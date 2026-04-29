<?php
/**
 * Validated http(s) URL value object.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;

/**
 * Immutable URL with normalized scheme/host/trailing-slash.
 *
 * Construction enforces: non-empty after trimming, RFC-valid syntax via
 * `filter_var( ..., FILTER_VALIDATE_URL )`, http or https scheme, and a
 * non-empty host. The trailing slash is stripped so equality comparisons
 * normalize `https://example.com/` and `https://example.com` to the same value.
 */
final class Url_Address implements \Stringable {

	/**
	 * Normalized URL string.
	 *
	 * @var string
	 */
	private readonly string $value;

	/**
	 * Construct from a raw URL string.
	 *
	 * @param string $url Raw URL.
	 *
	 * @throws Validation_Exception When the URL is empty, malformed, or uses a non-http(s) scheme.
	 */
	public function __construct( string $url ) {
		$url = trim( $url );

		if ( '' === $url ) {
			throw new Validation_Exception( 'URL cannot be empty' );
		}

		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new Validation_Exception( 'Invalid URL format' );
		}

		$parsed = wp_parse_url( $url );

		if ( ! is_array( $parsed ) || ! isset( $parsed['host'] ) || '' === $parsed['host'] ) {
			throw new Validation_Exception( 'Invalid URL format' );
		}

		if ( ! isset( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], [ 'http', 'https' ], true ) ) {
			throw new Validation_Exception( 'URL must use http or https scheme' );
		}

		$this->value = rtrim( $url, '/' );
	}

	/**
	 * Get the normalized URL string.
	 *
	 * @return string
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * Compare two `Url_Address` instances by their normalized values.
	 *
	 * @param self $other Other instance.
	 *
	 * @return bool
	 */
	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	/**
	 * Allow casting to string.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->value;
	}
}
