<?php
/**
 * Shared base class for `$wpdb`-backed repository implementations.
 *
 * @package LEAStudios\SiteAudit\Database
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the boilerplate every `Wpdb_*_Repository` was duplicating: the
 * `$wpdb` handle (with the global as a fallback for production callers
 * that don't inject), and the fully-prefixed primary table name resolved
 * once via {@see Schema::table()}.
 *
 * Subclasses keep their own constructor signature (a nullable `\wpdb`) so
 * tests can keep injecting a mock, and call up with the appropriate
 * `Schema::TABLE_*` constant. Repos that join against additional tables
 * still resolve those locally — only the primary table lives here.
 */
abstract class Wpdb_Repository_Base {

	/**
	 * WP database abstraction.
	 *
	 * @var \wpdb
	 */
	protected \wpdb $wpdb;

	/**
	 * Fully prefixed name of this repository's primary table.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Resolve the `$wpdb` handle and the primary table name once for the subclass.
	 *
	 * @param \wpdb|null $wpdb           Optional `$wpdb` override (mostly for tests).
	 * @param string     $table_constant One of the `Schema::TABLE_*` constants — the primary table this repo owns.
	 */
	protected function __construct( ?\wpdb $wpdb, string $table_constant ) {
		$this->wpdb  = $wpdb ?? $GLOBALS['wpdb'];
		$this->table = Schema::table( $table_constant );
	}
}
