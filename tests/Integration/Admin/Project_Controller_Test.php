<?php
/**
 * Project_Controller integration test: full nonce → handler → service → repository flow.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Admin;

use LEAStudios\SiteAudit\Activation;
use LEAStudios\SiteAudit\Capabilities;
use LEAStudios\SiteAudit\Modules\Url\Admin\Project_Controller;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Project_Service;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Project_Repository;
use LEAStudios\SiteAudit\Shared\Template_Renderer;
use LEAStudios\Tests\TestCase;

final class Project_Controller_Test extends TestCase {

	private Project_Controller $controller;

	private Wpdb_Project_Repository $repository;

	private string $captured_redirect = '';

	public function set_up(): void {
		parent::set_up();

		( new Activation() )->run();

		$this->repository = new Wpdb_Project_Repository();
		$service          = new Project_Service( $this->repository );
		$this->controller = new Project_Controller(
			$service,
			new Template_Renderer( LEASTUDIOS_SITEAUDIT_DIR . 'templates' )
		);

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

	public function test_handle_create_persists_a_project_and_redirects_to_list(): void {
		$nonce = wp_create_nonce( 'leastudios_siteaudit_create_project' );

		$_POST    = [
			'_wpnonce'    => $nonce,
			'name'        => 'Acme Marketing',
			'description' => 'Top-of-funnel landing pages',
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- test sets $_POST directly to drive the controller.

		try {
			$this->controller->handle_create();
			$this->fail( 'Expected redirect to short-circuit handler.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$this->assertStringContainsString( 'page=' . Project_Controller::PAGE_SLUG, $this->captured_redirect );
		$this->assertStringNotContainsString( 'action=create', $this->captured_redirect );

		$saved = $this->repository->find_by_name( 'Acme Marketing' );
		$this->assertNotNull( $saved );
		$this->assertSame( 'Acme Marketing', $saved->name()->value() );
		$this->assertSame( 'Top-of-funnel landing pages', $saved->description() );
	}

	public function test_handle_create_with_duplicate_name_redirects_back_to_form(): void {
		$service = new Project_Service( $this->repository );
		$service->create( 'Acme', null );

		$nonce = wp_create_nonce( 'leastudios_siteaudit_create_project' );

		$_POST    = [
			'_wpnonce'    => $nonce,
			'name'        => 'Acme',
			'description' => '',
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- test sets $_POST directly to drive the controller.

		try {
			$this->controller->handle_create();
			$this->fail( 'Expected redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$this->assertStringContainsString( 'action=create', $this->captured_redirect );

		$user_id = get_current_user_id();
		$notice  = get_transient( 'leastudios_siteaudit_notice_' . $user_id );
		$this->assertIsArray( $notice );
		$this->assertSame( 'error', $notice['type'] );
		$this->assertStringContainsString( 'already exists', $notice['message'] );
	}

	public function test_handle_create_without_manage_cap_dies(): void {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$_POST    = [];
		$_REQUEST = [];

		$this->expectException( \WPDieException::class );
		$this->controller->handle_create();
	}

	public function test_capabilities_constants_match_expected_strings(): void {
		$this->assertSame( 'manage_leastudios_siteaudit', Capabilities::MANAGE );
		$this->assertSame( 'view_leastudios_siteaudit', Capabilities::VIEW );
	}
}
