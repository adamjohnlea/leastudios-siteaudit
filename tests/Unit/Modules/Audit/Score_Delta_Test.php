<?php
/**
 * Score_Delta unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Audit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Score_Delta;
use LEAStudios\Tests\TestCase;

final class Score_Delta_Test extends TestCase {

	public function test_positive_delta_indicates_improvement(): void {
		$delta = new Score_Delta( 10 );

		$this->assertSame( 10, $delta->value() );
		$this->assertTrue( $delta->is_improvement() );
		$this->assertFalse( $delta->is_degradation() );
		$this->assertFalse( $delta->is_stable() );
	}

	public function test_negative_delta_indicates_degradation(): void {
		$delta = new Score_Delta( -5 );

		$this->assertSame( -5, $delta->value() );
		$this->assertFalse( $delta->is_improvement() );
		$this->assertTrue( $delta->is_degradation() );
		$this->assertFalse( $delta->is_stable() );
	}

	public function test_zero_delta_indicates_stable(): void {
		$delta = new Score_Delta( 0 );

		$this->assertSame( 0, $delta->value() );
		$this->assertFalse( $delta->is_improvement() );
		$this->assertFalse( $delta->is_degradation() );
		$this->assertTrue( $delta->is_stable() );
	}

	public function test_absolute_value(): void {
		$this->assertSame( 10, ( new Score_Delta( 10 ) )->absolute_value() );
		$this->assertSame( 5, ( new Score_Delta( -5 ) )->absolute_value() );
		$this->assertSame( 0, ( new Score_Delta( 0 ) )->absolute_value() );
	}

	public function test_direction_label(): void {
		$this->assertSame( '+10', ( new Score_Delta( 10 ) )->direction_label() );
		$this->assertSame( '-5', ( new Score_Delta( -5 ) )->direction_label() );
		$this->assertSame( '0', ( new Score_Delta( 0 ) )->direction_label() );
	}
}
