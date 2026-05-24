<?php
/**
 * Audit comparison repository backed by `$wpdb`.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Database\Schema;
use LEAStudios\SiteAudit\Database\Wpdb_Repository_Base;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit_Comparison;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Comparison_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Score_Delta;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Trend;
use LEAStudios\SiteAudit\Shared\Datetime_Util;

/**
 * Implements {@see Audit_Comparison_Repository_Interface} on top of `$wpdb`.
 *
 * `find_by_url_id()` joins to the audits table to filter on the current audit's
 * URL ownership.
 */
final class Wpdb_Audit_Comparison_Repository extends Wpdb_Repository_Base implements Audit_Comparison_Repository_Interface {

	/**
	 * Fully prefixed audits table name (used for the URL join).
	 *
	 * @var string
	 */
	private string $audits_table;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $wpdb Optional `$wpdb` override (mostly for tests).
	 */
	public function __construct( ?\wpdb $wpdb = null ) {
		parent::__construct( $wpdb, Schema::TABLE_AUDIT_COMPARISONS );
		$this->audits_table = Schema::table( Schema::TABLE_AUDITS );
	}

	/**
	 * Persist a new comparison.
	 *
	 * @param Audit_Comparison $comparison Comparison to insert.
	 *
	 * @return Audit_Comparison
	 */
	public function save( Audit_Comparison $comparison ): Audit_Comparison {
		$data    = [
			'current_audit_id'        => $comparison->current_audit_id(),
			'previous_audit_id'       => $comparison->previous_audit_id(),
			'score_delta'             => $comparison->score_delta()->value(),
			'new_issues_count'        => $comparison->new_issues_count(),
			'resolved_issues_count'   => $comparison->resolved_issues_count(),
			'persistent_issues_count' => $comparison->persistent_issues_count(),
			'trend'                   => $comparison->trend()->value,
			'created_at'              => $comparison->created_at()->format( 'Y-m-d H:i:s' ),
		];
		$formats = [ '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->insert( $this->table, $data, $formats );

		$insert_id = (int) $this->wpdb->insert_id;
		if ( $insert_id > 0 ) {
			$comparison->set_id( $insert_id );
		}

		return $comparison;
	}

	/**
	 * Find the comparison whose `current_audit_id` matches.
	 *
	 * @param int $current_audit_id Current audit id.
	 *
	 * @return Audit_Comparison|null
	 */
	public function find_by_current_audit_id( int $current_audit_id ): ?Audit_Comparison {
		$sql = $this->wpdb->prepare( 'SELECT * FROM %i WHERE current_audit_id = %d', $this->table, $current_audit_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * List comparisons whose current audit belongs to a given URL, newest first.
	 *
	 * @param int $url_id URL id.
	 *
	 * @return array<int, Audit_Comparison>
	 */
	public function find_by_url_id( int $url_id ): array {
		$sql = $this->wpdb->prepare(
			'SELECT ac.* FROM %i ac
			 INNER JOIN %i a ON ac.current_audit_id = a.id
			 WHERE a.url_id = %d
			 ORDER BY ac.created_at DESC',
			$this->table,
			$this->audits_table,
			$url_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$comparisons = [];
		foreach ( $rows as $row ) {
			$comparisons[] = $this->hydrate( $row );
		}

		return $comparisons;
	}

	/**
	 * Hydrate a single `$wpdb` row into an `Audit_Comparison` model.
	 *
	 * @param array<string, mixed> $row Associative row.
	 *
	 * @return Audit_Comparison
	 */
	private function hydrate( array $row ): Audit_Comparison {
		return new Audit_Comparison(
			(int) $row['id'],
			(int) $row['current_audit_id'],
			(int) $row['previous_audit_id'],
			new Score_Delta( (int) $row['score_delta'] ),
			(int) $row['new_issues_count'],
			(int) $row['resolved_issues_count'],
			(int) $row['persistent_issues_count'],
			Trend::from( (string) $row['trend'] ),
			Datetime_Util::from_mysql( (string) $row['created_at'] ),
		);
	}
}
