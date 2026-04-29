<?php
/**
 * Direction of score movement over time.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

/**
 * Three-way trend classifier derived from a signed delta.
 *
 * Persisted in `audit_comparisons.trend` and used by `Trend_Calculator` to
 * summarise a time series of audits.
 */
enum Trend: string {
	case IMPROVING = 'improving';
	case DEGRADING = 'degrading';
	case STABLE    = 'stable';

	/**
	 * Human-readable label for admin UI rendering.
	 *
	 * @return string
	 */
	public function label(): string {
		// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- false positive: $this in enum methods is supported in PHP 8.1+.
		return match ( $this ) {
			self::IMPROVING => 'Improving',
			self::DEGRADING => 'Degrading',
			self::STABLE    => 'Stable',
		};
		// phpcs:enable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
	}

	/**
	 * Map a signed integer delta to a trend.
	 *
	 * @param int $delta Signed delta.
	 *
	 * @return self
	 */
	public static function from_delta( int $delta ): self {
		return match ( true ) {
			$delta > 0 => self::IMPROVING,
			$delta < 0 => self::DEGRADING,
			default    => self::STABLE,
		};
	}
}
