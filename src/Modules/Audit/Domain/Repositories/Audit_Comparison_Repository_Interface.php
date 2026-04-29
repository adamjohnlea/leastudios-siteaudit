<?php
/**
 * Audit comparison repository contract.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit_Comparison;

/**
 * Persistence boundary for `Audit_Comparison` aggregates.
 *
 * Each current audit may have at most one comparison row (enforced by a
 * UNIQUE index on `current_audit_id`).
 */
interface Audit_Comparison_Repository_Interface {

	/**
	 * Persist a new comparison. Assigns the auto-increment id to the model.
	 *
	 * @param Audit_Comparison $comparison Comparison to insert.
	 *
	 * @return Audit_Comparison
	 */
	public function save( Audit_Comparison $comparison ): Audit_Comparison;

	/**
	 * Find the comparison whose `current_audit_id` matches.
	 *
	 * @param int $current_audit_id Current audit id.
	 *
	 * @return Audit_Comparison|null
	 */
	public function find_by_current_audit_id( int $current_audit_id ): ?Audit_Comparison;

	/**
	 * List comparisons whose current audit belongs to a given URL, newest first.
	 *
	 * @param int $url_id URL id.
	 *
	 * @return array<int, Audit_Comparison>
	 */
	public function find_by_url_id( int $url_id ): array;
}
