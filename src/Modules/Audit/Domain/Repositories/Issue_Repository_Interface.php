<?php
/**
 * Issue repository contract.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Issue;

/**
 * Persistence boundary for `Issue` aggregates.
 *
 * Issues are always associated with exactly one audit; `save_many()` is the
 * primary insertion path used after a fresh audit completes.
 */
interface Issue_Repository_Interface {

	/**
	 * Persist a single issue.
	 *
	 * @param Issue $issue Issue to insert.
	 *
	 * @return Issue
	 */
	public function save( Issue $issue ): Issue;

	/**
	 * Persist many issues; should be transactional where supported.
	 *
	 * @param array<int, Issue> $issues Issues to insert.
	 *
	 * @return array<int, Issue>
	 */
	public function save_many( array $issues ): array;

	/**
	 * List issues for a given audit, ordered by severity ASC.
	 *
	 * @param int $audit_id Audit id.
	 *
	 * @return array<int, Issue>
	 */
	public function find_by_audit_id( int $audit_id ): array;
}
