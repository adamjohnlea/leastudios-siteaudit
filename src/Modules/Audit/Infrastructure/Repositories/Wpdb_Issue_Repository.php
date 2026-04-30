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
 * `save_many()` issues a single multi-value INSERT, which InnoDB makes
 * atomic at the statement level — all rows commit together or none do.
 * That preserves the original "no half-saved batches" guarantee without
 * an explicit transaction, which would otherwise collide with the outer
 * transaction WP_UnitTestCase opens per test (a nested `START TRANSACTION`
 * silently commits the outer one on MySQL).
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
	 * Persist many issues atomically via a single multi-value INSERT.
	 *
	 * InnoDB statement atomicity guarantees that either every row in the batch
	 * lands or none of them do, with no nested-transaction needed. Returned
	 * `Issue` instances do **not** have ids assigned — InnoDB's default
	 * `innodb_autoinc_lock_mode = 2` does not promise contiguous auto-increment
	 * values for batch inserts, so back-filling ids would be unsound. The
	 * production caller (`Audit_Service`) does not need the ids; downstream
	 * reads use {@see find_by_audit_id()}.
	 *
	 * @param array<int, Issue> $issues Issues to insert.
	 *
	 * @return array<int, Issue>
	 */
	public function save_many( array $issues ): array {
		if ( [] === $issues ) {
			return [];
		}

		$row_placeholders = [];
		$args             = [];

		foreach ( $issues as $issue ) {
			$title            = $issue->title();
			$element_selector = $issue->element_selector();
			$help_url         = $issue->help_url();

			// Per-row placeholder layout: literal `NULL` is interpolated for null values,
			// because $wpdb->prepare() coerces null to '' for %s and 0 for %d, which
			// would silently flip nullable columns to empty strings.
			$parts = [
				'%d',                                       // audit_id.
				'%s',                                       // severity.
				'%s',                                       // category.
				null === $title ? 'NULL' : '%s',            // title.
				'%s',                                       // description.
				null === $element_selector ? 'NULL' : '%s', // element_selector.
				null === $help_url ? 'NULL' : '%s',         // help_url.
				'%s',                                       // created_at.
			];

			$row_placeholders[] = '(' . implode( ', ', $parts ) . ')';

			$args[] = $issue->audit_id();
			$args[] = $issue->severity()->value;
			$args[] = $issue->category()->value;
			if ( null !== $title ) {
				$args[] = $title;
			}
			$args[] = $issue->description();
			if ( null !== $element_selector ) {
				$args[] = $element_selector;
			}
			if ( null !== $help_url ) {
				$args[] = $help_url;
			}
			$args[] = $issue->created_at()->format( 'Y-m-d H:i:s' );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and per-row placeholder shape are hard-coded; user values flow through prepare().
		$sql = "INSERT INTO `{$this->table}` (audit_id, severity, category, title, description, element_selector, help_url, created_at) VALUES " . implode( ', ', $row_placeholders );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( $this->wpdb->prepare( $sql, $args ) );

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
			\LEAStudios\SiteAudit\Shared\Datetime_Util::from_mysql( (string) $row['created_at'] ),
			null !== $row['title'] ? (string) $row['title'] : null,
		);
	}
}
