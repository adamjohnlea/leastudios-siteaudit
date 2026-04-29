<?php
/**
 * Wpdb_Audit_Repository integration tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Repositories;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Audit_Repository;
use LEAStudios\Tests\TestCase;

final class Wpdb_Audit_Repository_Test extends TestCase {

	private Wpdb_Audit_Repository $repository;

	public function set_up(): void {
		parent::set_up();
		$this->repository = new Wpdb_Audit_Repository();
	}

	public function test_save_assigns_an_id(): void {
		$audit = $this->repository->save( $this->new_audit( url_id: 1, score: 75, strategy: Run_Strategy::DESKTOP ) );

		$this->assertNotNull( $audit->id() );
		$this->assertSame( 75, $audit->score()->value() );
	}

	public function test_find_by_id_round_trips(): void {
		$saved = $this->repository->save(
			$this->new_audit(
				url_id: 1,
				score: 88,
				strategy: Run_Strategy::MOBILE,
				status: Audit_Status::COMPLETED,
				raw_response: '{"foo":"bar"}',
				retry_count: 2,
			)
		);

		$found = $this->repository->find_by_id( (int) $saved->id() );

		$this->assertNotNull( $found );
		$this->assertSame( 88, $found->score()->value() );
		$this->assertSame( Run_Strategy::MOBILE, $found->strategy() );
		$this->assertSame( Audit_Status::COMPLETED, $found->status() );
		$this->assertSame( '{"foo":"bar"}', $found->raw_response() );
		$this->assertSame( 2, $found->retry_count() );
	}

	public function test_find_by_id_returns_null_when_missing(): void {
		$this->assertNull( $this->repository->find_by_id( 99999 ) );
	}

	public function test_find_by_url_id_returns_audits_newest_first(): void {
		$this->repository->save( $this->new_audit( url_id: 7, score: 70, audit_date: '2024-01-01 12:00:00' ) );
		$this->repository->save( $this->new_audit( url_id: 7, score: 80, audit_date: '2024-01-15 12:00:00' ) );
		$this->repository->save( $this->new_audit( url_id: 7, score: 75, audit_date: '2024-01-08 12:00:00' ) );
		$this->repository->save( $this->new_audit( url_id: 99, score: 50, audit_date: '2024-01-20 12:00:00' ) );

		$audits = $this->repository->find_by_url_id( 7 );

		$this->assertCount( 3, $audits );
		$this->assertSame( 80, $audits[0]->score()->value() );
		$this->assertSame( 75, $audits[1]->score()->value() );
		$this->assertSame( 70, $audits[2]->score()->value() );
	}

	public function test_find_latest_by_url_id_returns_newest_audit(): void {
		$this->repository->save( $this->new_audit( url_id: 11, score: 60, audit_date: '2024-01-01 12:00:00' ) );
		$this->repository->save( $this->new_audit( url_id: 11, score: 95, audit_date: '2024-02-01 12:00:00' ) );

		$latest = $this->repository->find_latest_by_url_id( 11 );

		$this->assertNotNull( $latest );
		$this->assertSame( 95, $latest->score()->value() );
	}

	public function test_find_latest_completed_by_url_id_and_strategy_filters_correctly(): void {
		$this->repository->save( $this->new_audit( url_id: 5, score: 90, strategy: Run_Strategy::DESKTOP, status: Audit_Status::COMPLETED, audit_date: '2024-01-01 12:00:00' ) );
		$this->repository->save( $this->new_audit( url_id: 5, score: 50, strategy: Run_Strategy::DESKTOP, status: Audit_Status::FAILED, audit_date: '2024-01-15 12:00:00' ) );
		$this->repository->save( $this->new_audit( url_id: 5, score: 70, strategy: Run_Strategy::MOBILE, status: Audit_Status::COMPLETED, audit_date: '2024-01-15 12:00:00' ) );

		$desktop = $this->repository->find_latest_completed_by_url_id_and_strategy( 5, Run_Strategy::DESKTOP );
		$mobile  = $this->repository->find_latest_completed_by_url_id_and_strategy( 5, Run_Strategy::MOBILE );

		$this->assertNotNull( $desktop );
		$this->assertSame( 90, $desktop->score()->value() );
		$this->assertNotNull( $mobile );
		$this->assertSame( 70, $mobile->score()->value() );
	}

	public function test_find_latest_scores_by_url_ids_groups_by_url_and_strategy(): void {
		$this->repository->save( $this->new_audit( url_id: 100, score: 70, strategy: Run_Strategy::DESKTOP, status: Audit_Status::COMPLETED, audit_date: '2024-01-01 12:00:00' ) );
		$this->repository->save( $this->new_audit( url_id: 100, score: 85, strategy: Run_Strategy::DESKTOP, status: Audit_Status::COMPLETED, audit_date: '2024-01-15 12:00:00' ) );
		$this->repository->save( $this->new_audit( url_id: 100, score: 60, strategy: Run_Strategy::MOBILE, status: Audit_Status::COMPLETED, audit_date: '2024-01-15 12:00:00' ) );
		$this->repository->save( $this->new_audit( url_id: 200, score: 50, strategy: Run_Strategy::DESKTOP, status: Audit_Status::COMPLETED, audit_date: '2024-01-15 12:00:00' ) );

		$result = $this->repository->find_latest_scores_by_url_ids( [ 100, 200 ] );

		$this->assertSame( 85, $result[100]['desktop'] );
		$this->assertSame( 60, $result[100]['mobile'] );
		$this->assertSame( 50, $result[200]['desktop'] );
		$this->assertArrayNotHasKey( 'mobile', $result[200] ?? [] );
	}

	public function test_find_latest_scores_returns_empty_for_empty_input(): void {
		$this->assertSame( [], $this->repository->find_latest_scores_by_url_ids( [] ) );
	}

	public function test_update_changes_status_and_error_message(): void {
		$audit = $this->repository->save( $this->new_audit( url_id: 1, score: 0, status: Audit_Status::PENDING ) );

		$audit->set_status( Audit_Status::FAILED );
		$audit->set_error_message( 'API timed out' );
		$audit->increment_retry_count();
		$this->repository->update( $audit );

		$reloaded = $this->repository->find_by_id( (int) $audit->id() );

		$this->assertNotNull( $reloaded );
		$this->assertSame( Audit_Status::FAILED, $reloaded->status() );
		$this->assertSame( 'API timed out', $reloaded->error_message() );
		$this->assertSame( 1, $reloaded->retry_count() );
	}

	private function new_audit(
		int $url_id = 1,
		int $score = 80,
		Run_Strategy $strategy = Run_Strategy::DESKTOP,
		Audit_Status $status = Audit_Status::COMPLETED,
		?string $raw_response = null,
		int $retry_count = 0,
		string $audit_date = '2024-01-01 12:00:00'
	): Audit {
		$now = new \DateTimeImmutable( $audit_date );

		return new Audit(
			null,
			$url_id,
			new Accessibility_Score( $score ),
			$status,
			$strategy,
			$now,
			$raw_response,
			null,
			$retry_count,
			$now,
		);
	}
}
