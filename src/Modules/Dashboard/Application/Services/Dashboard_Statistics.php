<?php
/**
 * Pure-function aggregator producing dashboard summaries from URLs + audits.
 *
 * @package LEAStudios\SiteAudit\Modules\Dashboard\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Dashboard\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Dashboard_Summary;
use LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Url_Summary;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;

/**
 * Stateless service that turns a `(urls, audits-by-url)` pair into the
 * aggregate dashboard summary and per-URL summaries used by the views.
 *
 * The caller is responsible for assembling `auditsByUrl` (keyed by URL id,
 * each value an audit list in DESC order); this service holds no I/O.
 */
final class Dashboard_Statistics {

	/**
	 * Compute aggregate metrics across a set of URLs.
	 *
	 * @param array<int, Url>               $urls          URLs in the set.
	 * @param array<int, array<int, Audit>> $audits_by_url Map of url_id => audits (DESC).
	 *
	 * @return Dashboard_Summary
	 */
	public function calculate_summary( array $urls, array $audits_by_url ): Dashboard_Summary {
		$total_audits    = 0;
		$score_sum       = 0;
		$scored_urls     = 0;
		$needs_attention = 0;
		$distribution    = [
			'excellent'  => 0,
			'good'       => 0,
			'needs_work' => 0,
			'poor'       => 0,
		];

		foreach ( $urls as $url ) {
			$url_id        = $url->id() ?? 0;
			$audits        = $audits_by_url[ $url_id ] ?? [];
			$total_audits += count( $audits );

			if ( [] === $audits ) {
				continue;
			}

			$latest_score = $audits[0]->score()->value();
			$score_sum   += $latest_score;
			++$scored_urls;

			if ( $latest_score < 70 ) {
				++$needs_attention;
			}

			match ( true ) {
				$latest_score >= 90 => ++$distribution['excellent'],
				$latest_score >= 70 => ++$distribution['good'],
				$latest_score >= 50 => ++$distribution['needs_work'],
				default             => ++$distribution['poor'],
			};
		}

		$average_score = $scored_urls > 0 ? (int) round( $score_sum / $scored_urls ) : 0;

		return new Dashboard_Summary(
			count( $urls ),
			$total_audits,
			$average_score,
			$needs_attention,
			$distribution
		);
	}

	/**
	 * Build per-URL summary VOs for the project / list views.
	 *
	 * @param array<int, Url>               $urls          URLs in the set.
	 * @param array<int, array<int, Audit>> $audits_by_url Map of url_id => audits (DESC).
	 *
	 * @return array<int, Url_Summary>
	 */
	public function generate_url_summaries( array $urls, array $audits_by_url ): array {
		$summaries = [];

		foreach ( $urls as $url ) {
			$url_id = $url->id() ?? 0;
			$audits = $audits_by_url[ $url_id ] ?? [];

			$latest_score         = [] !== $audits ? $audits[0]->score()->value() : null;
			$latest_desktop_score = null;
			$latest_mobile_score  = null;

			foreach ( $audits as $audit ) {
				if ( null === $latest_desktop_score && Run_Strategy::DESKTOP === $audit->strategy() ) {
					$latest_desktop_score = $audit->score()->value();
				}

				if ( null === $latest_mobile_score && Run_Strategy::MOBILE === $audit->strategy() ) {
					$latest_mobile_score = $audit->score()->value();
				}

				if ( null !== $latest_desktop_score && null !== $latest_mobile_score ) {
					break;
				}
			}

			$summaries[] = new Url_Summary(
				$url_id,
				$url->name() ?? $url->url()->value(),
				$url->url()->value(),
				$latest_score,
				count( $audits ),
				$url->audit_frequency()->label(),
				$url->is_enabled(),
				$latest_desktop_score,
				$latest_mobile_score
			);
		}

		return $summaries;
	}
}
