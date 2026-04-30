<?php
/**
 * Project application service.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Project_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Project_Name;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;

/**
 * Application-layer orchestrator for project lifecycle operations.
 *
 * Wraps the repository with name-uniqueness checks and timestamp management
 * so controllers can hand it raw form input.
 */
final class Project_Service {

	/**
	 * Project persistence boundary.
	 *
	 * @var Project_Repository_Interface
	 */
	private Project_Repository_Interface $project_repository;

	/**
	 * Constructor.
	 *
	 * @param Project_Repository_Interface $project_repository Repository implementation.
	 */
	public function __construct( Project_Repository_Interface $project_repository ) {
		$this->project_repository = $project_repository;
	}

	/**
	 * Create and persist a new project.
	 *
	 * @param string      $name        Project name.
	 * @param string|null $description Optional description.
	 *
	 * @return Project
	 *
	 * @throws Validation_Exception When the name is invalid or already in use.
	 */
	public function create( string $name, ?string $description ): Project {
		$project_name = new Project_Name( $name );

		$existing = $this->project_repository->find_by_name( $project_name->value() );
		if ( null !== $existing ) {
			throw new Validation_Exception( 'A project with this name already exists' );
		}

		$now = \LEAStudios\SiteAudit\Shared\Datetime_Util::now();

		$project = new Project(
			null,
			$project_name,
			'' !== $description && null !== $description ? $description : null,
			$now,
			$now,
		);

		return $this->project_repository->save( $project );
	}

	/**
	 * Update an existing project. Pass `null` for fields that should remain unchanged.
	 *
	 * @param int         $id          Project id.
	 * @param string|null $name        New name, or null to leave unchanged.
	 * @param string|null $description New description, or null to leave unchanged. Pass an empty string to clear.
	 *
	 * @return Project
	 *
	 * @throws Validation_Exception When the project does not exist or the new name conflicts.
	 */
	public function update( int $id, ?string $name, ?string $description ): Project {
		$project = $this->project_repository->find_by_id( $id );

		if ( null === $project ) {
			throw new Validation_Exception( 'Project not found' );
		}

		if ( null !== $name ) {
			$project_name = new Project_Name( $name );
			$existing     = $this->project_repository->find_by_name( $project_name->value() );
			if ( null !== $existing && $existing->id() !== $id ) {
				throw new Validation_Exception( 'A project with this name already exists' );
			}
			$project->set_name( $project_name );
		}

		if ( null !== $description ) {
			$project->set_description( '' !== $description ? $description : null );
		}

		$project->set_updated_at( \LEAStudios\SiteAudit\Shared\Datetime_Util::now() );

		return $this->project_repository->update( $project );
	}

	/**
	 * Delete a project.
	 *
	 * @param int $id Project id.
	 *
	 * @return void
	 *
	 * @throws Validation_Exception When the project does not exist.
	 */
	public function delete( int $id ): void {
		$project = $this->project_repository->find_by_id( $id );

		if ( null === $project ) {
			throw new Validation_Exception( 'Project not found' );
		}

		$this->project_repository->delete( $id );
	}

	/**
	 * Find a project by id.
	 *
	 * @param int $id Project id.
	 *
	 * @return Project|null
	 */
	public function find_by_id( int $id ): ?Project {
		return $this->project_repository->find_by_id( $id );
	}

	/**
	 * List all projects, ordered by name ascending.
	 *
	 * @return array<int, Project>
	 */
	public function find_all(): array {
		return $this->project_repository->find_all();
	}
}
