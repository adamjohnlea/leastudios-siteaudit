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
final class Wpdb_Project_Repository implements Project_Repository_Interface {

	/**
	 * WP database abstraction.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Fully prefixed table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $wpdb Optional `$wpdb` override (mostly for tests).
	 */
	public function __construct( ?\wpdb $wpdb = null ) {
		$this->wpdb  = $wpdb ?? $GLOBALS['wpdb'];
		$this->table = Schema::table( Schema::TABLE_PROJECTS );
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
		$sql = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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
		$sql = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE name = %s", $name ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results( "SELECT * FROM `{$this->table}` ORDER BY name ASC", ARRAY_A );

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( 'START TRANSACTION' );

		// Delete every audit-derived row that belongs to URLs in this project.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( $this->wpdb->prepare( "DELETE i FROM `{$issues_table}` AS i INNER JOIN `{$audits_table}` AS a ON i.audit_id = a.id INNER JOIN `{$urls_table}` AS u ON a.url_id = u.id WHERE u.project_id = %d", $id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( $this->wpdb->prepare( "DELETE c FROM `{$audit_comparisons_table}` AS c INNER JOIN `{$audits_table}` AS a ON c.current_audit_id = a.id OR c.previous_audit_id = a.id INNER JOIN `{$urls_table}` AS u ON a.url_id = u.id WHERE u.project_id = %d", $id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( $this->wpdb->prepare( "DELETE n FROM `{$notifications_table}` AS n INNER JOIN `{$urls_table}` AS u ON n.url_id = u.id WHERE u.project_id = %d", $id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( $this->wpdb->prepare( "DELETE a FROM `{$audits_table}` AS a INNER JOIN `{$urls_table}` AS u ON a.url_id = u.id WHERE u.project_id = %d", $id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->delete( $urls_table, [ 'project_id' => $id ], [ '%d' ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->delete( $subscriptions_table, [ 'project_id' => $id ], [ '%d' ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
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
