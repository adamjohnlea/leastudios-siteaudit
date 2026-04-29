<?php
/**
 * Plugin deactivation handler.
 *
 * @package LEAStudios\SiteAudit
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit;

defined( 'ABSPATH' ) || exit;

/**
 * Runs once when the plugin is deactivated. Does NOT drop data — only
 * unschedules cron events. Data removal happens in uninstall.php.
 */
final class Deactivation {

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public function run(): void {
		$timestamp = wp_next_scheduled( 'leastudios_siteaudit_tick' );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, 'leastudios_siteaudit_tick' );
		}
	}
}
