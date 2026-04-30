<?php
/**
 * Alert_Notifier unit tests.
 *
 * Verifies threshold logic across the audits collection (one alert email
 * per `run_audit()` call, picking the worst breach), early-exit guards,
 * and per-subscriber dispatch.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Notification;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Notification\Application\Services\Alert_Notifier;
use LEAStudios\SiteAudit\Modules\Notification\Application\Services\Email_Service_Interface;
use LEAStudios\SiteAudit\Modules\Notification\Domain\Repositories\Email_Subscription_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Url_Address;
use LEAStudios\SiteAudit\Shared\Template_Renderer;
use LEAStudios\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class Alert_Notifier_Test extends TestCase {

	private Email_Subscription_Repository_Interface&MockObject $subscription_repository;

	private Email_Service_Interface&MockObject $email_service;

	private Alert_Notifier $notifier;

	public function set_up(): void {
		parent::set_up();

		$this->subscription_repository = $this->createMock( Email_Subscription_Repository_Interface::class );
		$this->email_service           = $this->createMock( Email_Service_Interface::class );
		$this->notifier                = new Alert_Notifier(
			$this->subscription_repository,
			$this->email_service,
			new Template_Renderer( LEASTUDIOS_SITEAUDIT_DIR . 'templates' )
		);
	}

	public function test_does_nothing_when_alerts_are_disabled(): void {
		$url = $this->make_url( alerts_enabled: false, threshold_score: 70, project_id: 1 );

		$this->email_service->expects( $this->never() )->method( 'send' );

		$this->notifier->notify_if_threshold_breached( $url, [ $this->make_audit( Run_Strategy::DESKTOP, 50 ) ], [] );
	}

	public function test_does_nothing_when_url_has_no_project(): void {
		$url = $this->make_url( alerts_enabled: true, threshold_score: 70, project_id: null );

		$this->email_service->expects( $this->never() )->method( 'send' );

		$this->notifier->notify_if_threshold_breached( $url, [ $this->make_audit( Run_Strategy::DESKTOP, 50 ) ], [] );
	}

	public function test_does_nothing_when_no_thresholds_are_set(): void {
		$url = $this->make_url( alerts_enabled: true, threshold_score: null, threshold_drop: null, project_id: 1 );

		$this->email_service->expects( $this->never() )->method( 'send' );

		$this->notifier->notify_if_threshold_breached( $url, [ $this->make_audit( Run_Strategy::DESKTOP, 0 ) ], [] );
	}

	public function test_does_nothing_when_no_strategy_breaches_threshold(): void {
		$url = $this->make_url( alerts_enabled: true, threshold_score: 70, threshold_drop: 10, project_id: 1 );

		$audits   = [
			$this->make_audit( Run_Strategy::DESKTOP, 90 ),
			$this->make_audit( Run_Strategy::MOBILE, 88 ),
		];
		$previous = [
			Run_Strategy::DESKTOP->value => $this->make_audit( Run_Strategy::DESKTOP, 92 ),
			Run_Strategy::MOBILE->value  => $this->make_audit( Run_Strategy::MOBILE, 91 ),
		];

		$this->email_service->expects( $this->never() )->method( 'send' );

		$this->notifier->notify_if_threshold_breached( $url, $audits, $previous );
	}

	public function test_skips_failed_audits_in_the_run(): void {
		$url = $this->make_url( alerts_enabled: true, threshold_score: 70, project_id: 1 );

		$audits = [
			$this->make_audit( Run_Strategy::DESKTOP, 0, Audit_Status::FAILED ),
			$this->make_audit( Run_Strategy::MOBILE, 90 ),
		];

		$this->email_service->expects( $this->never() )->method( 'send' );

		$this->notifier->notify_if_threshold_breached( $url, $audits, [] );
	}

	public function test_fires_one_alert_when_score_at_or_below_threshold(): void {
		$url        = $this->make_url( alerts_enabled: true, threshold_score: 70, project_id: 1 );
		$audits     = [ $this->make_audit( Run_Strategy::DESKTOP, 70 ) ];
		$subscriber = $this->make_subscriber( 'admin@example.com' );

		$this->subscription_repository->method( 'find_subscribers_by_project_id' )->with( 1 )->willReturn( [ $subscriber ] );

		$this->email_service
			->expects( $this->once() )
			->method( 'send' )
			->with(
				'admin@example.com',
				$this->stringContains( 'Score Alert' ),
				$this->stringContains( '70' )
			)
			->willReturn( true );

		$this->notifier->notify_if_threshold_breached( $url, $audits, [] );
	}

	public function test_fires_one_alert_when_score_drops_by_threshold(): void {
		$url      = $this->make_url( alerts_enabled: true, threshold_score: null, threshold_drop: 10, project_id: 1 );
		$audits   = [ $this->make_audit( Run_Strategy::DESKTOP, 70 ) ];
		$previous = [ Run_Strategy::DESKTOP->value => $this->make_audit( Run_Strategy::DESKTOP, 85 ) ];

		$this->subscription_repository->method( 'find_subscribers_by_project_id' )->willReturn( [ $this->make_subscriber( 'admin@example.com' ) ] );

		$this->email_service
			->expects( $this->once() )
			->method( 'send' )
			->willReturn( true );

		$this->notifier->notify_if_threshold_breached( $url, $audits, $previous );
	}

	public function test_picks_worst_breach_when_multiple_strategies_breach(): void {
		$url    = $this->make_url( alerts_enabled: true, threshold_score: 70, project_id: 1 );
		$audits = [
			$this->make_audit( Run_Strategy::DESKTOP, 65 ),
			$this->make_audit( Run_Strategy::MOBILE, 40 ), // Worse score wins.
		];

		$this->subscription_repository->method( 'find_subscribers_by_project_id' )->willReturn( [ $this->make_subscriber( 'admin@example.com' ) ] );

		// Email body should mention the lower score (40), not 65.
		$this->email_service
			->expects( $this->once() )
			->method( 'send' )
			->with(
				$this->anything(),
				$this->anything(),
				$this->stringContains( '40' )
			)
			->willReturn( true );

		$this->notifier->notify_if_threshold_breached( $url, $audits, [] );
	}

	public function test_sends_only_one_email_per_run_even_when_both_strategies_breach(): void {
		$url    = $this->make_url( alerts_enabled: true, threshold_score: 70, project_id: 1 );
		$audits = [
			$this->make_audit( Run_Strategy::DESKTOP, 50 ),
			$this->make_audit( Run_Strategy::MOBILE, 60 ),
		];

		$this->subscription_repository->method( 'find_subscribers_by_project_id' )->willReturn( [ $this->make_subscriber( 'admin@example.com' ) ] );

		$this->email_service
			->expects( $this->once() ) // Exactly one, not two.
			->method( 'send' )
			->willReturn( true );

		$this->notifier->notify_if_threshold_breached( $url, $audits, [] );
	}

	public function test_sends_to_every_subscriber(): void {
		$url    = $this->make_url( alerts_enabled: true, threshold_score: 70, project_id: 1 );
		$audits = [ $this->make_audit( Run_Strategy::DESKTOP, 60 ) ];

		$subscribers = [
			$this->make_subscriber( 'one@example.com' ),
			$this->make_subscriber( 'two@example.com' ),
			$this->make_subscriber( 'three@example.com' ),
		];

		$this->subscription_repository->method( 'find_subscribers_by_project_id' )->willReturn( $subscribers );

		$this->email_service
			->expects( $this->exactly( 3 ) )
			->method( 'send' )
			->willReturn( true );

		$this->notifier->notify_if_threshold_breached( $url, $audits, [] );
	}

	public function test_does_nothing_when_no_subscribers_exist(): void {
		$url    = $this->make_url( alerts_enabled: true, threshold_score: 70, project_id: 1 );
		$audits = [ $this->make_audit( Run_Strategy::DESKTOP, 50 ) ];

		$this->subscription_repository->method( 'find_subscribers_by_project_id' )->willReturn( [] );
		$this->email_service->expects( $this->never() )->method( 'send' );

		$this->notifier->notify_if_threshold_breached( $url, $audits, [] );
	}

	private function make_url( bool $alerts_enabled, ?int $project_id, ?int $threshold_score = null, ?int $threshold_drop = null ): Url {
		$now = new \DateTimeImmutable();

		return new Url(
			1,
			$project_id,
			new Url_Address( 'https://example.com' ),
			'Example',
			Audit_Frequency::WEEKLY,
			Audit_Strategy::BOTH,
			true,
			$alerts_enabled,
			$threshold_score,
			$threshold_drop,
			null,
			$now,
			$now
		);
	}

	private function make_audit( Run_Strategy $strategy, int $score, Audit_Status $status = Audit_Status::COMPLETED ): Audit {
		$now = new \DateTimeImmutable();

		return new Audit(
			null,
			1,
			new Accessibility_Score( $score ),
			$status,
			$strategy,
			$now,
			null,
			null,
			0,
			$now
		);
	}

	private function make_subscriber( string $email ): \WP_User {
		$user             = new \WP_User();
		$user->user_email = $email;
		$user->ID         = (int) hexdec( substr( md5( $email ), 0, 6 ) );

		return $user;
	}
}
