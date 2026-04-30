<?php
/**
 * Subscription_Controller integration test.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Admin;

use LEAStudios\SiteAudit\Activation;
use LEAStudios\SiteAudit\Modules\Notification\Admin\Subscription_Controller;
use LEAStudios\SiteAudit\Modules\Notification\Infrastructure\Repositories\Wpdb_Email_Subscription_Repository;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Project_Service;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Project_Repository;
use LEAStudios\Tests\TestCase;

final class Subscription_Controller_Test extends TestCase {

	private Subscription_Controller $controller;

	private Wpdb_Email_Subscription_Repository $subscription_repository;

	private Project $project;

	private string $captured_redirect = '';

	public function set_up(): void {
		parent::set_up();
		( new Activation() )->run();

		$project_repository            = new Wpdb_Project_Repository();
		$this->subscription_repository = new Wpdb_Email_Subscription_Repository();

		$this->project = ( new Project_Service( $project_repository ) )->create( 'Acme', null );

		$this->controller = new Subscription_Controller( $project_repository, $this->subscription_repository );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$this->captured_redirect = '';
		add_filter( 'wp_redirect', [ $this, 'capture_redirect' ], 1 );
	}

	public function tear_down(): void {
		remove_filter( 'wp_redirect', [ $this, 'capture_redirect' ], 1 );
		$_POST    = [];
		$_REQUEST = [];
		parent::tear_down();
	}

	public function capture_redirect( string $location ): string {
		$this->captured_redirect = $location;
		throw new \RuntimeException( 'redirect-captured' );
	}

	public function test_init_registers_admin_post_hook(): void {
		$this->controller->init();
		$this->assertNotFalse( has_action( 'admin_post_' . Subscription_Controller::ACTION_TOGGLE ) );
	}

	public function test_handle_toggle_subscribes_when_not_yet_subscribed(): void {
		$user_id    = get_current_user_id();
		$project_id = (int) $this->project->id();

		$this->assertFalse( $this->subscription_repository->is_subscribed( $user_id, $project_id ) );

		$_POST    = [
			'_wpnonce'   => wp_create_nonce( Subscription_Controller::ACTION_TOGGLE ),
			'project_id' => (string) $project_id,
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		try {
			$this->controller->handle_toggle();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$this->assertTrue( $this->subscription_repository->is_subscribed( $user_id, $project_id ) );
		$this->assertStringContainsString( 'action=project', $this->captured_redirect );
	}

	public function test_handle_toggle_unsubscribes_when_already_subscribed(): void {
		$user_id    = get_current_user_id();
		$project_id = (int) $this->project->id();

		$this->subscription_repository->subscribe( $user_id, $project_id );
		$this->assertTrue( $this->subscription_repository->is_subscribed( $user_id, $project_id ) );

		$_POST    = [
			'_wpnonce'   => wp_create_nonce( Subscription_Controller::ACTION_TOGGLE ),
			'project_id' => (string) $project_id,
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		try {
			$this->controller->handle_toggle();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$this->assertFalse( $this->subscription_repository->is_subscribed( $user_id, $project_id ) );
	}

	public function test_handle_toggle_dies_for_user_without_view_capability(): void {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$_POST    = [];
		$_REQUEST = [];

		$this->expectException( \WPDieException::class );
		$this->controller->handle_toggle();
	}

	public function test_handle_toggle_redirects_with_error_when_project_unknown(): void {
		$_POST    = [
			'_wpnonce'   => wp_create_nonce( Subscription_Controller::ACTION_TOGGLE ),
			'project_id' => '99999',
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		try {
			$this->controller->handle_toggle();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$user_id = get_current_user_id();
		$notice  = get_transient( 'leastudios_siteaudit_notice_' . $user_id );
		$this->assertIsArray( $notice );
		$this->assertSame( 'error', $notice['type'] );
	}
}
