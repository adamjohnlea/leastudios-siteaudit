<?php
/**
 * Subscription repository contract.
 *
 * @package LEAStudios\SiteAudit\Modules\Notification\Domain\Repositories
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Notification\Domain\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Tracks which WordPress users have opted in to email notifications for
 * each project. Backed by `{$wpdb->prefix}leastudios_siteaudit_email_subscriptions`
 * with a unique `(user_id, project_id)` index.
 *
 * `find_subscribers_by_project_id()` returns full `WP_User` instances —
 * the notifiers only need `->user_email` and `->display_name`, but having
 * the whole user object lets future templates personalise without another
 * round-trip.
 */
interface Email_Subscription_Repository_Interface {

	/**
	 * Subscribe a user to a project. Idempotent — a second subscribe is a no-op.
	 *
	 * @param int $user_id    WordPress user id.
	 * @param int $project_id Project row id.
	 *
	 * @return void
	 */
	public function subscribe( int $user_id, int $project_id ): void;

	/**
	 * Unsubscribe a user from a project. Idempotent — unsubscribing when not
	 * subscribed is a no-op.
	 *
	 * @param int $user_id    WordPress user id.
	 * @param int $project_id Project row id.
	 *
	 * @return void
	 */
	public function unsubscribe( int $user_id, int $project_id ): void;

	/**
	 * Whether a user is subscribed to a project.
	 *
	 * @param int $user_id    WordPress user id.
	 * @param int $project_id Project row id.
	 *
	 * @return bool
	 */
	public function is_subscribed( int $user_id, int $project_id ): bool;

	/**
	 * All subscribed users for a project, joined to `wp_users`.
	 *
	 * Orphaned rows (subscriptions whose user has been deleted) are silently
	 * dropped by the JOIN — no cleanup needed; they simply yield nothing.
	 *
	 * @param int $project_id Project row id.
	 *
	 * @return array<int, \WP_User>
	 */
	public function find_subscribers_by_project_id( int $project_id ): array;
}
