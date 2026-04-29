<?php
/**
 * PageSpeed_Api_Client unit tests using a stub HTTP transport.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Audit;

use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Api_Exception;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Http_Response;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\PageSpeed_Api_Client;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Rate_Limit_Exception;
use LEAStudios\Tests\TestCase;

require_once __DIR__ . '/Captured_Http_Client.php';

final class PageSpeed_Api_Client_Test extends TestCase {

	public function test_run_audit_builds_query_string_with_url_category_and_strategy(): void {
		$transport = new Captured_Http_Client(
			new Http_Response( 200, $this->minimal_response_json( 80 ) )
		);

		$client = new PageSpeed_Api_Client( $transport, 'test-key' );
		$client->run_audit( 'https://example.com', 'mobile' );

		$this->assertStringContainsString( 'url=https%3A%2F%2Fexample.com', $transport->last_url );
		$this->assertStringContainsString( 'category=accessibility', $transport->last_url );
		$this->assertStringContainsString( 'strategy=mobile', $transport->last_url );
		$this->assertStringContainsString( 'key=test-key', $transport->last_url );
	}

	public function test_run_audit_omits_key_when_blank(): void {
		$transport = new Captured_Http_Client(
			new Http_Response( 200, $this->minimal_response_json( 80 ) )
		);

		$client = new PageSpeed_Api_Client( $transport, '' );
		$client->run_audit( 'https://example.com' );

		$this->assertStringNotContainsString( 'key=', $transport->last_url );
	}

	public function test_run_audit_returns_parsed_api_response_on_200(): void {
		$transport = new Captured_Http_Client(
			new Http_Response( 200, $this->minimal_response_json( 73 ) )
		);

		$response = ( new PageSpeed_Api_Client( $transport ) )->run_audit( 'https://example.com' );

		$this->assertSame( 73, $response->score() );
	}

	public function test_run_audit_throws_rate_limit_exception_on_429(): void {
		$transport = new Captured_Http_Client( new Http_Response( 429, '' ) );

		$this->expectException( Rate_Limit_Exception::class );

		( new PageSpeed_Api_Client( $transport ) )->run_audit( 'https://example.com' );
	}

	public function test_run_audit_throws_api_exception_on_500(): void {
		$transport = new Captured_Http_Client( new Http_Response( 500, 'oops' ) );

		$this->expectException( Api_Exception::class );

		( new PageSpeed_Api_Client( $transport ) )->run_audit( 'https://example.com' );
	}

	private function minimal_response_json( int $score ): string {
		return (string) wp_json_encode(
			[
				'lighthouseResult' => [
					'categories' => [
						'accessibility' => [ 'score' => $score / 100 ],
					],
					'audits'     => [],
				],
			]
		);
	}
}
