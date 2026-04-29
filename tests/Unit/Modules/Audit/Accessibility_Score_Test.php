<?php
/**
 * Accessibility_Score unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Audit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;
use LEAStudios\Tests\TestCase;

final class Accessibility_Score_Test extends TestCase {

	public function test_can_be_created_with_valid_value(): void {
		$score = new Accessibility_Score( 85 );

		$this->assertSame( 85, $score->value() );
	}

	public function test_can_be_created_with_zero(): void {
		$score = new Accessibility_Score( 0 );

		$this->assertSame( 0, $score->value() );
	}

	public function test_can_be_created_with_hundred(): void {
		$score = new Accessibility_Score( 100 );

		$this->assertSame( 100, $score->value() );
	}

	public function test_throws_exception_when_below_zero(): void {
		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'Score must be between 0 and 100' );

		new Accessibility_Score( -1 );
	}

	public function test_throws_exception_when_above_hundred(): void {
		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'Score must be between 0 and 100' );

		new Accessibility_Score( 101 );
	}

	public function test_is_greater_than(): void {
		$score80 = new Accessibility_Score( 80 );
		$score90 = new Accessibility_Score( 90 );

		$this->assertTrue( $score90->is_greater_than( $score80 ) );
		$this->assertFalse( $score80->is_greater_than( $score90 ) );
	}

	public function test_is_greater_than_returns_false_for_equal(): void {
		$score1 = new Accessibility_Score( 80 );
		$score2 = new Accessibility_Score( 80 );

		$this->assertFalse( $score1->is_greater_than( $score2 ) );
	}

	public function test_equals(): void {
		$score1 = new Accessibility_Score( 80 );
		$score2 = new Accessibility_Score( 80 );
		$score3 = new Accessibility_Score( 90 );

		$this->assertTrue( $score1->equals( $score2 ) );
		$this->assertFalse( $score1->equals( $score3 ) );
	}

	public function test_delta_calculates_difference(): void {
		$score1 = new Accessibility_Score( 80 );
		$score2 = new Accessibility_Score( 90 );

		$this->assertSame( 10, $score2->delta( $score1 ) );
		$this->assertSame( -10, $score1->delta( $score2 ) );
	}

	public function test_grade_returns_correct_label(): void {
		$this->assertSame( 'Excellent', ( new Accessibility_Score( 95 ) )->grade() );
		$this->assertSame( 'Good', ( new Accessibility_Score( 85 ) )->grade() );
		$this->assertSame( 'Needs Improvement', ( new Accessibility_Score( 60 ) )->grade() );
		$this->assertSame( 'Poor', ( new Accessibility_Score( 30 ) )->grade() );
	}
}
