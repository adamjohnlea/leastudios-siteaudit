<?php
/**
 * HTTP transport contract.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal GET-only HTTP client used by the PageSpeed client.
 *
 * In production this is wired to {@see Wp_Http_Client}; in unit tests it is
 * replaced by a stub so behaviour around status codes can be exercised
 * without hitting the real Google API.
 */
interface Http_Client_Interface {

	/**
	 * Perform an HTTP GET request.
	 *
	 * @param string $url Full URL including query string.
	 *
	 * @return Http_Response
	 *
	 * @throws Api_Exception When the transport itself fails (connection / DNS / timeout).
	 */
	public function get( string $url ): Http_Response;
}
