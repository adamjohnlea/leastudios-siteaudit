<?php
/**
 * Url_Service unit tests with a mocked repository.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Url;

use LEAStudios\SiteAudit\Modules\Url\Application\Services\Url_Service;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Url_Address;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;
use LEAStudios\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class Url_Service_Test extends TestCase {

	/**
	 * @var Url_Repository_Interface&MockObject
	 */
	private $repository;

	private Url_Service $service;

	public function set_up(): void {
		parent::set_up();
		$this->repository = $this->createMock( Url_Repository_Interface::class );
		$this->service    = new Url_Service( $this->repository );
	}

	public function test_create_saves_url_and_returns_it(): void {
		$this->repository->method( 'find_by_url' )->willReturn( null );
		$this->repository->expects( $this->once() )
			->method( 'save' )
			->willReturnCallback(
				static function ( Url $url ): Url {
					$url->set_id( 1 );
					return $url;
				}
			);

		$result = $this->service->create( 'https://example.com', 'Example', 'weekly' );

		$this->assertSame( 1, $result->id() );
		$this->assertSame( 'https://example.com', $result->url()->value() );
		$this->assertSame( 'Example', $result->name() );
		$this->assertSame( Audit_Frequency::WEEKLY, $result->audit_frequency() );
		$this->assertTrue( $result->is_enabled() );
	}

	public function test_create_with_project_id(): void {
		$this->repository->method( 'find_by_url' )->willReturn( null );
		$this->repository->expects( $this->once() )
			->method( 'save' )
			->willReturnCallback(
				static function ( Url $url ): Url {
					$url->set_id( 1 );
					return $url;
				}
			);

		$result = $this->service->create( 'https://example.com', 'Example', 'daily', 5 );

		$this->assertSame( 5, $result->project_id() );
		$this->assertSame( Audit_Frequency::DAILY, $result->audit_frequency() );
	}

	public function test_create_throws_exception_for_duplicate_url(): void {
		$this->repository->expects( $this->once() )
			->method( 'find_by_url' )
			->with( 'https://example.com' )
			->willReturn( $this->make_url( 1, 'https://example.com', 'Existing' ) );

		$this->repository->expects( $this->never() )->method( 'save' );

		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'This URL has already been added.' );

		$this->service->create( 'https://example.com', 'Duplicate', 'weekly' );
	}

	public function test_create_throws_exception_for_invalid_url(): void {
		$this->repository->expects( $this->never() )->method( 'save' );

		$this->expectException( Validation_Exception::class );

		$this->service->create( 'not-a-url', 'Invalid', 'weekly' );
	}

	public function test_create_throws_exception_for_invalid_frequency(): void {
		$this->repository->expects( $this->never() )->method( 'save' );

		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'Invalid audit frequency' );

		$this->service->create( 'https://example.com', 'Example', 'invalid' );
	}

	public function test_update_modifies_existing_url(): void {
		$existing = $this->make_url( 1, 'https://example.com', 'Original' );

		$this->repository->expects( $this->once() )
			->method( 'find_by_id' )
			->with( 1 )
			->willReturn( $existing );

		$this->repository->expects( $this->once() )
			->method( 'update' )
			->willReturnCallback( static fn( Url $url ): Url => $url );

		$result = $this->service->update( 1, 'Updated', 'daily', null, false );

		$this->assertSame( 'Updated', $result->name() );
		$this->assertSame( Audit_Frequency::DAILY, $result->audit_frequency() );
		$this->assertFalse( $result->is_enabled() );
	}

	public function test_update_throws_exception_when_url_not_found(): void {
		$this->repository->expects( $this->once() )
			->method( 'find_by_id' )
			->with( 999 )
			->willReturn( null );

		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'URL not found' );

		$this->service->update( 999, 'Updated' );
	}

	public function test_delete_removes_url(): void {
		$existing = $this->make_url( 1, 'https://example.com', 'Example' );

		$this->repository->expects( $this->once() )
			->method( 'find_by_id' )
			->with( 1 )
			->willReturn( $existing );

		$this->repository->expects( $this->once() )
			->method( 'delete' )
			->with( 1 );

		$this->service->delete( 1 );
	}

	public function test_delete_throws_exception_when_url_not_found(): void {
		$this->repository->expects( $this->once() )
			->method( 'find_by_id' )
			->with( 999 )
			->willReturn( null );

		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'URL not found' );

		$this->service->delete( 999 );
	}

	public function test_alert_threshold_score_validation_rejects_out_of_range(): void {
		$this->repository->method( 'find_by_url' )->willReturn( null );

		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'Alert threshold score must be between 0 and 100.' );

		$this->service->create( 'https://example.com', 'Example', 'weekly', null, 'both', true, 150 );
	}

	public function test_alert_threshold_drop_validation_rejects_zero(): void {
		$this->repository->method( 'find_by_url' )->willReturn( null );

		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'Alert threshold drop must be between 1 and 100.' );

		$this->service->create( 'https://example.com', 'Example', 'weekly', null, 'both', true, null, 0 );
	}

	private function make_url( int $id, string $address, string $name ): Url {
		$now = new \DateTimeImmutable();

		return new Url(
			$id,
			null,
			new Url_Address( $address ),
			$name,
			Audit_Frequency::WEEKLY,
			Audit_Strategy::BOTH,
			true,
			false,
			null,
			null,
			null,
			$now,
			$now,
		);
	}
}
