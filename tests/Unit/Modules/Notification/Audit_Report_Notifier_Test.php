<?php
/**
 * Audit_Report_Notifier unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Notification;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Dashboard_Summary;
use LEAStudios\SiteAudit\Modules\Notification\Application\Services\Audit_Report_Notifier;
use LEAStudios\SiteAudit\Modules\Notification\Application\Services\Email_Service_Interface;
use LEAStudios\SiteAudit\Modules\Notification\Domain\Repositories\Email_Subscription_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Pdf_Report_Data_Collector_Interface;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Pdf_Report_Service_Interface;
use LEAStudios\SiteAudit\Modules\Reporting\Domain\ValueObjects\Project_Report_Data;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Project_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Project_Name;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Url_Address;
use LEAStudios\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class Audit_Report_Notifier_Test extends TestCase {

	private Project_Repository_Interface&MockObject $project_repository;

	private Email_Subscription_Repository_Interface&MockObject $subscription_repository;

	private Pdf_Report_Data_Collector_Interface&MockObject $data_collector;

	private Pdf_Report_Service_Interface&MockObject $pdf_service;

	private Email_Service_Interface&MockObject $email_service;

	private Audit_Report_Notifier $notifier;

	public function set_up(): void {
		parent::set_up();

		$this->project_repository      = $this->createMock( Project_Repository_Interface::class );
		$this->subscription_repository = $this->createMock( Email_Subscription_Repository_Interface::class );
		$this->data_collector          = $this->createMock( Pdf_Report_Data_Collector_Interface::class );
		$this->pdf_service             = $this->createMock( Pdf_Report_Service_Interface::class );
		$this->email_service           = $this->createMock( Email_Service_Interface::class );

		$this->notifier = new Audit_Report_Notifier(
			$this->project_repository,
			$this->subscription_repository,
			$this->data_collector,
			$this->pdf_service,
			$this->email_service
		);
	}

	public function test_on_audit_completed_skips_failed_audits(): void {
		$url   = $this->make_url( 1 );
		$audit = $this->make_audit( Audit_Status::FAILED );

		$this->project_repository->expects( $this->never() )->method( 'find_by_id' );

		$this->notifier->on_audit_completed( $audit, $url, null );
	}

	public function test_on_audit_completed_skips_urls_without_a_project(): void {
		$url   = $this->make_url( null );
		$audit = $this->make_audit();

		$this->project_repository->expects( $this->never() )->method( 'find_by_id' );

		$this->notifier->on_audit_completed( $audit, $url, null );
	}

	public function test_notify_for_project_skips_unknown_projects(): void {
		$this->project_repository->method( 'find_by_id' )->with( 99 )->willReturn( null );

		$this->subscription_repository->expects( $this->never() )->method( 'find_subscribers_by_project_id' );
		$this->pdf_service->expects( $this->never() )->method( 'generate' );

		$this->notifier->notify_for_project( 99 );
	}

	public function test_notify_for_project_skips_when_no_subscribers(): void {
		$project = $this->make_project( 1 );

		$this->project_repository->method( 'find_by_id' )->willReturn( $project );
		$this->subscription_repository->method( 'find_subscribers_by_project_id' )->willReturn( [] );

		$this->pdf_service->expects( $this->never() )->method( 'generate' );
		$this->email_service->expects( $this->never() )->method( 'send_with_attachment' );

		$this->notifier->notify_for_project( 1 );
	}

	public function test_notify_for_project_renders_pdf_and_sends_to_each_subscriber(): void {
		$project     = $this->make_project( 1 );
		$report      = new Project_Report_Data(
			'Test',
			'2024-01-15 12:00',
			new Dashboard_Summary(
				1,
				1,
				85,
				0,
				[
					'excellent'  => 1,
					'good'       => 0,
					'needs_work' => 0,
					'poor'       => 0,
				]
			),
			[],
			[],
			0,
			[
				'critical' => 0,
				'serious'  => 0,
				'moderate' => 0,
				'minor'    => 0,
			]
		);
		$subscribers = [
			$this->make_subscriber( 'a@example.com' ),
			$this->make_subscriber( 'b@example.com' ),
		];

		$this->project_repository->method( 'find_by_id' )->willReturn( $project );
		$this->subscription_repository->method( 'find_subscribers_by_project_id' )->willReturn( $subscribers );
		$this->data_collector->method( 'collect' )->willReturn( $report );
		$this->pdf_service->method( 'generate' )->willReturn( '%PDF-1.4 fake bytes' );

		$this->email_service
			->expects( $this->exactly( 2 ) )
			->method( 'send_with_attachment' )
			->with(
				$this->callback( static fn( string $to ): bool => in_array( $to, [ 'a@example.com', 'b@example.com' ], true ) ),
				$this->stringContains( 'Audit Report' ),
				$this->stringContains( 'Test' ),
				'%PDF-1.4 fake bytes',
				$this->stringContains( 'report-' )
			)
			->willReturn( true );

		$this->notifier->notify_for_project( 1 );
	}

	private function make_url( ?int $project_id ): Url {
		$now = new \DateTimeImmutable();
		return new Url(
			1,
			$project_id,
			new Url_Address( 'https://example.com' ),
			'Example',
			Audit_Frequency::WEEKLY,
			Audit_Strategy::BOTH,
			true,
			false,
			null,
			null,
			null,
			$now,
			$now
		);
	}

	private function make_audit( Audit_Status $status = Audit_Status::COMPLETED ): Audit {
		$now = new \DateTimeImmutable();
		return new Audit(
			null,
			1,
			new Accessibility_Score( 85 ),
			$status,
			Run_Strategy::DESKTOP,
			$now,
			null,
			null,
			0,
			$now
		);
	}

	private function make_project( int $id ): Project {
		$now = new \DateTimeImmutable();
		return new Project( $id, new Project_Name( 'Test' ), null, $now, $now );
	}

	private function make_subscriber( string $email ): \WP_User {
		$user             = new \WP_User();
		$user->user_email = $email;
		$user->ID         = (int) hexdec( substr( md5( $email ), 0, 6 ) );
		return $user;
	}
}
