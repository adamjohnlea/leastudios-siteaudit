<?php
/**
 * UTC-anchored datetime helpers.
 *
 * @package LEAStudios\SiteAudit\Shared
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Shared;

defined( 'ABSPATH' ) || exit;

/**
 * Centralises every "now" timestamp the plugin records and every datetime
 * read back from MySQL. Always operates in UTC so the storage format is
 * stable across timezone-misconfigured PHP runtimes, which lets WordPress's
 * own `mysql2date()` / `wp_date()` helpers convert correctly to the user's
 * configured display timezone.
 *
 * Why a helper instead of `new \DateTimeImmutable()`:
 *
 * The bare constructor uses PHP's `date.timezone` ini setting, which differs
 * between Herd local dev (often the OS timezone) and production servers
 * (usually UTC). Mixing the two writes inconsistent strings to MySQL and
 * causes the dashboard to display stale or future-shifted timestamps.
 */
final class Datetime_Util {

	/**
	 * Current time in UTC.
	 *
	 * @return \DateTimeImmutable
	 */
	public static function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Hydrate a MySQL datetime string read from one of our tables. The
	 * caller's contract is that everything stored by this plugin is UTC.
	 *
	 * @param string $mysql_datetime e.g. "2026-04-30 00:54:30".
	 *
	 * @return \DateTimeImmutable
	 */
	public static function from_mysql( string $mysql_datetime ): \DateTimeImmutable {
		return new \DateTimeImmutable( $mysql_datetime, new \DateTimeZone( 'UTC' ) );
	}
}
