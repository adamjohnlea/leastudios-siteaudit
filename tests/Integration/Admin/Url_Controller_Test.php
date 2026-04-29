<?php
/**
 * Url_Controller integration test: full nonce → handler → service → repository flow.
 *
 * Mirrors the Project_Controller_Test pattern across all five admin-post
 * handlers (create, update, delete, bulk-import, run-audit) plus the
 * capability-denial path. The run-audit case stubs Audit_Service through
 * its interface so the test exercises the controller's notice / redirect
 * logic without making a real PageSpeed call.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Admin;

use LEAStudios\SiteAudit\Activation;
use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Audit_Service_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Audit_Repository;
use LEAStudios\SiteAudit\Modules\Url\Admin\Url_Controller;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Bulk_Import_Service;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Project_Service;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Url_Service;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Project_Repository;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Url_Repository;
use LEAStudios\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class Url_Controller_Test extends TestCase {

	private Url_Controller $controller;

	private Wpdb_Url_Repository $url_repository;

	private Audit_Service_Interface&MockObject $audit_service;

	private string $captured_redirect = '';

	public function set_up(): void {
		parent::set_up();

		( new Activation() )->run();

		$this->url_repository = new Wpdb_Url_Repository();
		$audit_repository     = new Wpdb_Audit_Repository();

		$url_service         = new Url_Service( $this->url_repository );
		$project_service     = new Project_Service( new Wpdb_Project_Repository() );
		$bulk_import_service = new Bulk_Import_Service( $this->url_repository );

		$this->audit_service = $this->createMock( Audit_Service_Interface::class );

		$this->controller = new Url_Controller(
			$url_service,
			$project_service,
			$bulk_import_service,
			$this->audit_service,
			$audit_repository
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

	public function test_handle_create_persists_url_and_redirects_to_list(): void {
		$nonce = wp_create_nonce( 'leastudios_siteaudit_create_url' );

		$_POST    = [
			'_wpnonce'  => $nonce,
			'url'       => 'https://example.com',
			'name'      => 'Example',
			'frequency' => 'weekly',
			'strategy'  => 'both',
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		try {
			$this->controller->handle_create();
			$this->fail( 'Expected redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$this->assertStringContainsString( 'page=' . Url_Controller::PAGE_SLUG, $this->captured_redirect );
		$this->assertStringNotContainsString( 'action=create', $this->captured_redirect );

		$saved = $this->url_repository->find_by_url( 'https://example.com' );
		$this->assertNotNull( $saved );
		$this->assertSame( 'Example', $saved->name() );
	}

	public function test_handle_create_with_invalid_url_redirects_back_to_form(): void {
		$nonce = wp_create_nonce( 'leastudios_siteaudit_create_url' );

		$_POST    = [
			'_wpnonce'  => $nonce,
			'url'       => 'not a url',
			'name'      => 'Invalid',
			'frequency' => 'weekly',
			'strategy'  => 'both',
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

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
	}

	public function test_handle_update_modifies_existing_url(): void {
		$created = ( new Url_Service( $this->url_repository ) )->create(
			'https://example.com',
			'Original',
			'weekly',
			null,
			'both',
			false,
			null,
			null
		);

		$nonce = wp_create_nonce( 'leastudios_siteaudit_update_url' );

		$_POST    = [
			'_wpnonce'  => $nonce,
			'id'        => (string) $created->id(),
			'name'      => 'Renamed',
			'frequency' => 'daily',
			'strategy'  => 'desktop',
			'enabled'   => '1',
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		try {
			$this->controller->handle_update();
			$this->fail( 'Expected redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$updated = $this->url_repository->find_by_id( (int) $created->id() );
		$this->assertNotNull( $updated );
		$this->assertSame( 'Renamed', $updated->name() );
		$this->assertSame( 'daily', $updated->audit_frequency()->value );
		$this->assertSame( 'desktop', $updated->audit_strategy()->value );
	}

	public function test_handle_delete_removes_url(): void {
		$created = ( new Url_Service( $this->url_repository ) )->create(
			'https://example.com',
			'Doomed',
			'weekly',
			null,
			'both',
			false,
			null,
			null
		);

		$nonce = wp_create_nonce( 'leastudios_siteaudit_delete_url' );

		$_POST    = [
			'_wpnonce' => $nonce,
			'id'       => (string) $created->id(),
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		try {
			$this->controller->handle_delete();
			$this->fail( 'Expected redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$this->assertNull( $this->url_repository->find_by_id( (int) $created->id() ) );
	}

	public function test_handle_bulk_import_paste_redirects_to_result_page(): void {
		$nonce = wp_create_nonce( 'leastudios_siteaudit_bulk_import_urls' );

		$_POST    = [
			'_wpnonce'    => $nonce,
			'import_type' => 'paste',
			'frequency'   => 'weekly',
			'urls'        => "https://one.test\nhttps://two.test",
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		try {
			$this->controller->handle_bulk_import();
			$this->fail( 'Expected redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$this->assertStringContainsString( 'action=bulk-import-result', $this->captured_redirect );
		$this->assertStringContainsString( 'token=', $this->captured_redirect );

		$this->assertNotNull( $this->url_repository->find_by_url( 'https://one.test' ) );
		$this->assertNotNull( $this->url_repository->find_by_url( 'https://two.test' ) );
	}

	public function test_handle_run_audit_calls_service_and_enqueues_success_notice(): void {
		$created = ( new Url_Service( $this->url_repository ) )->create(
			'https://example.com',
			'Auditable',
			'weekly',
			null,
			'desktop',
			false,
			null,
			null
		);

		$completed_audit = $this->fake_audit( (int) $created->id(), Audit_Status::COMPLETED );
		$this->audit_service
			->expects( $this->once() )
			->method( 'run_audit' )
			->with( (int) $created->id() )
			->willReturn( [ $completed_audit ] );

		$nonce = wp_create_nonce( 'leastudios_siteaudit_run_audit' );

		$_POST    = [
			'_wpnonce' => $nonce,
			'id'       => (string) $created->id(),
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		try {
			$this->controller->handle_run_audit();
			$this->fail( 'Expected redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$user_id = get_current_user_id();
		$notice  = get_transient( 'leastudios_siteaudit_notice_' . $user_id );
		$this->assertIsArray( $notice );
		$this->assertSame( 'success', $notice['type'] );
	}

	public function test_handle_run_audit_surfaces_settings_hint_when_all_strategies_fail(): void {
		$created = ( new Url_Service( $this->url_repository ) )->create(
			'https://example.com',
			'Failing',
			'weekly',
			null,
			'desktop',
			false,
			null,
			null
		);

		$failed_audit = $this->fake_audit( (int) $created->id(), Audit_Status::FAILED );
		$this->audit_service
			->method( 'run_audit' )
			->willReturn( [ $failed_audit ] );

		$nonce = wp_create_nonce( 'leastudios_siteaudit_run_audit' );

		$_POST    = [
			'_wpnonce' => $nonce,
			'id'       => (string) $created->id(),
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		try {
			$this->controller->handle_run_audit();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$user_id = get_current_user_id();
		$notice  = get_transient( 'leastudios_siteaudit_notice_' . $user_id );
		$this->assertIsArray( $notice );
		$this->assertSame( 'error', $notice['type'] );
		$this->assertStringContainsString( 'API key', $notice['message'] );
	}

	public function test_handle_run_audit_with_invalid_id_enqueues_error_and_does_not_call_service(): void {
		$this->audit_service->expects( $this->never() )->method( 'run_audit' );

		$nonce = wp_create_nonce( 'leastudios_siteaudit_run_audit' );

		$_POST    = [
			'_wpnonce' => $nonce,
			'id'       => '0',
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		try {
			$this->controller->handle_run_audit();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$user_id = get_current_user_id();
		$notice  = get_transient( 'leastudios_siteaudit_notice_' . $user_id );
		$this->assertIsArray( $notice );
		$this->assertSame( 'error', $notice['type'] );
	}

	public function test_handle_create_dies_for_user_without_manage_capability(): void {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$_POST    = [];
		$_REQUEST = [];

		$this->expectException( \WPDieException::class );
		$this->controller->handle_create();
	}

	public function test_handle_run_audit_dies_for_user_without_manage_capability(): void {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$_POST    = [];
		$_REQUEST = [];

		$this->expectException( \WPDieException::class );
		$this->controller->handle_run_audit();
	}

	private function fake_audit( int $url_id, Audit_Status $status ): Audit {
		$now = new \DateTimeImmutable();

		return new Audit(
			1,
			$url_id,
			new Accessibility_Score( 85 ),
			$status,
			Run_Strategy::DESKTOP,
			$now,
			null,
			null,
			0,
			$now
		);
	}
}
