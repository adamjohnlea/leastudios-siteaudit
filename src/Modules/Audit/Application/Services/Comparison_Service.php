<?php
/**
 * Compare two audits and produce an Audit_Comparison.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit_Comparison;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Score_Delta;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Trend;
use LEAStudios\SiteAudit\Shared\Datetime_Util;

/**
 * Pure-function comparator between two audits.
 *
 * Identifies issues by their `description` string — issues with the same
 * description across two audits are treated as the same underlying problem,
 * counted as `persistent`. Issues only in `current` are `new`; issues only in
 * `previous` are `resolved`.
 */
final class Comparison_Service {

	/**
	 * Build an Audit_Comparison summarising current vs previous.
	 *
	 * @param Audit $current  Newer audit.
	 * @param Audit $previous Older audit.
	 *
	 * @return Audit_Comparison
	 */
	public function compare( Audit $current, Audit $previous ): Audit_Comparison {
		$delta = $current->score()->delta( $previous->score() );

		$current_descriptions  = $this->issue_descriptions( $current );
		$previous_descriptions = $this->issue_descriptions( $previous );

		$new_issues        = array_diff( $current_descriptions, $previous_descriptions );
		$resolved_issues   = array_diff( $previous_descriptions, $current_descriptions );
		$persistent_issues = array_intersect( $current_descriptions, $previous_descriptions );

		return new Audit_Comparison(
			null,
			$current->id() ?? 0,
			$previous->id() ?? 0,
			new Score_Delta( $delta ),
			count( $new_issues ),
			count( $resolved_issues ),
			count( $persistent_issues ),
			Trend::from_delta( $delta ),
			Datetime_Util::now(),
		);
	}

	/**
	 * Extract issue descriptions from an audit.
	 *
	 * @param Audit $audit Audit instance.
	 *
	 * @return array<int, string>
	 */
	private function issue_descriptions( Audit $audit ): array {
		$descriptions = [];

		foreach ( $audit->issues() as $issue ) {
			$descriptions[] = $issue->description();
		}

		return $descriptions;
	}
}
