<?php
/**
 * Production-side action enqueuer that delegates to Action Scheduler.
 *
 * @package LEAStudios\SiteAudit\Modules\Scheduler\Infrastructure
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Scheduler\Infrastructure;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Action_Enqueuer_Interface;

/**
 * Bridge between the plugin's domain code and Action Scheduler's globals.
 *
 * Delegates to `as_enqueue_async_action()` and `as_has_scheduled_action()`.
 * The function-exists guard keeps the plugin from fatally erroring if AS
 * fails to load (extremely unlikely in production, but the right shape for
 * defensive code at a hard dependency boundary).
 */
final class As_Action_Enqueuer implements Action_Enqueuer_Interface {

	/**
	 * Enqueue an async action.
	 *
	 * @param string                  $hook Action hook name.
	 * @param array<int, scalar|null> $args Positional args.
	 *
	 * @return void
	 */
	public function enqueue_async( string $hook, array $args ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		as_enqueue_async_action( $hook, $args, 'leastudios-siteaudit' );
	}

	/**
	 * Whether a pending action with the given hook + args is queued.
	 *
	 * @param string                  $hook Action hook name.
	 * @param array<int, scalar|null> $args Positional args.
	 *
	 * @return bool
	 */
	public function has_pending( string $hook, array $args ): bool {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		return as_has_scheduled_action( $hook, $args, 'leastudios-siteaudit' );
	}
}
