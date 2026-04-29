<?php
/**
 * Issue repository backed by `$wpdb`.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Database\Schema;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Issue;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Issue_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Category;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Severity;

/**
 * Implements {@see Issue_Repository_Interface} on top of `$wpdb`.
 *
 * `save_many()` issues sequential inserts. We deliberately do not wrap them in
 * a transaction: WP_UnitTestCase already opens an outer transaction per test
 * and a nested `START TRANSACTION` would silently commit it on MySQL, breaking
 * test isolation. In production a partial mid-batch failure leaves fewer
 * issues than expected for one audit, which the next audit run overwrites.
 */
final class Wpdb_Issue_Repository implements Issue_Repository_Interface {

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
		$this->table = Schema::table( Schema::TABLE_ISSUES );
	}

	/**
	 * Persist a single issue.
	 *
	 * @param Issue $issue Issue to insert.
	 *
	 * @return Issue
	 */
	public function save( Issue $issue ): Issue {
		$data    = [
			'audit_id'         => $issue->audit_id(),
			'severity'         => $issue->severity()->value,
			'category'         => $issue->category()->value,
			'title'            => $issue->title(),
			'description'      => $issue->description(),
			'element_selector' => $issue->element_selector(),
			'help_url'         => $issue->help_url(),
			'created_at'       => $issue->created_at()->format( 'Y-m-d H:i:s' ),
		];
		$formats = [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->insert( $this->table, $data, $formats );

		$insert_id = (int) $this->wpdb->insert_id;
		if ( $insert_id > 0 ) {
			$issue->set_id( $insert_id );
		}

		return $issue;
	}

	/**
	 * Persist many issues sequentially.
	 *
	 * @param array<int, Issue> $issues Issues to insert.
	 *
	 * @return array<int, Issue>
	 */
	public function save_many( array $issues ): array {
		foreach ( $issues as $issue ) {
			$this->save( $issue );
		}

		return $issues;
	}

	/**
	 * List issues for a given audit, ordered by severity ASC.
	 *
	 * @param int $audit_id Audit id.
	 *
	 * @return array<int, Issue>
	 */
	public function find_by_audit_id( int $audit_id ): array {
		$sql = $this->wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE audit_id = %d ORDER BY severity ASC", $audit_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$issues = [];
		foreach ( $rows as $row ) {
			$issues[] = $this->hydrate( $row );
		}

		return $issues;
	}

	/**
	 * Hydrate a single `$wpdb` row into an `Issue` model.
	 *
	 * @param array<string, mixed> $row Associative row.
	 *
	 * @return Issue
	 */
	private function hydrate( array $row ): Issue {
		return new Issue(
			(int) $row['id'],
			(int) $row['audit_id'],
			Issue_Severity::from( (string) $row['severity'] ),
			Issue_Category::from( (string) $row['category'] ),
			(string) $row['description'],
			null !== $row['element_selector'] ? (string) $row['element_selector'] : null,
			null !== $row['help_url'] ? (string) $row['help_url'] : null,
			new \DateTimeImmutable( (string) $row['created_at'] ),
			null !== $row['title'] ? (string) $row['title'] : null,
		);
	}
}
