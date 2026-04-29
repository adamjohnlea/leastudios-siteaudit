<?php
/**
 * Strategy a single audit row was actually executed under.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

/**
 * Distinct from `Audit_Strategy`: that VO models the user's *preference*
 * (`desktop`, `mobile`, or `both`); this VO models the device profile a
 * concrete audit row was run with — always exactly one of `desktop` /
 * `mobile`, since "both" is implemented as two separate audit rows.
 *
 * Persisted in `audits.strategy`.
 */
enum Run_Strategy: string {
	case DESKTOP = 'desktop';
	case MOBILE  = 'mobile';

	/**
	 * Human-readable label for admin UI rendering.
	 *
	 * @return string
	 */
	public function label(): string {
		// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- false positive: $this in enum methods is supported in PHP 8.1+.
		return match ( $this ) {
			self::DESKTOP => 'Desktop',
			self::MOBILE  => 'Mobile',
		};
		// phpcs:enable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
	}
}
