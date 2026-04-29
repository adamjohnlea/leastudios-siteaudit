<?php
/**
 * Plugin capability registration.
 *
 * @package LEAStudios\SiteAudit
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the two custom capabilities used to gate admin pages and mutations.
 *
 * - `manage_leastudios_siteaudit` — write access (create/update/delete projects,
 *   URLs, settings; trigger manual audits; manage subscriptions). Granted to
 *   Administrator on activation.
 * - `view_leastudios_siteaudit` — read access (dashboards, reports). Granted
 *   to Administrator and Editor on activation.
 *
 * Site owners may reassign these to other roles via any role-management plugin.
 */
final class Capabilities {

	public const MANAGE = 'manage_leastudios_siteaudit';
	public const VIEW   = 'view_leastudios_siteaudit';

	/**
	 * Add capabilities to default roles.
	 *
	 * @return void
	 */
	public static function add(): void {
		$administrator = get_role( 'administrator' );
		if ( null !== $administrator ) {
			$administrator->add_cap( self::MANAGE );
			$administrator->add_cap( self::VIEW );
		}

		$editor = get_role( 'editor' );
		if ( null !== $editor ) {
			$editor->add_cap( self::VIEW );
		}
	}

	/**
	 * Remove capabilities from all roles.
	 *
	 * @return void
	 */
	public static function remove(): void {
		global $wp_roles;

		if ( ! $wp_roles instanceof \WP_Roles ) {
			return;
		}

		foreach ( array_keys( $wp_roles->roles ) as $role_name ) {
			$role = get_role( (string) $role_name );
			if ( null === $role ) {
				continue;
			}

			$role->remove_cap( self::MANAGE );
			$role->remove_cap( self::VIEW );
		}
	}
}
