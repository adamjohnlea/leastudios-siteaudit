<?php
/**
 * Test stub HTTP client that records the URL it was called with.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Audit;

use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Http_Client_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Http_Response;

/**
 * Stub used by PageSpeed_Api_Client_Test.
 *
 * Captures the URL passed to `get()` and returns a pre-canned response so the
 * test can assert on query-string composition and status-code handling.
 */
final class Captured_Http_Client implements Http_Client_Interface {

	public string $last_url = '';

	public function __construct( private Http_Response $response ) {
	}

	public function get( string $url ): Http_Response {
		$this->last_url = $url;
		return $this->response;
	}
}
