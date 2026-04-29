<?php
/**
 * Database migration handler.
 *
 * @package LEAStudios\SiteAudit\Database
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Versioned schema upgrades.
 *
 * Schema version 1 = initial dbDelta of every plugin table. Future schema
 * changes bump SCHEMA_VERSION and add a branch in {@see migrate()} guarded
 * by `$from_version < N`.
 */
class Migration {

	private const SCHEMA_VERSION_KEY = 'leastudios_siteaudit_db_version';
	private const SCHEMA_VERSION     = 1;

	/**
	 * Run migrations if the stored version is behind.
	 *
	 * @return void
	 */
	public function maybe_migrate(): void {
		$current = (int) get_option( self::SCHEMA_VERSION_KEY, 0 );

		if ( $current >= self::SCHEMA_VERSION ) {
			return;
		}

		$this->migrate( $current );
		update_option( self::SCHEMA_VERSION_KEY, self::SCHEMA_VERSION );
	}

	/**
	 * Run migrations from the given version up to SCHEMA_VERSION.
	 *
	 * @param int $from_version Currently stored schema version.
	 * @return void
	 */
	private function migrate( int $from_version ): void {
		if ( $from_version < 1 ) {
			Schema::create();
		}
	}
}
