<?php
/**
 * Severity classification for an accessibility issue.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

/**
 * Four-level severity ladder, mirroring axe-core / Lighthouse conventions.
 *
 * `weight()` provides a numeric ordering used by sorting and reporting code.
 * The string values are persisted in `issues.severity` and must remain stable.
 */
enum Issue_Severity: string {
	case CRITICAL = 'critical';
	case SERIOUS  = 'serious';
	case MODERATE = 'moderate';
	case MINOR    = 'minor';

	/**
	 * Human-readable label for admin UI rendering.
	 *
	 * @return string
	 */
	public function label(): string {
		// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- false positive: $this in enum methods is supported in PHP 8.1+.
		return match ( $this ) {
			self::CRITICAL => 'Critical',
			self::SERIOUS  => 'Serious',
			self::MODERATE => 'Moderate',
			self::MINOR    => 'Minor',
		};
		// phpcs:enable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
	}

	/**
	 * Numeric weight for sort/report ordering (higher = more severe).
	 *
	 * @return int
	 */
	public function weight(): int {
		// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- false positive: $this in enum methods is supported in PHP 8.1+.
		return match ( $this ) {
			self::CRITICAL => 4,
			self::SERIOUS  => 3,
			self::MODERATE => 2,
			self::MINOR    => 1,
		};
		// phpcs:enable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
	}
}
