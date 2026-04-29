<?php
/**
 * Comparison_Service unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Audit;

use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Comparison_Service;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Issue;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Category;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Severity;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Trend;
use LEAStudios\Tests\TestCase;

final class Comparison_Service_Test extends TestCase {

	private Comparison_Service $service;

	public function set_up(): void {
		parent::set_up();
		$this->service = new Comparison_Service();
	}

	public function test_compare_calculates_positive_score_delta(): void {
		$previous = $this->make_audit( 1, 70 );
		$current  = $this->make_audit( 2, 85 );

		$comparison = $this->service->compare( $current, $previous );

		$this->assertSame( 15, $comparison->score_delta()->value() );
		$this->assertTrue( $comparison->score_delta()->is_improvement() );
	}

	public function test_compare_calculates_negative_score_delta(): void {
		$previous = $this->make_audit( 1, 90 );
		$current  = $this->make_audit( 2, 75 );

		$comparison = $this->service->compare( $current, $previous );

		$this->assertSame( -15, $comparison->score_delta()->value() );
		$this->assertTrue( $comparison->score_delta()->is_degradation() );
	}

	public function test_compare_calculates_zero_delta_for_same_score(): void {
		$previous = $this->make_audit( 1, 80 );
		$current  = $this->make_audit( 2, 80 );

		$comparison = $this->service->compare( $current, $previous );

		$this->assertSame( 0, $comparison->score_delta()->value() );
		$this->assertTrue( $comparison->score_delta()->is_stable() );
	}

	public function test_compare_sets_improving_trend_for_positive_delta(): void {
		$previous = $this->make_audit( 1, 70 );
		$current  = $this->make_audit( 2, 85 );

		$comparison = $this->service->compare( $current, $previous );

		$this->assertSame( Trend::IMPROVING, $comparison->trend() );
	}

	public function test_compare_sets_degrading_trend_for_negative_delta(): void {
		$previous = $this->make_audit( 1, 90 );
		$current  = $this->make_audit( 2, 75 );

		$comparison = $this->service->compare( $current, $previous );

		$this->assertSame( Trend::DEGRADING, $comparison->trend() );
	}

	public function test_compare_sets_stable_trend_for_zero_delta(): void {
		$previous = $this->make_audit( 1, 80 );
		$current  = $this->make_audit( 2, 80 );

		$comparison = $this->service->compare( $current, $previous );

		$this->assertSame( Trend::STABLE, $comparison->trend() );
	}

	public function test_compare_identifies_new_issues(): void {
		$previous = $this->make_audit( 1, 90 );
		$previous->set_issues(
			[
				$this->make_issue( 'color-contrast', Issue_Category::COLOR_CONTRAST ),
			]
		);

		$current = $this->make_audit( 2, 80 );
		$current->set_issues(
			[
				$this->make_issue( 'color-contrast', Issue_Category::COLOR_CONTRAST ),
				$this->make_issue( 'image-alt', Issue_Category::IMAGES ),
			]
		);

		$comparison = $this->service->compare( $current, $previous );

		$this->assertSame( 1, $comparison->new_issues_count() );
	}

	public function test_compare_identifies_resolved_issues(): void {
		$previous = $this->make_audit( 1, 80 );
		$previous->set_issues(
			[
				$this->make_issue( 'color-contrast', Issue_Category::COLOR_CONTRAST ),
				$this->make_issue( 'image-alt', Issue_Category::IMAGES ),
			]
		);

		$current = $this->make_audit( 2, 90 );
		$current->set_issues(
			[
				$this->make_issue( 'color-contrast', Issue_Category::COLOR_CONTRAST ),
			]
		);

		$comparison = $this->service->compare( $current, $previous );

		$this->assertSame( 1, $comparison->resolved_issues_count() );
	}

	public function test_compare_identifies_persistent_issues(): void {
		$previous = $this->make_audit( 1, 80 );
		$previous->set_issues(
			[
				$this->make_issue( 'color-contrast', Issue_Category::COLOR_CONTRAST ),
				$this->make_issue( 'image-alt', Issue_Category::IMAGES ),
			]
		);

		$current = $this->make_audit( 2, 80 );
		$current->set_issues(
			[
				$this->make_issue( 'color-contrast', Issue_Category::COLOR_CONTRAST ),
				$this->make_issue( 'image-alt', Issue_Category::IMAGES ),
			]
		);

		$comparison = $this->service->compare( $current, $previous );

		$this->assertSame( 2, $comparison->persistent_issues_count() );
		$this->assertSame( 0, $comparison->new_issues_count() );
		$this->assertSame( 0, $comparison->resolved_issues_count() );
	}

	public function test_compare_handles_no_issues_in_both_audits(): void {
		$previous = $this->make_audit( 1, 100 );
		$current  = $this->make_audit( 2, 100 );

		$comparison = $this->service->compare( $current, $previous );

		$this->assertSame( 0, $comparison->new_issues_count() );
		$this->assertSame( 0, $comparison->resolved_issues_count() );
		$this->assertSame( 0, $comparison->persistent_issues_count() );
	}

	public function test_compare_sets_audit_ids_correctly(): void {
		$previous = $this->make_audit( 10, 80 );
		$current  = $this->make_audit( 20, 85 );

		$comparison = $this->service->compare( $current, $previous );

		$this->assertSame( 20, $comparison->current_audit_id() );
		$this->assertSame( 10, $comparison->previous_audit_id() );
	}

	private function make_audit( int $id, int $score ): Audit {
		$now = new \DateTimeImmutable();

		return new Audit(
			$id,
			1,
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

	private function make_issue( string $description, Issue_Category $category ): Issue {
		return new Issue(
			null,
			1,
			Issue_Severity::CRITICAL,
			$category,
			$description,
			null,
			null,
			new \DateTimeImmutable(),
		);
	}
}
