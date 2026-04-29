<?php
/**
 * Abstraction over Action Scheduler's enqueue / lookup primitives.
 *
 * @package LEAStudios\SiteAudit\Modules\Scheduler\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Scheduler\Application\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Thin facade over the `as_*()` global functions.
 *
 * Lets `Tick_Dispatcher` and `Url_Controller` schedule async work without
 * coupling tests to Action Scheduler's database tables. The production
 * implementation (`As_Action_Enqueuer`) delegates 1:1 to the AS API.
 */
interface Action_Enqueuer_Interface {

	/**
	 * Enqueue an async action for immediate background execution.
	 *
	 * @param string                  $hook Action hook name.
	 * @param array<int, scalar|null> $args Positional args passed to the listener.
	 *
	 * @return void
	 */
	public function enqueue_async( string $hook, array $args ): void;

	/**
	 * Whether a pending action with the given hook + args already exists.
	 *
	 * @param string                  $hook Action hook name.
	 * @param array<int, scalar|null> $args Positional args.
	 *
	 * @return bool
	 */
	public function has_pending( string $hook, array $args ): bool;
}
