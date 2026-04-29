<?php
/**
 * Maps an Audit_Frequency to its interval in hours.
 *
 * @package LEAStudios\SiteAudit\Modules\Scheduler\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Scheduler\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;

/**
 * Pure helper turning an `Audit_Frequency` into a fixed hour offset.
 *
 * Mirrors the source app's `ScheduledAuditRunner::getIntervalHours()` ladder
 * (24 / 168 / 336 / 720). Extracted as its own class so the dispatcher and
 * future "next-due-at" rendering share a single source of truth.
 */
final class Frequency_Interval {

	/**
	 * Hours between scheduled audits for the given frequency.
	 *
	 * @param Audit_Frequency $frequency Cadence.
	 *
	 * @return int Interval in hours, always positive.
	 */
	public function hours( Audit_Frequency $frequency ): int {
		return match ( $frequency ) {
			Audit_Frequency::DAILY    => 24,
			Audit_Frequency::WEEKLY   => 168,
			Audit_Frequency::BIWEEKLY => 336,
			Audit_Frequency::MONTHLY  => 720,
		};
	}
}
