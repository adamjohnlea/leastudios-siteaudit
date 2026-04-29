<?php
/**
 * Wpdb_Project_Repository integration tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Repositories;

use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Project_Name;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Project_Repository;
use LEAStudios\Tests\TestCase;

final class Wpdb_Project_Repository_Test extends TestCase {

	private Wpdb_Project_Repository $repository;

	public function set_up(): void {
		parent::set_up();
		$this->repository = new Wpdb_Project_Repository();
	}

	public function test_save_assigns_an_id(): void {
		$project = $this->repository->save( $this->new_project( 'Acme' ) );

		$this->assertNotNull( $project->id() );
		$this->assertSame( 'Acme', $project->name()->value() );
	}

	public function test_find_by_id_round_trips_a_saved_project(): void {
		$saved = $this->repository->save( $this->new_project( 'Acme', 'Marketing site' ) );

		$found = $this->repository->find_by_id( (int) $saved->id() );

		$this->assertNotNull( $found );
		$this->assertSame( 'Acme', $found->name()->value() );
		$this->assertSame( 'Marketing site', $found->description() );
	}

	public function test_find_by_id_returns_null_when_missing(): void {
		$this->assertNull( $this->repository->find_by_id( 99999 ) );
	}

	public function test_find_by_name_returns_match_or_null(): void {
		$this->repository->save( $this->new_project( 'Beta' ) );

		$this->assertNotNull( $this->repository->find_by_name( 'Beta' ) );
		$this->assertNull( $this->repository->find_by_name( 'Missing' ) );
	}

	public function test_find_all_returns_projects_sorted_by_name(): void {
		$this->repository->save( $this->new_project( 'Charlie' ) );
		$this->repository->save( $this->new_project( 'Alpha' ) );
		$this->repository->save( $this->new_project( 'Bravo' ) );

		$names = array_map(
			static fn( Project $p ): string => $p->name()->value(),
			$this->repository->find_all()
		);

		$this->assertSame( [ 'Alpha', 'Bravo', 'Charlie' ], $names );
	}

	public function test_update_changes_name_and_description(): void {
		$project = $this->repository->save( $this->new_project( 'Old' ) );

		$project->set_name( new Project_Name( 'New' ) );
		$project->set_description( 'Renamed' );
		$project->set_updated_at( new \DateTimeImmutable() );

		$this->repository->update( $project );

		$reloaded = $this->repository->find_by_id( (int) $project->id() );

		$this->assertNotNull( $reloaded );
		$this->assertSame( 'New', $reloaded->name()->value() );
		$this->assertSame( 'Renamed', $reloaded->description() );
	}

	public function test_delete_removes_the_project(): void {
		$project = $this->repository->save( $this->new_project( 'Doomed' ) );
		$id      = (int) $project->id();

		$this->repository->delete( $id );

		$this->assertNull( $this->repository->find_by_id( $id ) );
	}

	private function new_project( string $name, ?string $description = null ): Project {
		$now = new \DateTimeImmutable();

		return new Project(
			null,
			new Project_Name( $name ),
			$description,
			$now,
			$now,
		);
	}
}
