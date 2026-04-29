<?php
/**
 * Plugin deactivation handler.
 *
 * @package LEAStudios\SiteAudit
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Tick_Dispatcher;

/**
 * Runs once when the plugin is deactivated. Does NOT drop data — only
 * unschedules our queued and recurring Action Scheduler actions. Data
 * removal happens in uninstall.php.
 */
final class Deactivation {

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public function run(): void {
		// Cancel both the recurring tick and any per-URL audit actions still
		// pending in the queue. Reactivation re-creates the recurring tick on
		// the next page load via Plugin::register_recurring_tick().
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Plugin::TICK_HOOK );
			as_unschedule_all_actions( Tick_Dispatcher::RUN_AUDIT_HOOK );
		}

		// Belt and braces: clean up any leftover wp-cron event from before
		// Phase 5 if the plugin is upgraded across the change.
		$timestamp = wp_next_scheduled( Plugin::TICK_HOOK );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, Plugin::TICK_HOOK );
		}
	}
}
