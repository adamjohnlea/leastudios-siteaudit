<?php
/**
 * Dashboard_Controller integration test.
 *
 * Exercises the surface that has historically been silent in CI:
 * menu registration ordering, hookname registration via `add_submenu_page`,
 * GET-action dispatch, and capability gating. The first test in particular
 * (menu registration triggers `do_action('admin_menu')` and asserts the
 * registered hooknames) catches the class of bug that 404'd the Settings
 * link in Phase 4 — when `Settings_Page::register_menu` ran before
 * `Dashboard_Controller::register_menu`, `add_submenu_page` registered the
 * Settings callback against `admin_page_*` while WP later recomputed it
 * as `site-audit_page_*`, dropping the link.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Admin;

use LEAStudios\SiteAudit\Activation;
use LEAStudios\SiteAudit\Admin\Settings_Page;
use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Trend_Calculator;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Audit_Repository;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Issue_Repository;
use LEAStudios\SiteAudit\Modules\Dashboard\Admin\Dashboard_Controller;
use LEAStudios\SiteAudit\Modules\Dashboard\Application\Services\Dashboard_Statistics;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Project_Service;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Project_Repository;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Url_Repository;
use LEAStudios\Tests\TestCase;

final class Dashboard_Controller_Test extends TestCase {

	private Dashboard_Controller $controller;

	private Settings_Page $settings_page;

	private string $captured_redirect = '';

	public function set_up(): void {
		parent::set_up();

		( new Activation() )->run();

		$project_repository = new Wpdb_Project_Repository();
		$url_repository     = new Wpdb_Url_Repository();
		$audit_repository   = new Wpdb_Audit_Repository();
		$issue_repository   = new Wpdb_Issue_Repository();

		$this->controller = new Dashboard_Controller(
			$project_repository,
			$url_repository,
			$audit_repository,
			$issue_repository,
			new Dashboard_Statistics(),
			new Trend_Calculator()
		);

		$this->settings_page = new Settings_Page();

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$this->captured_redirect = '';
		add_filter( 'wp_redirect', [ $this, 'capture_redirect' ], 1 );
	}

	public function tear_down(): void {
		remove_filter( 'wp_redirect', [ $this, 'capture_redirect' ], 1 );
		$_GET = [];
		parent::tear_down();
	}

	public function capture_redirect( string $location ): string {
		$this->captured_redirect = $location;
		throw new \RuntimeException( 'redirect-captured' );
	}

	/**
	 * The Phase 4 regression catcher.
	 *
	 * If Settings_Page registers its submenu before Dashboard_Controller
	 * has registered the parent via add_menu_page, the hookname WP later
	 * recomputes for the Settings page won't match what add_submenu_page
	 * registered the callback against, and the Settings link 404s.
	 *
	 * Asserting both (a) parent menu present in $menu and (b) the Settings
	 * submenu's hook is registered under the correct `site-audit_page_*`
	 * name pins down both ordering and slug-resolution behavior.
	 */
	public function test_menu_registration_wires_parent_and_settings_under_correct_hook(): void {
		// Match the registration order in Plugin::init_admin(): Settings_Page
		// first, then Dashboard_Controller. Without the priority bump in
		// Settings_Page::init(), this models the bug exactly — Settings_Page's
		// `admin_menu` listener fires at priority 10 before Dashboard_Controller's.
		$this->settings_page->init();
		$this->controller->init();

		do_action( 'admin_menu' );

		global $menu, $submenu;

		$parent_slugs = array_column( $menu ?? [], 2 );
		$this->assertContains(
			Dashboard_Controller::PARENT_SLUG,
			$parent_slugs,
			'Top-level "Site Audit" menu must be registered.'
		);

		$this->assertArrayHasKey(
			Dashboard_Controller::PARENT_SLUG,
			$submenu ?? [],
			'Submenus must be registered under the parent slug.'
		);

		$submenu_slugs = array_column( $submenu[ Dashboard_Controller::PARENT_SLUG ], 2 );
		$this->assertContains( Dashboard_Controller::PARENT_SLUG, $submenu_slugs, 'Dashboard submenu missing.' );
		$this->assertContains( Settings_Page::PAGE_SLUG, $submenu_slugs, 'Settings submenu missing.' );

		// The smoking-gun assertion: `add_submenu_page` resolves the hookname
		// using $admin_page_hooks[parent_slug], which only exists once
		// `add_menu_page` has run. If Settings_Page::init had stayed at the
		// default priority, this would be `admin_page_*` and the call would
		// return false.
		$expected_settings_hook = 'site-audit_page_' . Settings_Page::PAGE_SLUG;
		$this->assertNotFalse(
			has_action( $expected_settings_hook ),
			'Settings page callback must be registered under the parent menu hook, not the default `admin_page_*`.'
		);

		$expected_dashboard_hook = 'toplevel_page_' . Dashboard_Controller::PARENT_SLUG;
		$this->assertNotFalse(
			has_action( $expected_dashboard_hook ),
			'Dashboard render callback must be registered under the toplevel page hook.'
		);
	}

	public function test_render_overview_outputs_dashboard_heading(): void {
		// Seed at least one project so the empty-state branch is skipped and
		// the project-cards branch runs end-to-end.
		$service = new Project_Service( new Wpdb_Project_Repository() );
		$service->create( 'Acme', null );

		$_GET['action'] = '';

		ob_start();
		$this->controller->render_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Site Audit Dashboard', $html );
		$this->assertStringContainsString( 'Acme', $html );
	}

	public function test_render_project_redirects_to_overview_when_project_id_unknown(): void {
		$_GET['action'] = 'project';
		$_GET['id']     = '99999';

		try {
			$this->controller->render_page();
			$this->fail( 'Expected redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$this->assertStringContainsString( 'page=' . Dashboard_Controller::PARENT_SLUG, $this->captured_redirect );
		$this->assertStringNotContainsString( 'action=project', $this->captured_redirect );
	}

	public function test_render_url_redirects_to_overview_when_url_id_unknown(): void {
		$_GET['action'] = 'url';
		$_GET['id']     = '99999';

		try {
			$this->controller->render_page();
			$this->fail( 'Expected redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect-captured', $e->getMessage() );
		}

		$this->assertStringContainsString( 'page=' . Dashboard_Controller::PARENT_SLUG, $this->captured_redirect );
		$this->assertStringNotContainsString( 'action=url', $this->captured_redirect );
	}

	public function test_render_page_dies_for_user_without_view_capability(): void {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$_GET['action'] = '';

		$this->expectException( \WPDieException::class );
		$this->controller->render_page();
	}

	public function test_enqueue_assets_only_loads_css_on_plugin_pages(): void {
		$this->controller->enqueue_assets( 'index.php' );
		$this->assertFalse( wp_style_is( 'leastudios-siteaudit-dashboard', 'enqueued' ) );

		$this->controller->enqueue_assets( 'toplevel_page_' . Dashboard_Controller::PARENT_SLUG );
		$this->assertTrue( wp_style_is( 'leastudios-siteaudit-dashboard', 'enqueued' ) );
	}
}
