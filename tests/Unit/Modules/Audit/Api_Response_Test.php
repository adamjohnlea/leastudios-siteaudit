<?php
/**
 * Api_Response unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Audit;

use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Api_Response;
use LEAStudios\Tests\TestCase;

final class Api_Response_Test extends TestCase {

	public function test_from_json_extracts_score_and_rescales_to_percentage(): void {
		$json = wp_json_encode(
			[
				'lighthouseResult' => [
					'categories' => [
						'accessibility' => [ 'score' => 0.87 ],
					],
					'audits'     => [],
				],
			]
		);

		$response = Api_Response::from_json( $json );

		$this->assertSame( 87, $response->score() );
	}

	public function test_from_json_returns_zero_score_when_missing(): void {
		$json     = wp_json_encode( [ 'lighthouseResult' => [ 'audits' => [] ] ] );
		$response = Api_Response::from_json( $json );

		$this->assertSame( 0, $response->score() );
	}

	public function test_from_json_filters_to_binary_failing_audits(): void {
		$json = wp_json_encode(
			[
				'lighthouseResult' => [
					'categories' => [ 'accessibility' => [ 'score' => 0.5 ] ],
					'audits'     => [
						'image-alt'      => [
							'id'               => 'image-alt',
							'title'            => 'Images have alt text',
							'description'      => 'Some images are missing alt text',
							'score'            => 0,
							'scoreDisplayMode' => 'binary',
							'helpUrl'          => 'https://example.com/help',
						],
						'color-contrast' => [
							'id'               => 'color-contrast',
							'title'            => 'Color contrast is sufficient',
							'description'      => 'Background and foreground colors do not contrast',
							'score'            => 1,
							'scoreDisplayMode' => 'binary',
						],
						'manual-thing'   => [
							'id'               => 'manual-thing',
							'title'            => 'Manual check',
							'description'      => 'Reviewer must verify',
							'score'            => null,
							'scoreDisplayMode' => 'manual',
						],
					],
				],
			]
		);

		$response = Api_Response::from_json( $json );
		$audits   = $response->failing_audits();

		$this->assertCount( 1, $audits );
		$this->assertSame( 'image-alt', $audits[0]['id'] );
		$this->assertSame( 'https://example.com/help', $audits[0]['helpUrl'] );
	}

	public function test_from_json_extracts_node_selectors_from_details_items(): void {
		$json = wp_json_encode(
			[
				'lighthouseResult' => [
					'categories' => [ 'accessibility' => [ 'score' => 0.5 ] ],
					'audits'     => [
						'image-alt' => [
							'id'               => 'image-alt',
							'title'            => 'Images have alt text',
							'description'      => 'Some images are missing alt text',
							'score'            => 0,
							'scoreDisplayMode' => 'binary',
							'details'          => [
								'items' => [
									[ 'node' => [ 'selector' => 'img.hero' ] ],
									[ 'node' => [ 'selector' => 'img.product' ] ],
									[ 'something_else' => true ],
								],
							],
						],
					],
				],
			]
		);

		$response = Api_Response::from_json( $json );
		$audits   = $response->failing_audits();

		$this->assertCount( 2, $audits[0]['details'] );
		$this->assertSame( 'img.hero', $audits[0]['details'][0]['selector'] );
		$this->assertSame( 'img.product', $audits[0]['details'][1]['selector'] );
	}

	public function test_from_json_throws_on_invalid_json(): void {
		$this->expectException( \JsonException::class );

		Api_Response::from_json( '{not json' );
	}

	public function test_raw_json_round_trips_input(): void {
		$json     = wp_json_encode( [ 'lighthouseResult' => [ 'audits' => [] ] ] );
		$response = Api_Response::from_json( $json );

		$this->assertSame( $json, $response->raw_json() );
	}
}
