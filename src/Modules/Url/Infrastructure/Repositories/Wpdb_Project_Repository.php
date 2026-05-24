<?php
/**
 * Project repository backed by `$wpdb`.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Database\Schema;
use LEAStudios\SiteAudit\Database\Wpdb_Repository_Base;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Project_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Project_Name;
use LEAStudios\SiteAudit\Shared\Datetime_Util;

/**
 * Implements {@see Project_Repository_Interface} on top of `$wpdb`.
 *
 * The fully prefixed table name is computed once in the constructor via
 * {@see Schema::table()}. Interpolating it back into queries is safe because
 * {@see Schema::TABLE_PROJECTS} is a hard-coded class constant and `$wpdb->prefix`
 * is trusted; all caller-supplied data goes through `$wpdb->prepare()` placeholders.
 */
final class Wpdb_Project_Repository extends Wpdb_Repository_Base implements Project_Repository_Interface {

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $wpdb Optional `$wpdb` override (mostly for tests).
	 */
	public function __construct( ?\wpdb $wpdb = null ) {
		parent::__construct( $wpdb, Schema::TABLE_PROJECTS );
	}

	/**
	 * Persist a new project. Assigns the auto-increment id to the model.
	 *
	 * @param Project $project Project to insert.
	 *
	 * @return Project
	 */
	public function save( Project $project ): Project {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->insert(
			$this->table,
			[
				'name'        => $project->name()->value(),
				'description' => $project->description(),
				'created_at'  => $project->created_at()->format( 'Y-m-d H:i:s' ),
				'updated_at'  => $project->updated_at()->format( 'Y-m-d H:i:s' ),
			],
			[ '%s', '%s', '%s', '%s' ]
		);

		$insert_id = (int) $this->wpdb->insert_id;
		if ( $insert_id > 0 ) {
			$project->set_id( $insert_id );
		}

		return $project;
	}

	/**
	 * Update the row matching `$project->id()`. No-op if id is null.
	 *
	 * @param Project $project Project to update.
	 *
	 * @return Project
	 */
	public function update( Project $project ): Project {
		$id = $project->id();
		if ( null === $id ) {
			return $project;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update(
			$this->table,
			[
				'name'        => $project->name()->value(),
				'description' => $project->description(),
				'updated_at'  => $project->updated_at()->format( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return $project;
	}

	/**
	 * Find a project by primary key.
	 *
	 * @param int $id Project id.
	 *
	 * @return Project|null
	 */
	public function find_by_id( int $id ): ?Project {
		$sql = $this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Find a project by exact name match.
	 *
	 * @param string $name Project name.
	 *
	 * @return Project|null
	 */
	public function find_by_name( string $name ): ?Project {
		$sql = $this->wpdb->prepare( 'SELECT * FROM %i WHERE name = %s', $this->table, $name );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * List all projects, ordered by name ascending.
	 *
	 * @return array<int, Project>
	 */
	public function find_all(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT * FROM %i ORDER BY name ASC', $this->table ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$projects = [];
		foreach ( $rows as $row ) {
			$projects[] = $this->hydrate( $row );
		}

		return $projects;
	}

	/**
	 * Delete a project by primary key, cascading to every dependent row.
	 *
	 * Walks the URL → audit/issues/comparisons/notifications graph for
	 * each URL in the project, then removes email subscriptions and the
	 * project row itself. Cross-table referential integrity is enforced
	 * in PHP because dbDelta strips foreign-key declarations.
	 *
	 * @param int $id Project id.
	 *
	 * @return void
	 */
	public function delete( int $id ): void {
		$urls_table              = Schema::table( Schema::TABLE_URLS );
		$audits_table            = Schema::table( Schema::TABLE_AUDITS );
		$issues_table            = Schema::table( Schema::TABLE_ISSUES );
		$audit_comparisons_table = Schema::table( Schema::TABLE_AUDIT_COMPARISONS );
		$notifications_table     = Schema::table( Schema::TABLE_NOTIFICATIONS );
		$subscriptions_table     = Schema::table( Schema::TABLE_EMAIL_SUBSCRIPTIONS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( 'START TRANSACTION' );

		// Delete every audit-derived row that belongs to URLs in this project.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( $this->wpdb->prepare( 'DELETE i FROM %i AS i INNER JOIN %i AS a ON i.audit_id = a.id INNER JOIN %i AS u ON a.url_id = u.id WHERE u.project_id = %d', $issues_table, $audits_table, $urls_table, $id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( $this->wpdb->prepare( 'DELETE c FROM %i AS c INNER JOIN %i AS a ON c.current_audit_id = a.id OR c.previous_audit_id = a.id INNER JOIN %i AS u ON a.url_id = u.id WHERE u.project_id = %d', $audit_comparisons_table, $audits_table, $urls_table, $id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( $this->wpdb->prepare( 'DELETE n FROM %i AS n INNER JOIN %i AS u ON n.url_id = u.id WHERE u.project_id = %d', $notifications_table, $urls_table, $id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( $this->wpdb->prepare( 'DELETE a FROM %i AS a INNER JOIN %i AS u ON a.url_id = u.id WHERE u.project_id = %d', $audits_table, $urls_table, $id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->delete( $urls_table, [ 'project_id' => $id ], [ '%d' ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->delete( $subscriptions_table, [ 'project_id' => $id ], [ '%d' ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( 'COMMIT' );
	}

	/**
	 * Hydrate a row into a `Project` model.
	 *
	 * @param array<string, mixed> $row Associative row from `$wpdb`.
	 *
	 * @return Project
	 */
	private function hydrate( array $row ): Project {
		return new Project(
			(int) $row['id'],
			new Project_Name( (string) $row['name'] ),
			null !== $row['description'] ? (string) $row['description'] : null,
			Datetime_Util::from_mysql( (string) $row['created_at'] ),
			Datetime_Util::from_mysql( (string) $row['updated_at'] ),
		);
	}
}
