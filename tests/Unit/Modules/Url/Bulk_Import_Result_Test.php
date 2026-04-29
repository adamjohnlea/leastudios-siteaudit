<?php
/**
 * Bulk_Import_Result value object tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Url;

use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Bulk_Import_Result;
use LEAStudios\Tests\TestCase;

final class Bulk_Import_Result_Test extends TestCase {

	public function test_total_processed_returns_sum(): void {
		$result = new Bulk_Import_Result(
			3,
			2,
			[
				[
					'line'  => 1,
					'url'   => 'bad-url',
					'error' => 'Invalid URL format',
				],
			]
		);

		$this->assertSame( 6, $result->total_processed() );
	}

	public function test_has_errors_returns_true_when_errors_exist(): void {
		$result = new Bulk_Import_Result(
			1,
			0,
			[
				[
					'line'  => 2,
					'url'   => 'not-valid',
					'error' => 'Invalid URL format',
				],
			]
		);

		$this->assertTrue( $result->has_errors() );
	}

	public function test_has_errors_returns_false_when_no_errors(): void {
		$result = new Bulk_Import_Result( 5, 1, [] );
		$this->assertFalse( $result->has_errors() );
	}
}
