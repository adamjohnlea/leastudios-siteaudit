<?php
/**
 * URL repository backed by `$wpdb`.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Database\Schema;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Url_Address;

/**
 * Implements {@see Url_Repository_Interface} on top of `$wpdb`.
 *
 * The fully prefixed table name comes from {@see Schema::table()}; interpolating
 * it back into queries is safe because {@see Schema::TABLE_URLS} is a hard-coded
 * class constant and `$wpdb->prefix` is trusted. All caller-supplied data flows
 * through `$wpdb->prepare()` placeholders, and `$wpdb->esc_like()` escapes
 * wildcards before LIKE comparisons.
 */
final class Wpdb_Url_Repository implements Url_Repository_Interface {

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
		$this->table = Schema::table( Schema::TABLE_URLS );
	}

	/**
	 * Persist a new URL. Assigns the auto-increment id to the model.
	 *
	 * @param Url $url URL to insert.
	 *
	 * @return Url
	 */
	public function save( Url $url ): Url {
		$data    = $this->to_row( $url );
		$formats = $this->row_formats( $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->insert( $this->table, $data, $formats );

		$insert_id = (int) $this->wpdb->insert_id;
		if ( $insert_id > 0 ) {
			$url->set_id( $insert_id );
		}

		return $url;
	}

	/**
	 * Update the row matching `$url->id()`. No-op if id is null.
	 *
	 * @param Url $url URL to update.
	 *
	 * @return Url
	 */
	public function update( Url $url ): Url {
		$id = $url->id();
		if ( null === $id ) {
			return $url;
		}

		$data = $this->to_row( $url );
		unset( $data['created_at'] );

		$formats = $this->row_formats( $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update( $this->table, $data, [ 'id' => $id ], $formats, [ '%d' ] );

		return $url;
	}

	/**
	 * Find a URL by primary key.
	 *
	 * @param int $id URL id.
	 *
	 * @return Url|null
	 */
	public function find_by_id( int $id ): ?Url {
		$sql = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * List all URLs, ordered by name ascending.
	 *
	 * @return array<int, Url>
	 */
	public function find_all(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results( "SELECT * FROM `{$this->table}` ORDER BY name ASC", ARRAY_A );

		return $this->hydrate_many( $rows );
	}

	/**
	 * List URLs assigned to a given project.
	 *
	 * @param int $project_id Project id.
	 *
	 * @return array<int, Url>
	 */
	public function find_by_project_id( int $project_id ): array {
		$sql = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE project_id = %d ORDER BY name ASC", $project_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return $this->hydrate_many( $rows );
	}

	/**
	 * List URLs that have no owning project (`project_id IS NULL`).
	 *
	 * @return array<int, Url>
	 */
	public function find_unassigned(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results( "SELECT * FROM `{$this->table}` WHERE project_id IS NULL ORDER BY name ASC", ARRAY_A );

		return $this->hydrate_many( $rows );
	}

	/**
	 * List URLs flagged as participating in scheduled runs.
	 *
	 * @return array<int, Url>
	 */
	public function find_enabled(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results( "SELECT * FROM `{$this->table}` WHERE enabled = 1 ORDER BY name ASC", ARRAY_A );

		return $this->hydrate_many( $rows );
	}

	/**
	 * Delete a URL by primary key.
	 *
	 * @param int $id URL id.
	 *
	 * @return void
	 */
	public function delete( int $id ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );
	}

	/**
	 * Find a URL by its normalized address.
	 *
	 * @param string $url Normalized URL string.
	 *
	 * @return Url|null
	 */
	public function find_by_url( string $url ): ?Url {
		$sql = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE url = %s", $url ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Paginated list-with-search.
	 *
	 * @param int    $page     1-indexed page number.
	 * @param int    $per_page Page size.
	 * @param string $search   Substring matched against url and name (LIKE; wildcards escaped).
	 *
	 * @return array<int, Url>
	 */
	public function find_paginated( int $page, int $per_page, string $search = '' ): array {
		$offset = max( 0, ( $page - 1 ) * $per_page );

		if ( '' !== $search ) {
			$like = '%' . $this->wpdb->esc_like( $search ) . '%';
			$sql  = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE (url LIKE %s OR name LIKE %s) ORDER BY name ASC LIMIT %d OFFSET %d", $like, $like, $per_page, $offset ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` ORDER BY name ASC LIMIT %d OFFSET %d", $per_page, $offset ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return $this->hydrate_many( $rows );
	}

	/**
	 * Count rows matching an optional search.
	 *
	 * @param string $search Substring matched against url and name (LIKE; wildcards escaped).
	 *
	 * @return int
	 */
	public function count_for_search( string $search = '' ): int {
		if ( '' !== $search ) {
			$like = '%' . $this->wpdb->esc_like( $search ) . '%';
			$sql  = $this->wpdb->prepare( "SELECT COUNT(*) FROM `{$this->table}` WHERE url LIKE %s OR name LIKE %s", $like, $like ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = "SELECT COUNT(*) FROM `{$this->table}`";
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $this->wpdb->get_var( $sql );

		return (int) $count;
	}

	/**
	 * Convert a `Url` model to its associative wpdb row representation.
	 *
	 * @param Url $url Model.
	 *
	 * @return array<string, mixed>
	 */
	private function to_row( Url $url ): array {
		return [
			'project_id'            => $url->project_id(),
			'url'                   => $url->url()->value(),
			'name'                  => $url->name(),
			'audit_frequency'       => $url->audit_frequency()->value,
			'audit_strategy'        => $url->audit_strategy()->value,
			'enabled'               => $url->is_enabled() ? 1 : 0,
			'alerts_enabled'        => $url->alerts_enabled() ? 1 : 0,
			'alert_threshold_score' => $url->alert_threshold_score(),
			'alert_threshold_drop'  => $url->alert_threshold_drop(),
			'last_audited_at'       => null !== $url->last_audited_at() ? $url->last_audited_at()->format( 'Y-m-d H:i:s' ) : null,
			'created_at'            => $url->created_at()->format( 'Y-m-d H:i:s' ),
			'updated_at'            => $url->updated_at()->format( 'Y-m-d H:i:s' ),
		];
	}

	/**
	 * Build the `$wpdb`-format placeholder list for a row, in column order.
	 *
	 * @param array<string, mixed> $row Row keyed by column name.
	 *
	 * @return array<int, string>
	 */
	private function row_formats( array $row ): array {
		$map = [
			'project_id'            => '%d',
			'url'                   => '%s',
			'name'                  => '%s',
			'audit_frequency'       => '%s',
			'audit_strategy'        => '%s',
			'enabled'               => '%d',
			'alerts_enabled'        => '%d',
			'alert_threshold_score' => '%d',
			'alert_threshold_drop'  => '%d',
			'last_audited_at'       => '%s',
			'created_at'            => '%s',
			'updated_at'            => '%s',
		];

		$formats = [];
		foreach ( array_keys( $row ) as $column ) {
			$formats[] = $map[ $column ] ?? '%s';
		}

		return $formats;
	}

	/**
	 * Hydrate a list of `$wpdb` rows.
	 *
	 * @param mixed $rows Result of `$wpdb->get_results()`.
	 *
	 * @return array<int, Url>
	 */
	private function hydrate_many( $rows ): array {
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$urls = [];
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$urls[] = $this->hydrate( $row );
			}
		}

		return $urls;
	}

	/**
	 * Hydrate a single `$wpdb` row into a `Url` model.
	 *
	 * @param array<string, mixed> $row Associative row.
	 *
	 * @return Url
	 */
	private function hydrate( array $row ): Url {
		return new Url(
			(int) $row['id'],
			null !== $row['project_id'] ? (int) $row['project_id'] : null,
			new Url_Address( (string) $row['url'] ),
			null !== $row['name'] ? (string) $row['name'] : null,
			Audit_Frequency::from( (string) $row['audit_frequency'] ),
			Audit_Strategy::from( (string) $row['audit_strategy'] ),
			(bool) $row['enabled'],
			(bool) $row['alerts_enabled'],
			null !== $row['alert_threshold_score'] ? (int) $row['alert_threshold_score'] : null,
			null !== $row['alert_threshold_drop'] ? (int) $row['alert_threshold_drop'] : null,
			null !== $row['last_audited_at'] ? \LEAStudios\SiteAudit\Shared\Datetime_Util::from_mysql( (string) $row['last_audited_at'] ) : null,
			\LEAStudios\SiteAudit\Shared\Datetime_Util::from_mysql( (string) $row['created_at'] ),
			\LEAStudios\SiteAudit\Shared\Datetime_Util::from_mysql( (string) $row['updated_at'] ),
		);
	}
}
