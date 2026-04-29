<?php
/**
 * Trend unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Audit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Trend;
use LEAStudios\Tests\TestCase;

final class Trend_Test extends TestCase {

	public function test_all_cases_exist(): void {
		$this->assertSame( 'improving', Trend::IMPROVING->value );
		$this->assertSame( 'degrading', Trend::DEGRADING->value );
		$this->assertSame( 'stable', Trend::STABLE->value );
	}

	public function test_label_returns_human_readable_string(): void {
		$this->assertSame( 'Improving', Trend::IMPROVING->label() );
		$this->assertSame( 'Degrading', Trend::DEGRADING->label() );
		$this->assertSame( 'Stable', Trend::STABLE->label() );
	}

	public function test_from_delta_returns_improving_for_positive(): void {
		$this->assertSame( Trend::IMPROVING, Trend::from_delta( 5 ) );
	}

	public function test_from_delta_returns_degrading_for_negative(): void {
		$this->assertSame( Trend::DEGRADING, Trend::from_delta( -3 ) );
	}

	public function test_from_delta_returns_stable_for_zero(): void {
		$this->assertSame( Trend::STABLE, Trend::from_delta( 0 ) );
	}

	public function test_all_cases_count(): void {
		$this->assertCount( 3, Trend::cases() );
	}
}
