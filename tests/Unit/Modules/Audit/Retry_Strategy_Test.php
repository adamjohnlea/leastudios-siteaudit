<?php
/**
 * Retry_Strategy unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Audit;

use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\RateLimiting\Retry_Strategy;
use LEAStudios\Tests\TestCase;

final class Retry_Strategy_Test extends TestCase {

	public function test_should_retry_returns_true_when_under_max_retries(): void {
		$strategy = new Retry_Strategy( max_retries: 3 );

		$this->assertTrue( $strategy->should_retry( 0 ) );
		$this->assertTrue( $strategy->should_retry( 1 ) );
		$this->assertTrue( $strategy->should_retry( 2 ) );
	}

	public function test_should_retry_returns_false_when_at_max_retries(): void {
		$strategy = new Retry_Strategy( max_retries: 3 );

		$this->assertFalse( $strategy->should_retry( 3 ) );
	}

	public function test_should_retry_returns_false_when_over_max_retries(): void {
		$strategy = new Retry_Strategy( max_retries: 3 );

		$this->assertFalse( $strategy->should_retry( 5 ) );
	}

	public function test_get_delay_returns_exponential_backoff(): void {
		// Pass an explicit large max so the test demonstrates the
		// exponential pattern below the cap regardless of the runtime
		// default (which was lowered to 5s to avoid blocking AS workers).
		$strategy = new Retry_Strategy( max_retries: 5, base_delay_ms: 1000, max_delay_ms: 30000 );

		$this->assertSame( 1000, $strategy->delay_ms( 0 ) );
		$this->assertSame( 2000, $strategy->delay_ms( 1 ) );
		$this->assertSame( 4000, $strategy->delay_ms( 2 ) );
		$this->assertSame( 8000, $strategy->delay_ms( 3 ) );
	}

	public function test_get_delay_respects_max_delay(): void {
		$strategy = new Retry_Strategy( max_retries: 10, base_delay_ms: 1000, max_delay_ms: 5000 );

		$this->assertSame( 5000, $strategy->delay_ms( 5 ) );
	}

	public function test_default_values(): void {
		$strategy = new Retry_Strategy();

		$this->assertTrue( $strategy->should_retry( 0 ) );
		$this->assertTrue( $strategy->should_retry( 1 ) );
		$this->assertTrue( $strategy->should_retry( 2 ) );
		$this->assertFalse( $strategy->should_retry( 3 ) );
	}

	public function test_get_max_retries(): void {
		$strategy = new Retry_Strategy( max_retries: 5 );

		$this->assertSame( 5, $strategy->max_retries() );
	}
}
