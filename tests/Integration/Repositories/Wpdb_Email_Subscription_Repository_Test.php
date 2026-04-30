<?php
/**
 * Wpdb_Email_Subscription_Repository round-trip tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Repositories;

use LEAStudios\SiteAudit\Activation;
use LEAStudios\SiteAudit\Modules\Notification\Infrastructure\Repositories\Wpdb_Email_Subscription_Repository;
use LEAStudios\Tests\TestCase;

final class Wpdb_Email_Subscription_Repository_Test extends TestCase {

	private Wpdb_Email_Subscription_Repository $repository;

	public function set_up(): void {
		parent::set_up();
		( new Activation() )->run();
		$this->repository = new Wpdb_Email_Subscription_Repository();
	}

	public function test_subscribe_then_is_subscribed_returns_true(): void {
		$user_id = self::factory()->user->create();

		$this->assertFalse( $this->repository->is_subscribed( $user_id, 1 ) );

		$this->repository->subscribe( $user_id, 1 );

		$this->assertTrue( $this->repository->is_subscribed( $user_id, 1 ) );
	}

	public function test_subscribe_is_idempotent(): void {
		$user_id = self::factory()->user->create();

		$this->repository->subscribe( $user_id, 1 );
		$this->repository->subscribe( $user_id, 1 );
		$this->repository->subscribe( $user_id, 1 );

		// One row, not three.
		$subscribers = $this->repository->find_subscribers_by_project_id( 1 );
		$this->assertCount( 1, $subscribers );
	}

	public function test_unsubscribe_removes_subscription(): void {
		$user_id = self::factory()->user->create();

		$this->repository->subscribe( $user_id, 1 );
		$this->assertTrue( $this->repository->is_subscribed( $user_id, 1 ) );

		$this->repository->unsubscribe( $user_id, 1 );
		$this->assertFalse( $this->repository->is_subscribed( $user_id, 1 ) );
	}

	public function test_unsubscribe_is_idempotent_when_not_subscribed(): void {
		$user_id = self::factory()->user->create();

		// Should not throw.
		$this->repository->unsubscribe( $user_id, 1 );
		$this->assertFalse( $this->repository->is_subscribed( $user_id, 1 ) );
	}

	public function test_find_subscribers_returns_wp_user_instances(): void {
		$user_a = self::factory()->user->create( [ 'user_email' => 'a@example.com' ] );
		$user_b = self::factory()->user->create( [ 'user_email' => 'b@example.com' ] );

		$this->repository->subscribe( $user_a, 1 );
		$this->repository->subscribe( $user_b, 1 );

		$subscribers = $this->repository->find_subscribers_by_project_id( 1 );

		$this->assertCount( 2, $subscribers );
		$this->assertContainsOnlyInstancesOf( \WP_User::class, $subscribers );

		$emails = array_map( static fn( \WP_User $u ): string => (string) $u->user_email, $subscribers );
		$this->assertContains( 'a@example.com', $emails );
		$this->assertContains( 'b@example.com', $emails );
	}

	public function test_find_subscribers_skips_orphaned_rows_when_user_deleted(): void {
		$user_id = self::factory()->user->create( [ 'user_email' => 'temp@example.com' ] );
		$this->repository->subscribe( $user_id, 1 );

		// Delete the user; the subscription row is now orphaned.
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );

		$subscribers = $this->repository->find_subscribers_by_project_id( 1 );

		$this->assertSame( [], $subscribers );
	}

	public function test_subscriptions_are_scoped_per_project(): void {
		$user_id = self::factory()->user->create();

		$this->repository->subscribe( $user_id, 1 );

		$this->assertTrue( $this->repository->is_subscribed( $user_id, 1 ) );
		$this->assertFalse( $this->repository->is_subscribed( $user_id, 2 ) );
	}
}
