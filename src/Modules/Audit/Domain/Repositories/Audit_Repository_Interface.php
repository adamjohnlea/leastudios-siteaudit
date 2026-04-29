<?php
/**
 * Audit repository contract.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;

/**
 * Persistence boundary for `Audit` aggregates.
 *
 * Implementations are expected to mutate the passed model in place when
 * assigning auto-increment ids on `save()`, then return the same instance.
 */
interface Audit_Repository_Interface {

	/**
	 * Persist a new audit. Assigns the auto-increment id to the model.
	 *
	 * @param Audit $audit Audit to insert.
	 *
	 * @return Audit
	 */
	public function save( Audit $audit ): Audit;

	/**
	 * Update mutable fields on the row matching `$audit->id()`. No-op if id is null.
	 *
	 * @param Audit $audit Audit to update.
	 *
	 * @return Audit
	 */
	public function update( Audit $audit ): Audit;

	/**
	 * Find an audit by primary key.
	 *
	 * @param int $id Audit id.
	 *
	 * @return Audit|null
	 */
	public function find_by_id( int $id ): ?Audit;

	/**
	 * List audits for a URL, ordered by audit_date DESC (newest first).
	 *
	 * @param int $url_id URL id.
	 *
	 * @return array<int, Audit>
	 */
	public function find_by_url_id( int $url_id ): array;

	/**
	 * Most recent audit for a URL regardless of strategy or status.
	 *
	 * @param int $url_id URL id.
	 *
	 * @return Audit|null
	 */
	public function find_latest_by_url_id( int $url_id ): ?Audit;

	/**
	 * Most recent COMPLETED audit for a (URL, strategy) tuple.
	 *
	 * @param int          $url_id   URL id.
	 * @param Run_Strategy $strategy Device profile.
	 *
	 * @return Audit|null
	 */
	public function find_latest_completed_by_url_id_and_strategy( int $url_id, Run_Strategy $strategy ): ?Audit;

	/**
	 * Latest completed score for each strategy across the given URL ids.
	 *
	 * Result shape: `[ url_id => [ 'desktop' => score, 'mobile' => score ] ]`.
	 *
	 * @param array<int, int> $url_ids URL ids.
	 *
	 * @return array<int, array<string, int>>
	 */
	public function find_latest_scores_by_url_ids( array $url_ids ): array;
}
