<?php
/**
 * Time-series trend + average + graph-data calculator.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Trend;

/**
 * Pure derivations over a list of audits (newest first, as repositories return).
 *
 * Used by dashboard widgets and per-URL detail pages to summarise score history
 * without burdening the caller with the sort-direction convention.
 */
final class Trend_Calculator {

	/**
	 * Determine the overall trend from latest to earliest in the list.
	 *
	 * @param array<int, Audit> $audits Audits in DESC order (newest first).
	 *
	 * @return Trend
	 */
	public function calculate_trend( array $audits ): Trend {
		if ( count( $audits ) <= 1 ) {
			return Trend::STABLE;
		}

		$latest   = $audits[0];
		$earliest = $audits[ count( $audits ) - 1 ];
		$delta    = $latest->score()->delta( $earliest->score() );

		return Trend::from_delta( $delta );
	}

	/**
	 * Generate a [score, date] tuple for each audit, preserving input order.
	 *
	 * @param array<int, Audit> $audits Audits.
	 *
	 * @return array<int, array{score: int, date: string}>
	 */
	public function generate_graph_data( array $audits ): array {
		$data = [];

		foreach ( $audits as $audit ) {
			$data[] = [
				'score' => $audit->score()->value(),
				'date'  => $audit->audit_date()->format( 'Y-m-d' ),
			];
		}

		return $data;
	}

	/**
	 * Mean score across the input list, rounded to nearest int. Returns 0 when empty.
	 *
	 * @param array<int, Audit> $audits Audits.
	 *
	 * @return int
	 */
	public function calculate_average( array $audits ): int {
		if ( [] === $audits ) {
			return 0;
		}

		$total = 0;

		foreach ( $audits as $audit ) {
			$total += $audit->score()->value();
		}

		return (int) round( $total / count( $audits ) );
	}
}
