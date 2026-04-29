<?php
/**
 * Domain validation exception.
 *
 * Thrown by value objects and application services when caller-supplied input
 * violates a domain invariant. Caught and rendered by admin/REST controllers.
 *
 * @package LEAStudios\SiteAudit\Shared\Exceptions
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Shared\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps `\InvalidArgumentException` so call sites can catch domain validation
 * failures distinctly from generic argument errors.
 */
class Validation_Exception extends \InvalidArgumentException {
}
