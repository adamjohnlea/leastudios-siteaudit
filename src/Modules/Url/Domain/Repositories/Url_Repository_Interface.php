<?php
/**
 * URL repository contract.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Domain\Repositories
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Domain\Repositories;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;

/**
 * Persistence boundary for `Url` aggregates.
 *
 * Implementations are expected to mutate the passed model in place when
 * assigning auto-increment ids on `save()`, then return the same instance.
 */
interface Url_Repository_Interface {

	/**
	 * Persist a new URL. Assigns the auto-increment id to the model.
	 *
	 * @param Url $url URL to insert.
	 *
	 * @return Url
	 */
	public function save( Url $url ): Url;

	/**
	 * Update the row matching `$url->id()`. No-op if id is null.
	 *
	 * @param Url $url URL to update.
	 *
	 * @return Url
	 */
	public function update( Url $url ): Url;

	/**
	 * Find a URL by primary key.
	 *
	 * @param int $id URL id.
	 *
	 * @return Url|null
	 */
	public function find_by_id( int $id ): ?Url;

	/**
	 * List all URLs, ordered by name ascending.
	 *
	 * @return array<int, Url>
	 */
	public function find_all(): array;

	/**
	 * List URLs assigned to a given project.
	 *
	 * @param int $project_id Project id.
	 *
	 * @return array<int, Url>
	 */
	public function find_by_project_id( int $project_id ): array;

	/**
	 * List URLs that have no owning project (`project_id IS NULL`).
	 *
	 * @return array<int, Url>
	 */
	public function find_unassigned(): array;

	/**
	 * List URLs flagged as participating in scheduled runs.
	 *
	 * @return array<int, Url>
	 */
	public function find_enabled(): array;

	/**
	 * Delete a URL by primary key.
	 *
	 * @param int $id URL id.
	 *
	 * @return void
	 */
	public function delete( int $id ): void;

	/**
	 * Find a URL by its normalized address.
	 *
	 * @param string $url Normalized URL string.
	 *
	 * @return Url|null
	 */
	public function find_by_url( string $url ): ?Url;

	/**
	 * Paginated list-with-search.
	 *
	 * @param int    $page     1-indexed page number.
	 * @param int    $per_page Page size.
	 * @param string $search   Substring matched against url and name (LIKE; wildcards escaped).
	 *
	 * @return array<int, Url>
	 */
	public function find_paginated( int $page, int $per_page, string $search = '' ): array;

	/**
	 * Count rows matching an optional search.
	 *
	 * @param string $search Substring matched against url and name (LIKE; wildcards escaped).
	 *
	 * @return int
	 */
	public function count_for_search( string $search = '' ): int;
}
