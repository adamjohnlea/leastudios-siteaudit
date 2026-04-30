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

	/**
	 * Convert a stored UTC datetime to a string formatted in the WordPress
	 * display timezone (the `timezone_string` site option, e.g.
	 * "America/Boise"). Returns an empty string for null input.
	 *
	 * Why not `mysql2date()`: that helper interprets its input string as
	 * already being in `wp_timezone()` and only re-formats the wall clock,
	 * which silently re-labels UTC values as local time without applying any
	 * offset. `get_date_from_gmt()` is the canonical "input is UTC, output
	 * is WP-timezone" conversion.
	 *
	 * @param \DateTimeImmutable|null $utc    UTC datetime (typically from `from_mysql`).
	 * @param string                  $format PHP date format string.
	 *
	 * @return string
	 */
	public static function format_for_display( ?\DateTimeImmutable $utc, string $format ): string {
		if ( null === $utc ) {
			return '';
		}
		return get_date_from_gmt( $utc->format( 'Y-m-d H:i:s' ), $format );
	}
}
