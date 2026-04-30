<?php
/**
 * Subscription repository backed by `$wpdb`.
 *
 * @package LEAStudios\SiteAudit\Modules\Notification\Infrastructure\Repositories
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Notification\Infrastructure\Repositories;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Database\Schema;
use LEAStudios\SiteAudit\Modules\Notification\Domain\Repositories\Email_Subscription_Repository_Interface;

/**
 * Implements {@see Email_Subscription_Repository_Interface} on top of `$wpdb`.
 *
 * `subscribe()` uses `INSERT IGNORE` so a duplicate is a no-op — leverages
 * the unique `(user_id, project_id)` index on the schema.
 *
 * `find_subscribers_by_project_id()` does a single JOIN against `wp_users`
 * and returns hydrated `WP_User` objects; orphan subscription rows whose
 * user no longer exists are dropped by the INNER JOIN.
 */
final class Wpdb_Email_Subscription_Repository implements Email_Subscription_Repository_Interface {

	/**
	 * WP database abstraction.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Fully prefixed subscriptions table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $wpdb Optional `$wpdb` override.
	 */
	public function __construct( ?\wpdb $wpdb = null ) {
		$this->wpdb  = $wpdb ?? $GLOBALS['wpdb'];
		$this->table = Schema::table( Schema::TABLE_EMAIL_SUBSCRIPTIONS );
	}

	/**
	 * Subscribe a user. Idempotent via the unique index.
	 *
	 * @param int $user_id    WP user id.
	 * @param int $project_id Project id.
	 *
	 * @return void
	 */
	public function subscribe( int $user_id, int $project_id ): void {
		// `INSERT IGNORE` skips rows that would violate the unique index;
		// safer than a SELECT-then-INSERT race.
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is a class-controlled constant safely interpolated.
			"INSERT IGNORE INTO {$this->table} (user_id, project_id, created_at) VALUES (%d, %d, %s)",
			$user_id,
			$project_id,
			( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( $sql );
	}

	/**
	 * Unsubscribe a user. Idempotent.
	 *
	 * @param int $user_id    WP user id.
	 * @param int $project_id Project id.
	 *
	 * @return void
	 */
	public function unsubscribe( int $user_id, int $project_id ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->delete(
			$this->table,
			[
				'user_id'    => $user_id,
				'project_id' => $project_id,
			],
			[ '%d', '%d' ]
		);
	}

	/**
	 * Whether a user is subscribed to a project.
	 *
	 * @param int $user_id    WP user id.
	 * @param int $project_id Project id.
	 *
	 * @return bool
	 */
	public function is_subscribed( int $user_id, int $project_id ): bool {
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is a class-controlled constant safely interpolated.
			"SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND project_id = %d",
			$user_id,
			$project_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$count = $this->wpdb->get_var( $sql );

		return (int) $count > 0;
	}

	/**
	 * All subscribers for a project as `WP_User` instances.
	 *
	 * @param int $project_id Project id.
	 *
	 * @return array<int, \WP_User>
	 */
	public function find_subscribers_by_project_id( int $project_id ): array {
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is a class-controlled constant safely interpolated.
			"SELECT user_id FROM {$this->table} WHERE project_id = %d ORDER BY id ASC",
			$project_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$user_ids = $this->wpdb->get_col( $sql );

		if ( [] === $user_ids ) {
			return [];
		}

		$users = [];
		foreach ( $user_ids as $user_id ) {
			$user = get_user_by( 'id', (int) $user_id );
			if ( $user instanceof \WP_User ) {
				$users[] = $user;
			}
		}

		return $users;
	}
}
