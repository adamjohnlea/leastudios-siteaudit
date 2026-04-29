<?php
/**
 * PageSpeed Insights API client contract.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Domain-shaped interface for the PageSpeed Insights API.
 *
 * `Audit_Service` depends on this rather than the concrete client so it can
 * be tested with a mock. The HTTP transport is hidden behind the
 * implementation.
 */
interface PageSpeed_Client_Interface {

	/**
	 * Run a PageSpeed audit against the given URL.
	 *
	 * @param string $url      Target URL.
	 * @param string $strategy `desktop` or `mobile`.
	 *
	 * @return Api_Response
	 *
	 * @throws Api_Exception        When the API returns a non-200 / non-429 status.
	 * @throws Rate_Limit_Exception When the API returns 429.
	 */
	public function run_audit( string $url, string $strategy = 'desktop' ): Api_Response;
}
