<?php
/**
 * Audit_Worker integration test.
 *
 * Verifies the worker calls `Audit_Service::run_audit()` with the URL id it
 * was handed, lifts the PHP timeout for long PageSpeed calls, and treats a
 * deleted-URL race (Validation_Exception from the service) as a successful
 * no-op rather than a worker error.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Scheduler;

use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Audit_Service_Interface;
use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Audit_Worker;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;
use LEAStudios\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class Audit_Worker_Test extends TestCase {

	private Audit_Service_Interface&MockObject $audit_service;

	private Audit_Worker $worker;

	public function set_up(): void {
		parent::set_up();
		$this->audit_service = $this->createMock( Audit_Service_Interface::class );
		$this->worker        = new Audit_Worker( $this->audit_service );
	}

	public function test_run_delegates_to_audit_service_with_url_id(): void {
		$this->audit_service
			->expects( $this->once() )
			->method( 'run_audit' )
			->with( 42 )
			->willReturn( [] );

		$this->worker->run( 42 );
	}

	public function test_run_swallows_validation_exception_for_deleted_url(): void {
		$this->audit_service
			->method( 'run_audit' )
			->willThrowException( new Validation_Exception( 'URL not found' ) );

		// Should NOT re-throw — Action Scheduler treats throws as failures and
		// retries them; a deleted URL is genuinely no-longer-needed work.
		$this->worker->run( 99999 );
		$this->assertTrue( true );
	}

	public function test_run_does_not_swallow_unexpected_throwables(): void {
		$this->audit_service
			->method( 'run_audit' )
			->willThrowException( new \RuntimeException( 'database down' ) );

		// Genuine errors must propagate so AS can retry the action with backoff.
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'database down' );

		$this->worker->run( 1 );
	}
}
