<?php
/**
 * Generic PageSpeed API failure.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown by the HTTP/API layer for non-rate-limit failures.
 *
 * Subclassed by `Rate_Limit_Exception` for HTTP 429 cases that should drive
 * `Retry_Strategy`-based retries.
 */
class Api_Exception extends \RuntimeException {
}
