<?php
/**
 * How often a URL should be re-audited.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

/**
 * Cadence at which the scheduled-audit runner re-audits a URL.
 *
 * The string values are persisted in `urls.audit_frequency` and must remain
 * stable across versions. Use `tryFrom()` to safely coerce caller input;
 * `from()` throws on unknown values.
 */
enum Audit_Frequency: string {
	case DAILY    = 'daily';
	case WEEKLY   = 'weekly';
	case BIWEEKLY = 'biweekly';
	case MONTHLY  = 'monthly';

	/**
	 * Human-readable label for admin UI rendering.
	 *
	 * @return string
	 */
	public function label(): string {
		// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- false positive: $this in enum methods is supported in PHP 8.1+.
		return match ( $this ) {
			self::DAILY    => 'Daily',
			self::WEEKLY   => 'Weekly',
			self::BIWEEKLY => 'Biweekly',
			self::MONTHLY  => 'Monthly',
		};
		// phpcs:enable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
	}
}
