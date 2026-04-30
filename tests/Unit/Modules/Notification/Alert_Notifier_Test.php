<?php
/**
 * Alert_Notifier unit tests.
 *
 * Verifies threshold logic, early-exit guards, and per-subscriber dispatch.
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
		$this->notifier                = new Alert_Notifier( $this->subscription_repository, $this->email_service );
	}

	public function test_does_nothing_when_audit_is_not_completed(): void {
		$url   = $this->make_url( alerts_enabled: true, threshold_score: 70, project_id: 1 );
		$audit = $this->make_audit( 50, Audit_Status::FAILED );

		$this->email_service->expects( $this->never() )->method( 'send' );

		$this->notifier->notify_if_threshold_breached( $audit, $url, null );
	}

	public function test_does_nothing_when_alerts_are_disabled(): void {
		$url   = $this->make_url( alerts_enabled: false, threshold_score: 70, project_id: 1 );
		$audit = $this->make_audit( 50 );

		$this->email_service->expects( $this->never() )->method( 'send' );

		$this->notifier->notify_if_threshold_breached( $audit, $url, null );
	}

	public function test_does_nothing_when_url_has_no_project(): void {
		$url   = $this->make_url( alerts_enabled: true, threshold_score: 70, project_id: null );
		$audit = $this->make_audit( 50 );

		$this->email_service->expects( $this->never() )->method( 'send' );

		$this->notifier->notify_if_threshold_breached( $audit, $url, null );
	}

	public function test_does_nothing_when_no_thresholds_are_set(): void {
		$url   = $this->make_url( alerts_enabled: true, threshold_score: null, threshold_drop: null, project_id: 1 );
		$audit = $this->make_audit( 0 );

		$this->email_service->expects( $this->never() )->method( 'send' );

		$this->notifier->notify_if_threshold_breached( $audit, $url, null );
	}

	public function test_does_nothing_when_no_thresholds_breached(): void {
		$url   = $this->make_url( alerts_enabled: true, threshold_score: 70, threshold_drop: 10, project_id: 1 );
		$audit = $this->make_audit( 90 );
		$prior = $this->make_audit( 95 );

		$this->email_service->expects( $this->never() )->method( 'send' );

		$this->notifier->notify_if_threshold_breached( $audit, $url, $prior );
	}

	public function test_fires_alert_when_score_at_or_below_threshold(): void {
		$url        = $this->make_url( alerts_enabled: true, threshold_score: 70, project_id: 1 );
		$audit      = $this->make_audit( 70 );
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

		$this->notifier->notify_if_threshold_breached( $audit, $url, null );
	}

	public function test_fires_alert_when_score_drops_by_threshold(): void {
		$url        = $this->make_url( alerts_enabled: true, threshold_score: null, threshold_drop: 10, project_id: 1 );
		$audit      = $this->make_audit( 70 );
		$prior      = $this->make_audit( 85 );  // 15 point drop
		$subscriber = $this->make_subscriber( 'admin@example.com' );

		$this->subscription_repository->method( 'find_subscribers_by_project_id' )->willReturn( [ $subscriber ] );

		$this->email_service
			->expects( $this->once() )
			->method( 'send' )
			->willReturn( true );

		$this->notifier->notify_if_threshold_breached( $audit, $url, $prior );
	}

	public function test_does_not_fire_drop_alert_when_no_previous_audit(): void {
		$url   = $this->make_url( alerts_enabled: true, threshold_score: null, threshold_drop: 10, project_id: 1 );
		$audit = $this->make_audit( 50 );  // First audit ever; no drop to measure.

		$this->email_service->expects( $this->never() )->method( 'send' );

		$this->notifier->notify_if_threshold_breached( $audit, $url, null );
	}

	public function test_sends_to_every_subscriber(): void {
		$url   = $this->make_url( alerts_enabled: true, threshold_score: 70, project_id: 1 );
		$audit = $this->make_audit( 60 );

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

		$this->notifier->notify_if_threshold_breached( $audit, $url, null );
	}

	public function test_does_nothing_when_no_subscribers_exist(): void {
		$url   = $this->make_url( alerts_enabled: true, threshold_score: 70, project_id: 1 );
		$audit = $this->make_audit( 50 );

		$this->subscription_repository->method( 'find_subscribers_by_project_id' )->willReturn( [] );
		$this->email_service->expects( $this->never() )->method( 'send' );

		$this->notifier->notify_if_threshold_breached( $audit, $url, null );
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

	private function make_audit( int $score, Audit_Status $status = Audit_Status::COMPLETED ): Audit {
		$now = new \DateTimeImmutable();

		return new Audit(
			null,
			1,
			new Accessibility_Score( $score ),
			$status,
			Run_Strategy::DESKTOP,
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
