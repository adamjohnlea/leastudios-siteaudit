<?php
/**
 * Rate-limit signal from the PageSpeed API.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown for HTTP 429 responses to distinguish them from other API failures.
 *
 * Callers that catch this should consult `Retry_Strategy` rather than fail-fast.
 */
final class Rate_Limit_Exception extends Api_Exception {
}
