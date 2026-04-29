<?php
/**
 * Wpdb_Audit_Comparison_Repository integration tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Repositories;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit_Comparison;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Score_Delta;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Trend;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Audit_Comparison_Repository;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Audit_Repository;
use LEAStudios\Tests\TestCase;

final class Wpdb_Audit_Comparison_Repository_Test extends TestCase {

	private Wpdb_Audit_Comparison_Repository $repository;

	private Wpdb_Audit_Repository $audit_repository;

	public function set_up(): void {
		parent::set_up();
		$this->repository       = new Wpdb_Audit_Comparison_Repository();
		$this->audit_repository = new Wpdb_Audit_Repository();
	}

	public function test_save_assigns_an_id(): void {
		$saved = $this->repository->save( $this->new_comparison( current_audit_id: 1, previous_audit_id: 2 ) );

		$this->assertNotNull( $saved->id() );
	}

	public function test_find_by_current_audit_id_round_trips(): void {
		$saved = $this->repository->save(
			$this->new_comparison(
				current_audit_id: 50,
				previous_audit_id: 49,
				score_delta: 12,
				new_issues: 3,
				resolved_issues: 1,
				persistent_issues: 5,
				trend: Trend::IMPROVING,
			)
		);

		$found = $this->repository->find_by_current_audit_id( 50 );

		$this->assertNotNull( $found );
		$this->assertSame( $saved->id(), $found->id() );
		$this->assertSame( 12, $found->score_delta()->value() );
		$this->assertSame( 3, $found->new_issues_count() );
		$this->assertSame( 1, $found->resolved_issues_count() );
		$this->assertSame( 5, $found->persistent_issues_count() );
		$this->assertSame( Trend::IMPROVING, $found->trend() );
	}

	public function test_find_by_current_audit_id_returns_null_when_missing(): void {
		$this->assertNull( $this->repository->find_by_current_audit_id( 99999 ) );
	}

	public function test_find_by_url_id_joins_through_audits_table(): void {
		// Two audits for url_id=42, one for url_id=99.
		$audit_a = $this->audit_repository->save( $this->new_audit( url_id: 42, score: 80, audit_date: '2024-01-01 12:00:00' ) );
		$audit_b = $this->audit_repository->save( $this->new_audit( url_id: 42, score: 85, audit_date: '2024-01-15 12:00:00' ) );
		$audit_c = $this->audit_repository->save( $this->new_audit( url_id: 99, score: 70, audit_date: '2024-01-15 12:00:00' ) );

		$this->repository->save(
			$this->new_comparison(
				current_audit_id: (int) $audit_b->id(),
				previous_audit_id: (int) $audit_a->id(),
				created_at: '2024-01-15 12:00:00',
			)
		);

		$this->repository->save(
			$this->new_comparison(
				current_audit_id: (int) $audit_c->id(),
				previous_audit_id: (int) $audit_b->id(),
				created_at: '2024-01-16 12:00:00',
			)
		);

		$results = $this->repository->find_by_url_id( 42 );

		$this->assertCount( 1, $results );
		$this->assertSame( (int) $audit_b->id(), $results[0]->current_audit_id() );
	}

	public function test_find_by_url_id_returns_newest_first(): void {
		$audit_old = $this->audit_repository->save( $this->new_audit( url_id: 7, score: 70, audit_date: '2024-01-01 12:00:00' ) );
		$audit_mid = $this->audit_repository->save( $this->new_audit( url_id: 7, score: 80, audit_date: '2024-01-08 12:00:00' ) );
		$audit_new = $this->audit_repository->save( $this->new_audit( url_id: 7, score: 90, audit_date: '2024-01-15 12:00:00' ) );

		$this->repository->save(
			$this->new_comparison(
				current_audit_id: (int) $audit_mid->id(),
				previous_audit_id: (int) $audit_old->id(),
				created_at: '2024-01-08 12:00:00',
			)
		);

		$this->repository->save(
			$this->new_comparison(
				current_audit_id: (int) $audit_new->id(),
				previous_audit_id: (int) $audit_mid->id(),
				created_at: '2024-01-15 12:00:00',
			)
		);

		$results = $this->repository->find_by_url_id( 7 );

		$this->assertCount( 2, $results );
		$this->assertSame( (int) $audit_new->id(), $results[0]->current_audit_id() );
		$this->assertSame( (int) $audit_mid->id(), $results[1]->current_audit_id() );
	}

	private function new_comparison(
		int $current_audit_id = 1,
		int $previous_audit_id = 2,
		int $score_delta = 0,
		int $new_issues = 0,
		int $resolved_issues = 0,
		int $persistent_issues = 0,
		Trend $trend = Trend::STABLE,
		string $created_at = '2024-01-01 12:00:00'
	): Audit_Comparison {
		return new Audit_Comparison(
			null,
			$current_audit_id,
			$previous_audit_id,
			new Score_Delta( $score_delta ),
			$new_issues,
			$resolved_issues,
			$persistent_issues,
			$trend,
			new \DateTimeImmutable( $created_at ),
		);
	}

	private function new_audit(
		int $url_id = 1,
		int $score = 80,
		string $audit_date = '2024-01-01 12:00:00'
	): Audit {
		$now = new \DateTimeImmutable( $audit_date );

		return new Audit(
			null,
			$url_id,
			new Accessibility_Score( $score ),
			Audit_Status::COMPLETED,
			Run_Strategy::DESKTOP,
			$now,
			null,
			null,
			0,
			$now,
		);
	}
}
