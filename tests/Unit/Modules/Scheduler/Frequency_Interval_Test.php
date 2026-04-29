<?php
/**
 * Frequency_Interval unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Scheduler;

use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Frequency_Interval;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\Tests\TestCase;

final class Frequency_Interval_Test extends TestCase {

	public function test_maps_each_frequency_to_documented_hour_offset(): void {
		$interval = new Frequency_Interval();

		$this->assertSame( 24, $interval->hours( Audit_Frequency::DAILY ) );
		$this->assertSame( 168, $interval->hours( Audit_Frequency::WEEKLY ) );
		$this->assertSame( 336, $interval->hours( Audit_Frequency::BIWEEKLY ) );
		$this->assertSame( 720, $interval->hours( Audit_Frequency::MONTHLY ) );
	}
}
