<?php
/**
 * Uninstall handler — runs when the plugin is deleted via WP admin.
 *
 * @package LEAStudios\SiteAudit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

LEAStudios\SiteAudit\Capabilities::remove();
LEAStudios\SiteAudit\Database\Schema::drop_tables();

delete_option( 'leastudios_siteaudit_options' );
delete_option( 'leastudios_siteaudit_db_version' );

$leastudios_siteaudit_cron_timestamp = wp_next_scheduled( 'leastudios_siteaudit_tick' );
if ( $leastudios_siteaudit_cron_timestamp ) {
	wp_unschedule_event( $leastudios_siteaudit_cron_timestamp, 'leastudios_siteaudit_tick' );
}

flush_rewrite_rules();
