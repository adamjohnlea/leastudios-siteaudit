<?php
/**
 * Plugin schema (table names + dbDelta SQL).
 *
 * @package LEAStudios\SiteAudit\Database
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for all custom tables.
 *
 * Schemas were ported from the source app's SQLite migrations
 * (see /Users/adamlea/Herd/beaconaudit/src/Database/Migrations/) and
 * translated to MySQL. Cross-table referential integrity is enforced
 * in PHP rather than via foreign keys, since dbDelta strips them.
 *
 * The `users` table from the source app is intentionally absent —
 * WordPress's own users table is used for `email_subscriptions.user_id`.
 */
final class Schema {

	public const TABLE_PROJECTS            = 'leastudios_siteaudit_projects';
	public const TABLE_URLS                = 'leastudios_siteaudit_urls';
	public const TABLE_AUDITS              = 'leastudios_siteaudit_audits';
	public const TABLE_ISSUES              = 'leastudios_siteaudit_issues';
	public const TABLE_AUDIT_COMPARISONS   = 'leastudios_siteaudit_audit_comparisons';
	public const TABLE_NOTIFICATIONS       = 'leastudios_siteaudit_notifications';
	public const TABLE_EMAIL_SUBSCRIPTIONS = 'leastudios_siteaudit_email_subscriptions';

	/**
	 * Get the prefixed table name for a logical table key.
	 *
	 * @param string $table One of the TABLE_* constants.
	 * @return string Fully prefixed table name.
	 */
	public static function table( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . $table;
	}

	/**
	 * Run dbDelta for every plugin table.
	 *
	 * Idempotent. Safe to call on every activation or version bump.
	 *
	 * @return void
	 */
	public static function create(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$projects            = self::table( self::TABLE_PROJECTS );
		$urls                = self::table( self::TABLE_URLS );
		$audits              = self::table( self::TABLE_AUDITS );
		$issues              = self::table( self::TABLE_ISSUES );
		$audit_comparisons   = self::table( self::TABLE_AUDIT_COMPARISONS );
		$notifications       = self::table( self::TABLE_NOTIFICATIONS );
		$email_subscriptions = self::table( self::TABLE_EMAIL_SUBSCRIPTIONS );

		$statements = [
			"CREATE TABLE {$projects} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL,
				description text NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY name (name)
			) {$charset_collate};",

			"CREATE TABLE {$urls} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NULL,
				url varchar(2048) NOT NULL,
				name varchar(255) NULL,
				audit_frequency varchar(20) NOT NULL DEFAULT 'weekly',
				audit_strategy varchar(20) NOT NULL DEFAULT 'both',
				enabled tinyint(1) NOT NULL DEFAULT 1,
				alerts_enabled tinyint(1) NOT NULL DEFAULT 0,
				alert_threshold_score smallint NULL,
				alert_threshold_drop smallint NULL,
				last_audited_at datetime NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY url (url(191)),
				KEY project_id (project_id),
				KEY enabled (enabled),
				KEY audit_frequency (audit_frequency),
				KEY last_audited_at (last_audited_at)
			) {$charset_collate};",

			"CREATE TABLE {$audits} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				url_id bigint(20) unsigned NOT NULL,
				score smallint NOT NULL,
				status varchar(20) NOT NULL,
				strategy varchar(20) NOT NULL DEFAULT 'desktop',
				audit_date datetime NOT NULL,
				raw_response longtext NULL,
				error_message text NULL,
				retry_count smallint NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY url_id (url_id),
				KEY audit_date (audit_date),
				KEY status (status),
				KEY url_date (url_id, audit_date)
			) {$charset_collate};",

			"CREATE TABLE {$issues} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				audit_id bigint(20) unsigned NOT NULL,
				severity varchar(20) NOT NULL,
				category varchar(64) NOT NULL,
				title varchar(255) NULL,
				description text NOT NULL,
				element_selector text NULL,
				help_url varchar(2048) NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY audit_id (audit_id),
				KEY severity (severity),
				KEY category (category)
			) {$charset_collate};",

			"CREATE TABLE {$audit_comparisons} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				current_audit_id bigint(20) unsigned NOT NULL,
				previous_audit_id bigint(20) unsigned NOT NULL,
				score_delta smallint NOT NULL,
				new_issues_count int unsigned NOT NULL DEFAULT 0,
				resolved_issues_count int unsigned NOT NULL DEFAULT 0,
				persistent_issues_count int unsigned NOT NULL DEFAULT 0,
				trend varchar(20) NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY current_audit_id (current_audit_id),
				KEY previous_audit_id (previous_audit_id)
			) {$charset_collate};",

			"CREATE TABLE {$notifications} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				url_id bigint(20) unsigned NOT NULL,
				audit_id bigint(20) unsigned NOT NULL,
				notification_type varchar(64) NOT NULL,
				channel varchar(20) NOT NULL,
				sent_at datetime NULL,
				failed_at datetime NULL,
				error_message text NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY url_id (url_id),
				KEY audit_id (audit_id),
				KEY sent_at (sent_at),
				KEY channel (channel)
			) {$charset_collate};",

			"CREATE TABLE {$email_subscriptions} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				project_id bigint(20) unsigned NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY user_project (user_id, project_id),
				KEY project_id (project_id)
			) {$charset_collate};",
		];

		foreach ( $statements as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Drop every plugin table. Use on uninstall only.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$tables = [
			self::TABLE_EMAIL_SUBSCRIPTIONS,
			self::TABLE_NOTIFICATIONS,
			self::TABLE_AUDIT_COMPARISONS,
			self::TABLE_ISSUES,
			self::TABLE_AUDITS,
			self::TABLE_URLS,
			self::TABLE_PROJECTS,
		];

		foreach ( $tables as $table ) {
			$prefixed = self::table( $table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $prefixed ) );
		}
	}
}
