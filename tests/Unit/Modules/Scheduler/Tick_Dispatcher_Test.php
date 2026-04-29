<?php
/**
 * Tick_Dispatcher unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Scheduler;

use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Action_Enqueuer_Interface;
use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Frequency_Interval;
use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Tick_Dispatcher;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Url_Address;
use LEAStudios\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class Tick_Dispatcher_Test extends TestCase {

	private Url_Repository_Interface&MockObject $url_repository;

	private Action_Enqueuer_Interface&MockObject $enqueuer;

	private Tick_Dispatcher $dispatcher;

	public function set_up(): void {
		parent::set_up();

		$this->url_repository = $this->createMock( Url_Repository_Interface::class );
		$this->enqueuer       = $this->createMock( Action_Enqueuer_Interface::class );
		$this->dispatcher     = new Tick_Dispatcher( $this->url_repository, $this->enqueuer, new Frequency_Interval() );
	}

	public function test_enqueues_one_action_per_due_url(): void {
		$now = new \DateTimeImmutable( '2026-01-15 12:00:00' );

		$this->url_repository->method( 'find_enabled' )->willReturn(
			[
				$this->make_url( 1, Audit_Frequency::DAILY, new \DateTimeImmutable( '2026-01-13 12:00:00' ) ),  // 48h ago, due
				$this->make_url( 2, Audit_Frequency::WEEKLY, new \DateTimeImmutable( '2026-01-07 12:00:00' ) ), // 192h ago, due
			]
		);

		$this->enqueuer->method( 'has_pending' )->willReturn( false );

		$expected_calls = [];
		$this->enqueuer
			->expects( $this->exactly( 2 ) )
			->method( 'enqueue_async' )
			->with(
				Tick_Dispatcher::RUN_AUDIT_HOOK,
				$this->callback(
					static function ( array $args ) use ( &$expected_calls ): bool {
						$expected_calls[] = $args[0] ?? null;
						return true;
					}
				)
			);

		$enqueued = $this->dispatcher->tick( $now );

		$this->assertSame( 2, $enqueued );
		$this->assertSame( [ 1, 2 ], $expected_calls );
	}

	public function test_skips_urls_that_are_not_yet_due(): void {
		$now = new \DateTimeImmutable( '2026-01-15 12:00:00' );

		$this->url_repository->method( 'find_enabled' )->willReturn(
			[
				$this->make_url( 1, Audit_Frequency::DAILY, new \DateTimeImmutable( '2026-01-15 06:00:00' ) ), // 6h ago, not due (daily)
				$this->make_url( 2, Audit_Frequency::WEEKLY, new \DateTimeImmutable( '2026-01-13 12:00:00' ) ), // 48h ago, not due (weekly)
			]
		);

		$this->enqueuer->expects( $this->never() )->method( 'enqueue_async' );

		$this->assertSame( 0, $this->dispatcher->tick( $now ) );
	}

	public function test_treats_never_audited_urls_as_due(): void {
		$this->url_repository->method( 'find_enabled' )->willReturn(
			[
				$this->make_url( 1, Audit_Frequency::WEEKLY, null ),
			]
		);
		$this->enqueuer->method( 'has_pending' )->willReturn( false );

		$this->enqueuer
			->expects( $this->once() )
			->method( 'enqueue_async' )
			->with( Tick_Dispatcher::RUN_AUDIT_HOOK, [ 1 ] );

		$this->assertSame( 1, $this->dispatcher->tick() );
	}

	public function test_skips_urls_that_already_have_a_pending_action(): void {
		$now = new \DateTimeImmutable( '2026-01-15 12:00:00' );

		$this->url_repository->method( 'find_enabled' )->willReturn(
			[ $this->make_url( 1, Audit_Frequency::DAILY, new \DateTimeImmutable( '2026-01-13 12:00:00' ) ) ]
		);

		// Simulate a previous tick already queued URL 1 — guard returns true.
		$this->enqueuer->method( 'has_pending' )->with( Tick_Dispatcher::RUN_AUDIT_HOOK, [ 1 ] )->willReturn( true );

		$this->enqueuer->expects( $this->never() )->method( 'enqueue_async' );

		$this->assertSame( 0, $this->dispatcher->tick( $now ) );
	}

	public function test_returns_zero_when_no_enabled_urls_exist(): void {
		$this->url_repository->method( 'find_enabled' )->willReturn( [] );
		$this->enqueuer->expects( $this->never() )->method( 'enqueue_async' );

		$this->assertSame( 0, $this->dispatcher->tick() );
	}

	private function make_url( int $id, Audit_Frequency $frequency, ?\DateTimeImmutable $last_audited_at ): Url {
		$now = new \DateTimeImmutable();

		return new Url(
			$id,
			null,
			new Url_Address( 'https://example.com/' . $id ),
			'URL ' . $id,
			$frequency,
			Audit_Strategy::BOTH,
			true,
			false,
			null,
			null,
			$last_audited_at,
			$now,
			$now
		);
	}
}
