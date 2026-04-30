<?php
/**
 * Audit repository backed by `$wpdb`.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Database\Schema;
use LEAStudios\SiteAudit\Database\Wpdb_Repository_Base;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Shared\Datetime_Util;

/**
 * Implements {@see Audit_Repository_Interface} on top of `$wpdb`.
 *
 * The fully prefixed table name comes from {@see Schema::table()}; interpolating
 * it back into queries is safe because {@see Schema::TABLE_AUDITS} is a hard-coded
 * class constant and `$wpdb->prefix` is trusted. All caller-supplied data flows
 * through `$wpdb->prepare()` placeholders.
 */
final class Wpdb_Audit_Repository extends Wpdb_Repository_Base implements Audit_Repository_Interface {

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $wpdb Optional `$wpdb` override (mostly for tests).
	 */
	public function __construct( ?\wpdb $wpdb = null ) {
		parent::__construct( $wpdb, Schema::TABLE_AUDITS );
	}

	/**
	 * Persist a new audit. Assigns the auto-increment id to the model.
	 *
	 * @param Audit $audit Audit to insert.
	 *
	 * @return Audit
	 */
	public function save( Audit $audit ): Audit {
		$data    = [
			'url_id'        => $audit->url_id(),
			'score'         => $audit->score()->value(),
			'status'        => $audit->status()->value,
			'strategy'      => $audit->strategy()->value,
			'audit_date'    => $audit->audit_date()->format( 'Y-m-d H:i:s' ),
			'raw_response'  => $audit->raw_response(),
			'error_message' => $audit->error_message(),
			'retry_count'   => $audit->retry_count(),
			'created_at'    => $audit->created_at()->format( 'Y-m-d H:i:s' ),
		];
		$formats = [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->insert( $this->table, $data, $formats );

		$insert_id = (int) $this->wpdb->insert_id;
		if ( $insert_id > 0 ) {
			$audit->set_id( $insert_id );
		}

		return $audit;
	}

	/**
	 * Update mutable fields on the row matching `$audit->id()`. No-op if id is null.
	 *
	 * @param Audit $audit Audit to update.
	 *
	 * @return Audit
	 */
	public function update( Audit $audit ): Audit {
		$id = $audit->id();
		if ( null === $id ) {
			return $audit;
		}

		$data    = [
			'score'         => $audit->score()->value(),
			'status'        => $audit->status()->value,
			'raw_response'  => $audit->raw_response(),
			'error_message' => $audit->error_message(),
			'retry_count'   => $audit->retry_count(),
		];
		$formats = [ '%d', '%s', '%s', '%s', '%d' ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update( $this->table, $data, [ 'id' => $id ], $formats, [ '%d' ] );

		return $audit;
	}

	/**
	 * Find an audit by primary key.
	 *
	 * @param int $id Audit id.
	 *
	 * @return Audit|null
	 */
	public function find_by_id( int $id ): ?Audit {
		$sql = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * List audits for a URL, ordered by audit_date DESC (newest first).
	 *
	 * @param int $url_id URL id.
	 *
	 * @return array<int, Audit>
	 */
	public function find_by_url_id( int $url_id ): array {
		$sql = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE url_id = %d ORDER BY audit_date DESC", $url_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return $this->hydrate_many( $rows );
	}

	/**
	 * Most recent audit for a URL regardless of strategy or status.
	 *
	 * @param int $url_id URL id.
	 *
	 * @return Audit|null
	 */
	public function find_latest_by_url_id( int $url_id ): ?Audit {
		$sql = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE url_id = %d ORDER BY audit_date DESC LIMIT 1", $url_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Most recent COMPLETED audit for a (URL, strategy) tuple.
	 *
	 * @param int          $url_id   URL id.
	 * @param Run_Strategy $strategy Device profile.
	 *
	 * @return Audit|null
	 */
	public function find_latest_completed_by_url_id_and_strategy( int $url_id, Run_Strategy $strategy ): ?Audit {
		$sql = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE url_id = %d AND strategy = %s AND status = %s ORDER BY audit_date DESC LIMIT 1", $url_id, $strategy->value, Audit_Status::COMPLETED->value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * Latest completed score for each strategy across the given URL ids.
	 *
	 * @param array<int, int> $url_ids URL ids.
	 *
	 * @return array<int, array<string, int>>
	 */
	public function find_latest_scores_by_url_ids( array $url_ids ): array {
		if ( [] === $url_ids ) {
			return [];
		}

		$ids          = array_values( array_map( 'intval', $url_ids ) );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$completed = Audit_Status::COMPLETED->value;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare(
			"SELECT a.url_id, a.strategy, a.score
			 FROM `{$this->table}` a
			 INNER JOIN (
				 SELECT url_id, strategy, MAX(id) AS max_id
				 FROM `{$this->table}`
				 WHERE status = %s AND url_id IN ({$placeholders})
				 GROUP BY url_id, strategy
			 ) latest ON a.id = latest.max_id",
			array_merge( [ $completed ], $ids )
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		$result = [];
		if ( ! is_array( $rows ) ) {
			return $result;
		}

		foreach ( $rows as $row ) {
			$url_id                                = (int) $row['url_id'];
			$result[ $url_id ][ $row['strategy'] ] = (int) $row['score'];
		}

		return $result;
	}

	/**
	 * Hydrate a list of `$wpdb` rows.
	 *
	 * @param mixed $rows Result of `$wpdb->get_results()`.
	 *
	 * @return array<int, Audit>
	 */
	private function hydrate_many( $rows ): array {
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$audits = [];
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$audits[] = $this->hydrate( $row );
			}
		}

		return $audits;
	}

	/**
	 * Hydrate a single `$wpdb` row into an `Audit` model.
	 *
	 * @param array<string, mixed> $row Associative row.
	 *
	 * @return Audit
	 */
	private function hydrate( array $row ): Audit {
		return new Audit(
			(int) $row['id'],
			(int) $row['url_id'],
			new Accessibility_Score( (int) $row['score'] ),
			Audit_Status::from( (string) $row['status'] ),
			Run_Strategy::from( (string) $row['strategy'] ),
			Datetime_Util::from_mysql( (string) $row['audit_date'] ),
			null !== $row['raw_response'] ? (string) $row['raw_response'] : null,
			null !== $row['error_message'] ? (string) $row['error_message'] : null,
			(int) $row['retry_count'],
			Datetime_Util::from_mysql( (string) $row['created_at'] ),
		);
	}
}
