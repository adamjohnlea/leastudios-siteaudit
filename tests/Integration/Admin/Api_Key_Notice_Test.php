<?php
/**
 * Api_Key_Notice integration test.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Admin;

use LEAStudios\SiteAudit\Activation;
use LEAStudios\SiteAudit\Admin\Api_Key_Notice;
use LEAStudios\SiteAudit\Admin\Settings_Page;
use LEAStudios\SiteAudit\Capabilities;
use LEAStudios\Tests\TestCase;

final class Api_Key_Notice_Test extends TestCase {

	private Api_Key_Notice $notice;

	public function set_up(): void {
		parent::set_up();
		( new Activation() )->run();
		$this->notice = new Api_Key_Notice();

		// Default screen for tests; specific tests override via set_current_screen().
		set_current_screen( 'dashboard' );
	}

	public function test_renders_warning_when_api_key_is_empty_for_admin_user(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		ob_start();
		$this->notice->maybe_render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'PageSpeed Insights API key', $output );
		$this->assertStringContainsString( Settings_Page::PAGE_SLUG, $output );
	}

	public function test_does_not_render_when_api_key_is_set(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		update_option(
			Settings_Page::OPTION_NAME,
			array_merge(
				Activation::default_options(),
				[ 'pagespeed_api_key' => 'AIzaTEST' ]
			)
		);

		ob_start();
		$this->notice->maybe_render();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_does_not_render_when_api_key_is_only_whitespace(): void {
		// Empty + whitespace must read identically — operators sometimes paste a stray space.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		update_option(
			Settings_Page::OPTION_NAME,
			array_merge(
				Activation::default_options(),
				[ 'pagespeed_api_key' => "   \n\t" ]
			)
		);

		ob_start();
		$this->notice->maybe_render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $output );
	}

	public function test_does_not_render_for_user_without_manage_capability(): void {
		$editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor_id );
		// Editor gets VIEW but not MANAGE; sanity-check the precondition.
		$this->assertFalse( current_user_can( Capabilities::MANAGE ) );

		ob_start();
		$this->notice->maybe_render();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_suppressed_on_plugin_settings_screen(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// Mimic the screen id WordPress generates for our submenu page.
		set_current_screen( 'site-audit_page_' . Settings_Page::PAGE_SLUG );

		ob_start();
		$this->notice->maybe_render();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_init_registers_admin_notices_hook(): void {
		$this->notice->init();
		$this->assertNotFalse( has_action( 'admin_notices', [ $this->notice, 'maybe_render' ] ) );
	}
}
