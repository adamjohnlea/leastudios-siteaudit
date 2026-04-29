<?php
/**
 * Trend_Calculator unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Audit;

use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Trend_Calculator;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Trend;
use LEAStudios\Tests\TestCase;

final class Trend_Calculator_Test extends TestCase {

	private Trend_Calculator $calculator;

	public function set_up(): void {
		parent::set_up();
		$this->calculator = new Trend_Calculator();
	}

	public function test_determines_improving_trend_from_ascending_scores(): void {
		$audits = [
			$this->make_audit( 85, '2024-01-22' ),
			$this->make_audit( 80, '2024-01-15' ),
			$this->make_audit( 75, '2024-01-08' ),
			$this->make_audit( 70, '2024-01-01' ),
		];

		$trend = $this->calculator->calculate_trend( $audits );

		$this->assertSame( Trend::IMPROVING, $trend );
	}

	public function test_determines_degrading_trend_from_descending_scores(): void {
		$audits = [
			$this->make_audit( 75, '2024-01-22' ),
			$this->make_audit( 80, '2024-01-15' ),
			$this->make_audit( 85, '2024-01-08' ),
			$this->make_audit( 90, '2024-01-01' ),
		];

		$trend = $this->calculator->calculate_trend( $audits );

		$this->assertSame( Trend::DEGRADING, $trend );
	}

	public function test_determines_stable_trend_from_consistent_scores(): void {
		$audits = [
			$this->make_audit( 80, '2024-01-01' ),
			$this->make_audit( 80, '2024-01-08' ),
			$this->make_audit( 80, '2024-01-15' ),
		];

		$trend = $this->calculator->calculate_trend( $audits );

		$this->assertSame( Trend::STABLE, $trend );
	}

	public function test_returns_stable_for_single_audit(): void {
		$audits = [
			$this->make_audit( 80, '2024-01-01' ),
		];

		$trend = $this->calculator->calculate_trend( $audits );

		$this->assertSame( Trend::STABLE, $trend );
	}

	public function test_returns_stable_for_empty_audits(): void {
		$trend = $this->calculator->calculate_trend( [] );

		$this->assertSame( Trend::STABLE, $trend );
	}

	public function test_uses_overall_direction_for_mixed_scores(): void {
		$audits = [
			$this->make_audit( 85, '2024-01-22' ),
			$this->make_audit( 80, '2024-01-15' ),
			$this->make_audit( 65, '2024-01-08' ),
			$this->make_audit( 70, '2024-01-01' ),
		];

		$trend = $this->calculator->calculate_trend( $audits );

		$this->assertSame( Trend::IMPROVING, $trend );
	}

	public function test_generates_graph_data_from_audits(): void {
		$audits = [
			$this->make_audit( 85, '2024-01-15' ),
			$this->make_audit( 80, '2024-01-08' ),
			$this->make_audit( 70, '2024-01-01' ),
		];

		$graph_data = $this->calculator->generate_graph_data( $audits );

		$this->assertCount( 3, $graph_data );
		$this->assertSame( 85, $graph_data[0]['score'] );
		$this->assertSame( '2024-01-15', $graph_data[0]['date'] );
		$this->assertSame( 70, $graph_data[2]['score'] );
	}

	public function test_calculates_average_score(): void {
		$audits = [
			$this->make_audit( 70, '2024-01-01' ),
			$this->make_audit( 80, '2024-01-08' ),
			$this->make_audit( 90, '2024-01-15' ),
		];

		$average = $this->calculator->calculate_average( $audits );

		$this->assertSame( 80, $average );
	}

	public function test_average_returns_zero_for_empty_audits(): void {
		$this->assertSame( 0, $this->calculator->calculate_average( [] ) );
	}

	private function make_audit( int $score, string $date ): Audit {
		$audit_date = new \DateTimeImmutable( $date );

		return new Audit(
			null,
			1,
			new Accessibility_Score( $score ),
			Audit_Status::COMPLETED,
			Run_Strategy::DESKTOP,
			$audit_date,
			null,
			null,
			0,
			$audit_date,
		);
	}
}
