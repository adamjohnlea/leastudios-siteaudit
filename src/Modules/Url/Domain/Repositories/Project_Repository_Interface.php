<?php
/**
 * Project repository contract.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Domain\Repositories
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Domain\Repositories;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project;

/**
 * Persistence boundary for `Project` aggregates.
 *
 * Implementations are expected to mutate the passed model in place when
 * assigning auto-increment ids on `save()`, then return the same instance
 * so call sites can chain.
 */
interface Project_Repository_Interface {

	/**
	 * Persist a new project. Assigns the auto-increment id to the model.
	 *
	 * @param Project $project Project to insert.
	 *
	 * @return Project
	 */
	public function save( Project $project ): Project;

	/**
	 * Update the row matching `$project->id()`. No-op if id is null.
	 *
	 * @param Project $project Project to update.
	 *
	 * @return Project
	 */
	public function update( Project $project ): Project;

	/**
	 * Find a project by primary key.
	 *
	 * @param int $id Project id.
	 *
	 * @return Project|null
	 */
	public function find_by_id( int $id ): ?Project;

	/**
	 * Find a project by exact name match.
	 *
	 * @param string $name Project name.
	 *
	 * @return Project|null
	 */
	public function find_by_name( string $name ): ?Project;

	/**
	 * List all projects, ordered by name ascending.
	 *
	 * @return array<int, Project>
	 */
	public function find_all(): array;

	/**
	 * Delete a project by primary key.
	 *
	 * @param int $id Project id.
	 *
	 * @return void
	 */
	public function delete( int $id ): void;
}
