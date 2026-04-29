<?php
/**
 * PageSpeed Insights API client.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to https://www.googleapis.com/pagespeedonline/v5/runPagespeed.
 *
 * Builds the query string with the configured key (when set), forwards
 * the GET to the injected {@see Http_Client_Interface}, and maps non-200
 * responses to the matching exception type. The 429 mapping is what
 * allows {@see \LEAStudios\SiteAudit\Modules\Audit\Infrastructure\RateLimiting\Retry_Strategy}
 * to drive backoff in `Audit_Service`.
 */
final class PageSpeed_Api_Client implements PageSpeed_Client_Interface {

	private const API_URL = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

	/**
	 * HTTP transport.
	 *
	 * @var Http_Client_Interface
	 */
	private readonly Http_Client_Interface $http_client;

	/**
	 * PageSpeed API key (empty string disables key auth — useful in tests).
	 *
	 * @var string
	 */
	private readonly string $api_key;

	/**
	 * Constructor.
	 *
	 * @param Http_Client_Interface $http_client HTTP transport.
	 * @param string                $api_key     PageSpeed API key.
	 */
	public function __construct( Http_Client_Interface $http_client, string $api_key = '' ) {
		$this->http_client = $http_client;
		$this->api_key     = $api_key;
	}

	/**
	 * Run a PageSpeed audit.
	 *
	 * @param string $url      Target URL.
	 * @param string $strategy `desktop` or `mobile`.
	 *
	 * @return Api_Response
	 *
	 * @throws Api_Exception        When the API returns a non-200 / non-429 status.
	 * @throws Rate_Limit_Exception When the API returns 429.
	 */
	public function run_audit( string $url, string $strategy = 'desktop' ): Api_Response {
		$params = [
			'url'      => $url,
			'category' => 'accessibility',
			'strategy' => $strategy,
		];

		if ( '' !== $this->api_key ) {
			$params['key'] = $this->api_key;
		}

		$query_string = http_build_query( $params );
		$response     = $this->http_client->get( self::API_URL . '?' . $query_string );

		if ( 429 === $response->status_code() ) {
			throw new Rate_Limit_Exception( 'Rate limit exceeded', 429 );
		}

		if ( 200 !== $response->status_code() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Second argument is the integer status code, not user-facing output.
			throw new Api_Exception( 'API request failed', $response->status_code() );
		}

		return Api_Response::from_json( $response->body() );
	}
}
