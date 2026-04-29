<?php
/**
 * `wp_remote_get()` adapter implementing Http_Client_Interface.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps WordPress's HTTP API so the rest of the audit module never touches
 * `wp_remote_get()` directly.
 *
 * PageSpeed Insights responses for slow pages can take 30–60 seconds, so the
 * default timeout matches the source app's curl client at 60 seconds.
 */
final class Wp_Http_Client implements Http_Client_Interface {

	private const TIMEOUT_SECONDS = 60;

	/**
	 * Perform an HTTP GET via WordPress's HTTP API.
	 *
	 * @param string $url Full URL including query string.
	 *
	 * @return Http_Response
	 *
	 * @throws Api_Exception When the request fails at the transport level.
	 */
	public function get( string $url ): Http_Response {
		$response = wp_remote_get(
			$url,
			[
				'timeout'    => self::TIMEOUT_SECONDS,
				'user-agent' => 'LEAStudiosSiteAudit/' . LEASTUDIOS_SITEAUDIT_VERSION,
				'sslverify'  => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new Api_Exception( 'HTTP request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = (string) wp_remote_retrieve_body( $response );

		return new Http_Response( $status_code, $body );
	}
}
