<?php
/**
 * Lifecycle status of an audit row.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

/**
 * The four states an audit row can occupy.
 *
 * `pending` and `in_progress` are working states; `completed` and `failed` are
 * terminal. The string values are persisted in `audits.status` and must remain
 * stable across versions.
 */
enum Audit_Status: string {
	case PENDING     = 'pending';
	case IN_PROGRESS = 'in_progress';
	case COMPLETED   = 'completed';
	case FAILED      = 'failed';

	/**
	 * Human-readable label for admin UI rendering.
	 *
	 * @return string
	 */
	public function label(): string {
		// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- false positive: $this in enum methods is supported in PHP 8.1+.
		return match ( $this ) {
			self::PENDING     => 'Pending',
			self::IN_PROGRESS => 'In Progress',
			self::COMPLETED   => 'Completed',
			self::FAILED      => 'Failed',
		};
		// phpcs:enable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
	}

	/**
	 * Whether the status is terminal (no further transitions).
	 *
	 * @return bool
	 */
	public function is_terminal(): bool {
		// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- false positive: $this in enum methods is supported in PHP 8.1+.
		return match ( $this ) {
			self::COMPLETED, self::FAILED => true,
			default                       => false,
		};
		// phpcs:enable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
	}
}
