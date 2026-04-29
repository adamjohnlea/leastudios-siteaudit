<?php
/**
 * Transient-backed admin flash notices.
 *
 * @package LEAStudios\SiteAudit\Admin
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Survives the post-redirect-get cycle for admin form submissions.
 *
 * Each user has a single-slot notice keyed by their id; the notice is read
 * and deleted on the next page render. Two types are supported: 'success'
 * and 'error', rendered as standard WordPress admin notice markup.
 */
final class Notice_Service {

	private const TRANSIENT_PREFIX = 'leastudios_siteaudit_notice_';
	private const TTL_SECONDS      = 60;

	/**
	 * Store a notice for the current user. Replaces any existing notice.
	 *
	 * @param string $type    'success' or 'error'.
	 * @param string $message Human-readable message.
	 *
	 * @return void
	 */
	public static function enqueue( string $type, string $message ): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$normalized_type = ( 'error' === $type ) ? 'error' : 'success';

		set_transient(
			self::TRANSIENT_PREFIX . $user_id,
			[
				'type'    => $normalized_type,
				'message' => $message,
			],
			self::TTL_SECONDS
		);
	}

	/**
	 * Render and clear the current user's notice, if any.
	 *
	 * @return void
	 */
	public static function render(): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$key    = self::TRANSIENT_PREFIX . $user_id;
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );

		$type    = ( isset( $notice['type'] ) && 'error' === $notice['type'] ) ? 'error' : 'success';
		$message = (string) $notice['message'];

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
