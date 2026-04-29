<?php
/**
 * Which PageSpeed device profiles a URL is audited under.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

/**
 * Selects desktop, mobile, or both PageSpeed strategies per audit cycle.
 *
 * The string values are persisted in `urls.audit_strategy` and `audits.strategy`.
 */
enum Audit_Strategy: string {
	case DESKTOP = 'desktop';
	case MOBILE  = 'mobile';
	case BOTH    = 'both';

	/**
	 * Human-readable label for admin UI rendering.
	 *
	 * @return string
	 */
	public function label(): string {
		// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- false positive: $this in enum methods is supported in PHP 8.1+.
		return match ( $this ) {
			self::DESKTOP => 'Desktop only',
			self::MOBILE  => 'Mobile only',
			self::BOTH    => 'Desktop & Mobile',
		};
		// phpcs:enable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
	}
}
