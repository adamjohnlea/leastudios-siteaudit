<?php
/**
 * Captured HTTP response (status + body).
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable snapshot of an HTTP response.
 *
 * Used as the abstraction between the WordPress-specific transport
 * (`Wp_Http_Client`) and the PageSpeed client, so the latter can be tested
 * against a stub without touching the network.
 */
final class Http_Response {

	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	private readonly int $status_code;

	/**
	 * Raw response body.
	 *
	 * @var string
	 */
	private readonly string $body;

	/**
	 * Constructor.
	 *
	 * @param int    $status_code HTTP status code.
	 * @param string $body        Raw response body.
	 */
	public function __construct( int $status_code, string $body ) {
		$this->status_code = $status_code;
		$this->body        = $body;
	}

	/**
	 * HTTP status code.
	 *
	 * @return int
	 */
	public function status_code(): int {
		return $this->status_code;
	}

	/**
	 * Raw response body.
	 *
	 * @return string
	 */
	public function body(): string {
		return $this->body;
	}
}
