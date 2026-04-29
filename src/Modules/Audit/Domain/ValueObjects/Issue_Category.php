<?php
/**
 * Coarse category bucket for an accessibility issue.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

/**
 * Six themed buckets plus an `OTHER` catch-all, used to group issues in reports.
 *
 * Mapping from raw Lighthouse audit ids to a category lives elsewhere; this VO
 * just enumerates the destination buckets. The string values are persisted in
 * `issues.category` and must remain stable.
 */
enum Issue_Category: string {
	case COLOR_CONTRAST = 'color_contrast';
	case ARIA           = 'aria';
	case FORMS          = 'forms';
	case IMAGES         = 'images';
	case NAVIGATION     = 'navigation';
	case TABLES         = 'tables';
	case OTHER          = 'other';

	/**
	 * Human-readable label for admin UI rendering.
	 *
	 * @return string
	 */
	public function label(): string {
		// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- false positive: $this in enum methods is supported in PHP 8.1+.
		return match ( $this ) {
			self::COLOR_CONTRAST => 'Color Contrast',
			self::ARIA           => 'ARIA',
			self::FORMS          => 'Forms',
			self::IMAGES         => 'Images',
			self::NAVIGATION     => 'Navigation',
			self::TABLES         => 'Tables',
			self::OTHER          => 'Other',
		};
		// phpcs:enable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
	}
}
