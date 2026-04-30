<?php
/**
 * Settings_Page integration test.
 *
 * Locks down the option key + sanitize behavior. Plugin.php and (Phase 5)
 * the Action Scheduler hookups read from this same option, so quiet
 * regressions here cascade into the audit pipeline.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Admin;

use LEAStudios\SiteAudit\Activation;
use LEAStudios\SiteAudit\Admin\Settings_Page;
use LEAStudios\SiteAudit\Capabilities;
use LEAStudios\Tests\TestCase;

final class Settings_Page_Test extends TestCase {

	private Settings_Page $page;

	public function set_up(): void {
		parent::set_up();
		( new Activation() )->run();
		$this->page = new Settings_Page();
	}

	public function test_option_name_constant_is_stable(): void {
		// External callers (Plugin::build_audit_service, future cron tick)
		// rely on this exact string. If it ever changes, every existing
		// install loses its API key on upgrade.
		$this->assertSame( 'leastudios_siteaudit_options', Settings_Page::OPTION_NAME );
	}

	public function test_register_settings_registers_the_option(): void {
		$this->page->register_settings();

		global $wp_settings_fields, $wp_settings_sections;

		$this->assertArrayHasKey( 'leastudios-siteaudit-settings', $wp_settings_sections ?? [] );
		$this->assertArrayHasKey( 'leastudios_siteaudit_pagespeed', $wp_settings_sections['leastudios-siteaudit-settings'] );
		$this->assertArrayHasKey( 'leastudios_siteaudit_defaults', $wp_settings_sections['leastudios-siteaudit-settings'] );

		$pagespeed_fields = $wp_settings_fields['leastudios-siteaudit-settings']['leastudios_siteaudit_pagespeed'] ?? [];
		$this->assertArrayHasKey( 'pagespeed_api_key', $pagespeed_fields );
		$this->assertArrayHasKey( 'pagespeed_rate_limit', $pagespeed_fields );
		$this->assertArrayHasKey( 'pagespeed_retry_count', $pagespeed_fields );
	}

	public function test_sanitize_options_clamps_rate_limit_to_valid_range(): void {
		$result = $this->page->sanitize_options( [ 'pagespeed_rate_limit' => 9999 ] );
		$this->assertSame( 60, $result['pagespeed_rate_limit'] );

		$result = $this->page->sanitize_options( [ 'pagespeed_rate_limit' => 0 ] );
		$this->assertSame( 1, $result['pagespeed_rate_limit'] );
	}

	public function test_sanitize_options_clamps_retry_count_to_valid_range(): void {
		$result = $this->page->sanitize_options( [ 'pagespeed_retry_count' => 50 ] );
		$this->assertSame( 10, $result['pagespeed_retry_count'] );

		// 0 is a valid value (no retries) and must be preserved.
		$result = $this->page->sanitize_options( [ 'pagespeed_retry_count' => 0 ] );
		$this->assertSame( 0, $result['pagespeed_retry_count'] );
	}

	public function test_sanitize_options_rejects_unknown_frequency_and_strategy(): void {
		update_option(
			Settings_Page::OPTION_NAME,
			array_merge(
				Activation::default_options(),
				[
					'default_audit_frequency' => 'weekly',
					'default_audit_strategy'  => 'both',
				]
			)
		);

		$result = $this->page->sanitize_options(
			[
				'default_audit_frequency' => 'never',
				'default_audit_strategy'  => 'tablet',
			]
		);

		// Unknown values must not overwrite the previously-stored option.
		$this->assertSame( 'weekly', $result['default_audit_frequency'] );
		$this->assertSame( 'both', $result['default_audit_strategy'] );
	}

	public function test_sanitize_options_strips_html_and_whitespace_from_api_key(): void {
		// sanitize_text_field strips the full <script>...</script> block (tag + body),
		// trims newlines, and collapses whitespace. We rely on this so a paste with
		// extra whitespace or accidental HTML doesn't poison the PageSpeed query.
		$result = $this->page->sanitize_options( [ 'pagespeed_api_key' => "  AIza<script>evil</script>123\n" ] );
		$this->assertSame( 'AIza123', $result['pagespeed_api_key'] );
	}

	public function test_render_page_emits_nothing_for_user_without_manage_capability(): void {
		// Editor has VIEW (read-only access to dashboards) but never MANAGE; the
		// settings form must stay invisible to them. WP's submenu registration
		// also gates the page, but we re-check inside render_page() as defense
		// in depth — this test locks that second check in.
		$editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor_id );
		$this->assertFalse( current_user_can( Capabilities::MANAGE ) );

		ob_start();
		$this->page->render_page();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}
}
