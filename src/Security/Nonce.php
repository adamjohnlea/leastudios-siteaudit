<?php
/**
 * Nonce helper for secure form/action verification.
 *
 * @package LEAStudios\SiteAudit\Security
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized nonce management. Every action name is prefixed with
 * `leastudios_siteaudit_` to keep nonces from colliding with other plugins.
 */
final class Nonce {

	private const PREFIX = 'leastudios_siteaudit_';

	/**
	 * Create a nonce for a specific action.
	 *
	 * @param string $action The action name (without prefix).
	 * @return string The nonce value.
	 */
	public static function create( string $action ): string {
		return wp_create_nonce( self::PREFIX . $action );
	}

	/**
	 * Verify a nonce for a specific action.
	 *
	 * @param string $nonce  The nonce to verify.
	 * @param string $action The action name (without prefix).
	 * @return bool Whether the nonce is valid.
	 */
	public static function verify( string $nonce, string $action ): bool {
		return false !== wp_verify_nonce( $nonce, self::PREFIX . $action );
	}

	/**
	 * Verify an admin-post / form-submit nonce. Dies on failure.
	 *
	 * @param string $action    The action name (without prefix).
	 * @param string $param_key Request parameter key. Default '_wpnonce'.
	 * @return void
	 */
	public static function check_request( string $action, string $param_key = '_wpnonce' ): void {
		check_admin_referer( self::PREFIX . $action, $param_key );
	}

	/**
	 * Verify an AJAX nonce. Dies on failure.
	 *
	 * @param string $action    The action name (without prefix).
	 * @param string $param_key Request parameter key. Default '_wpnonce'.
	 * @return void
	 */
	public static function check_ajax( string $action, string $param_key = '_wpnonce' ): void {
		check_ajax_referer( self::PREFIX . $action, $param_key );
	}
}
