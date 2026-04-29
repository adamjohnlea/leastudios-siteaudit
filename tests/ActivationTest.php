<?php
/**
 * Activation integration tests: tables, capabilities, options.
 *
 * @package LEAStudios\SiteAudit
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests;

use LEAStudios\SiteAudit\Activation;
use LEAStudios\SiteAudit\Capabilities;
use LEAStudios\SiteAudit\Database\Schema;
use LEAStudios\Tests\TestCase;

class ActivationTest extends TestCase {

	public function test_activation_creates_every_plugin_table(): void {
		( new Activation() )->run();

		global $wpdb;

		$tables = [
			Schema::TABLE_PROJECTS,
			Schema::TABLE_URLS,
			Schema::TABLE_AUDITS,
			Schema::TABLE_ISSUES,
			Schema::TABLE_AUDIT_COMPARISONS,
			Schema::TABLE_NOTIFICATIONS,
			Schema::TABLE_EMAIL_SUBSCRIPTIONS,
		];

		foreach ( $tables as $table ) {
			$prefixed = Schema::table( $table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$found = $wpdb->get_var( "SHOW TABLES LIKE '{$prefixed}'" );
			$this->assertSame( $prefixed, $found, "Expected table {$prefixed} to exist." );
		}
	}

	public function test_activation_grants_capabilities_to_administrator_and_editor(): void {
		( new Activation() )->run();

		$administrator = get_role( 'administrator' );
		$editor        = get_role( 'editor' );

		$this->assertNotNull( $administrator );
		$this->assertNotNull( $editor );

		$this->assertTrue( $administrator->has_cap( Capabilities::MANAGE ) );
		$this->assertTrue( $administrator->has_cap( Capabilities::VIEW ) );

		$this->assertFalse( $editor->has_cap( Capabilities::MANAGE ) );
		$this->assertTrue( $editor->has_cap( Capabilities::VIEW ) );
	}

	public function test_activation_seeds_default_options_only_when_unset(): void {
		delete_option( 'leastudios_siteaudit_options' );

		( new Activation() )->run();

		$options = get_option( 'leastudios_siteaudit_options' );

		$this->assertIsArray( $options );
		$this->assertSame( '', $options['pagespeed_api_key'] );
		$this->assertSame( 'weekly', $options['default_audit_frequency'] );
		$this->assertSame( 'both', $options['default_audit_strategy'] );

		// Re-running activation must not clobber an existing customised option.
		update_option( 'leastudios_siteaudit_options', array_merge( $options, [ 'pagespeed_api_key' => 'set-by-user' ] ) );
		( new Activation() )->run();

		$reloaded = get_option( 'leastudios_siteaudit_options' );
		$this->assertSame( 'set-by-user', $reloaded['pagespeed_api_key'] );
	}

	public function test_capabilities_remove_clears_caps_from_all_roles(): void {
		( new Activation() )->run();

		Capabilities::remove();

		$administrator = get_role( 'administrator' );
		$editor        = get_role( 'editor' );

		$this->assertNotNull( $administrator );
		$this->assertNotNull( $editor );

		$this->assertFalse( $administrator->has_cap( Capabilities::MANAGE ) );
		$this->assertFalse( $administrator->has_cap( Capabilities::VIEW ) );
		$this->assertFalse( $editor->has_cap( Capabilities::VIEW ) );
	}
}
