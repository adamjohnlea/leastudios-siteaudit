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
use LEAStudios\SiteAudit\Database\Wpdb_Repository_Base;
use LEAStudios\SiteAudit\Modules\Notification\Domain\Repositories\Email_Subscription_Repository_Interface;
use LEAStudios\SiteAudit\Shared\Datetime_Util;

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
final class Wpdb_Email_Subscription_Repository extends Wpdb_Repository_Base implements Email_Subscription_Repository_Interface {

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $wpdb Optional `$wpdb` override.
	 */
	public function __construct( ?\wpdb $wpdb = null ) {
		parent::__construct( $wpdb, Schema::TABLE_EMAIL_SUBSCRIPTIONS );
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
			'INSERT IGNORE INTO %i (user_id, project_id, created_at) VALUES (%d, %d, %s)',
			$this->table,
			$user_id,
			$project_id,
			Datetime_Util::now()->format( 'Y-m-d H:i:s' )
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
			'SELECT COUNT(*) FROM %i WHERE user_id = %d AND project_id = %d',
			$this->table,
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
			'SELECT user_id FROM %i WHERE project_id = %d ORDER BY id ASC',
			$this->table,
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
