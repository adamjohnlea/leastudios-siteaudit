<?php
/**
 * Audit_Service unit tests (mocked dependencies).
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Audit;

use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Audit_Pipeline;
use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Audit_Service;
use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Comparison_Service;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit_Comparison;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Comparison_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Issue_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Api_Exception;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Api_Response;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\PageSpeed_Client_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Rate_Limit_Exception;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\RateLimiting\Retry_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Url_Address;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;
use LEAStudios\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class Audit_Service_Test extends TestCase {

	private Url_Repository_Interface&MockObject $url_repository;

	private Audit_Repository_Interface&MockObject $audit_repository;

	private Issue_Repository_Interface&MockObject $issue_repository;

	private PageSpeed_Client_Interface&MockObject $pagespeed_client;

	private Audit_Comparison_Repository_Interface&MockObject $comparison_repository;

	private Audit_Service $service;

	public function set_up(): void {
		parent::set_up();

		$this->url_repository        = $this->createMock( Url_Repository_Interface::class );
		$this->audit_repository      = $this->createMock( Audit_Repository_Interface::class );
		$this->issue_repository      = $this->createMock( Issue_Repository_Interface::class );
		$this->pagespeed_client      = $this->createMock( PageSpeed_Client_Interface::class );
		$this->comparison_repository = $this->createMock( Audit_Comparison_Repository_Interface::class );

		$pipeline = new Audit_Pipeline(
			$this->audit_repository,
			$this->issue_repository,
			$this->pagespeed_client,
			new Retry_Strategy( max_retries: 3, base_delay_ms: 0 ),
			new Comparison_Service(),
			$this->comparison_repository,
		);

		$this->service = new Audit_Service(
			$this->url_repository,
			$this->audit_repository,
			$pipeline
		);
	}

	public function test_run_audit_creates_completed_audit_on_success(): void {
		$url = $this->make_url( 1 );
		$this->url_repository->method( 'find_by_id' )->with( 1 )->willReturn( $url );
		$this->url_repository->method( 'update' )->willReturnArgument( 0 );

		$api_response = $this->make_api_response( 85 );
		$this->pagespeed_client->method( 'run_audit' )->willReturn( $api_response );

		$this->audit_repository->method( 'save' )->willReturnCallback(
			static function ( Audit $audit ): Audit {
				$audit->set_id( 1 );
				return $audit;
			}
		);

		$this->audit_repository->method( 'update' )->willReturnCallback(
			static fn( Audit $audit ): Audit => $audit
		);

		$this->issue_repository->method( 'save_many' )->willReturnArgument( 0 );

		$audits = $this->service->run_audit( 1 );
		$audit  = $audits[0];

		$this->assertSame( 85, $audit->score()->value() );
		$this->assertSame( Audit_Status::COMPLETED, $audit->status() );
	}

	public function test_run_audit_throws_exception_when_url_not_found(): void {
		$this->url_repository->method( 'find_by_id' )->willReturn( null );

		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'URL not found' );

		$this->service->run_audit( 999 );
	}

	public function test_run_audit_creates_failed_audit_on_api_error(): void {
		$url = $this->make_url( 1 );
		$this->url_repository->method( 'find_by_id' )->with( 1 )->willReturn( $url );
		$this->url_repository->method( 'update' )->willReturnArgument( 0 );

		$this->pagespeed_client->method( 'run_audit' )->willThrowException( new Api_Exception( 'API error' ) );

		$this->audit_repository->method( 'save' )->willReturnCallback(
			static function ( Audit $audit ): Audit {
				$audit->set_id( 1 );
				return $audit;
			}
		);

		$this->audit_repository
			->expects( $this->once() )
			->method( 'update' )
			->willReturnCallback( static fn( Audit $audit ): Audit => $audit );

		$audits = $this->service->run_audit( 1 );
		$audit  = $audits[0];

		$this->assertSame( Audit_Status::FAILED, $audit->status() );
		$this->assertSame( 'API error', $audit->error_message() );
	}

	public function test_run_audit_retries_on_rate_limit(): void {
		$url = $this->make_url( 1 );
		$this->url_repository->method( 'find_by_id' )->with( 1 )->willReturn( $url );
		$this->url_repository->method( 'update' )->willReturnArgument( 0 );

		$api_response = $this->make_api_response( 90 );

		$call_count = 0;
		$this->pagespeed_client->method( 'run_audit' )->willReturnCallback(
			static function () use ( &$call_count, $api_response ): Api_Response {
				++$call_count;
				if ( $call_count < 3 ) {
					throw new Rate_Limit_Exception( 'Rate limit exceeded' );
				}
				return $api_response;
			}
		);

		$this->audit_repository->method( 'save' )->willReturnCallback(
			static function ( Audit $audit ): Audit {
				$audit->set_id( 1 );
				return $audit;
			}
		);

		$this->audit_repository->method( 'update' )->willReturnCallback(
			static fn( Audit $audit ): Audit => $audit
		);

		$this->issue_repository->method( 'save_many' )->willReturnArgument( 0 );

		$audits = $this->service->run_audit( 1 );
		$audit  = $audits[0];

		$this->assertSame( Audit_Status::COMPLETED, $audit->status() );
		$this->assertSame( 90, $audit->score()->value() );
		$this->assertSame( 2, $audit->retry_count() );
	}

	public function test_run_audit_fails_after_max_retries(): void {
		$url = $this->make_url( 1 );
		$this->url_repository->method( 'find_by_id' )->with( 1 )->willReturn( $url );
		$this->url_repository->method( 'update' )->willReturnArgument( 0 );

		$this->pagespeed_client->method( 'run_audit' )
			->willThrowException( new Rate_Limit_Exception( 'Rate limit exceeded' ) );

		$this->audit_repository->method( 'save' )->willReturnCallback(
			static function ( Audit $audit ): Audit {
				$audit->set_id( 1 );
				return $audit;
			}
		);

		$this->audit_repository->method( 'update' )->willReturnCallback(
			static fn( Audit $audit ): Audit => $audit
		);

		$audits = $this->service->run_audit( 1 );
		$audit  = $audits[0];

		$this->assertSame( Audit_Status::FAILED, $audit->status() );
		$this->assertStringContainsString( 'Rate limit exceeded', (string) $audit->error_message() );
		$this->assertSame( 3, $audit->retry_count() );
	}

	public function test_run_audit_extracts_issues_from_response(): void {
		$url = $this->make_url( 1 );
		$this->url_repository->method( 'find_by_id' )->with( 1 )->willReturn( $url );
		$this->url_repository->method( 'update' )->willReturnArgument( 0 );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture file, not a remote URL.
		$json         = (string) file_get_contents( __DIR__ . '/fixtures/valid_response.json' );
		$api_response = Api_Response::from_json( $json );
		$this->pagespeed_client->method( 'run_audit' )->willReturn( $api_response );

		$this->audit_repository->method( 'save' )->willReturnCallback(
			static function ( Audit $audit ): Audit {
				$audit->set_id( 1 );
				return $audit;
			}
		);

		$this->audit_repository->method( 'update' )->willReturnCallback(
			static fn( Audit $audit ): Audit => $audit
		);

		$saved_issues = [];
		$this->issue_repository->method( 'save_many' )->willReturnCallback(
			static function ( array $issues ) use ( &$saved_issues ): array {
				$saved_issues = $issues;
				return $issues;
			}
		);

		$this->service->run_audit( 1 );

		$this->assertCount( 2, $saved_issues );

		$this->assertSame( 'Background and foreground colors do not have a sufficient contrast ratio.', $saved_issues[0]->title() );
		$this->assertSame( 'Low-contrast text is difficult or impossible for many users to read.', $saved_issues[0]->description() );
		$this->assertSame( 'https://dequeuniversity.com/rules/axe/4.10/color-contrast', $saved_issues[0]->help_url() );
		$this->assertSame( 'div.header > p.subtitle', $saved_issues[0]->element_selector() );

		$this->assertSame( 'Image elements do not have [alt] attributes', $saved_issues[1]->title() );
		$this->assertSame( 'Informative elements should aim for short, descriptive alternate text.', $saved_issues[1]->description() );
		$this->assertSame( 'https://dequeuniversity.com/rules/axe/4.10/image-alt', $saved_issues[1]->help_url() );
		$this->assertSame( 'img.hero-image', $saved_issues[1]->element_selector() );
	}

	public function test_run_audit_updates_url_last_audited_at(): void {
		$url = $this->make_url( 1 );
		$this->url_repository->method( 'find_by_id' )->with( 1 )->willReturn( $url );

		$api_response = $this->make_api_response( 85 );
		$this->pagespeed_client->method( 'run_audit' )->willReturn( $api_response );

		$this->audit_repository->method( 'save' )->willReturnCallback(
			static function ( Audit $audit ): Audit {
				$audit->set_id( 1 );
				return $audit;
			}
		);

		$this->audit_repository->method( 'update' )->willReturnCallback(
			static fn( Audit $audit ): Audit => $audit
		);

		$this->issue_repository->method( 'save_many' )->willReturnArgument( 0 );

		$this->url_repository
			->expects( $this->once() )
			->method( 'update' )
			->willReturnCallback( static fn( Url $url ): Url => $url );

		$this->service->run_audit( 1 );

		$this->assertNotNull( $url->last_audited_at() );
	}

	public function test_run_audit_creates_comparison_when_previous_audit_exists(): void {
		$url = $this->make_url( 1 );
		$this->url_repository->method( 'find_by_id' )->with( 1 )->willReturn( $url );
		$this->url_repository->method( 'update' )->willReturnArgument( 0 );

		$api_response = $this->make_api_response( 85 );
		$this->pagespeed_client->method( 'run_audit' )->willReturn( $api_response );

		$previous_audit = new Audit(
			5,
			1,
			new Accessibility_Score( 70 ),
			Audit_Status::COMPLETED,
			Run_Strategy::DESKTOP,
			new \DateTimeImmutable( '-1 week' ),
			null,
			null,
			0,
			new \DateTimeImmutable( '-1 week' ),
		);

		$this->audit_repository->method( 'save' )->willReturnCallback(
			static function ( Audit $audit ): Audit {
				$audit->set_id( 10 );
				return $audit;
			}
		);

		$this->audit_repository->method( 'update' )->willReturnCallback(
			static fn( Audit $audit ): Audit => $audit
		);

		$this->audit_repository
			->method( 'find_latest_completed_by_url_id_and_strategy' )
			->with( 1, Run_Strategy::DESKTOP )
			->willReturn( $previous_audit );

		$this->issue_repository->method( 'save_many' )->willReturnArgument( 0 );

		$this->comparison_repository
			->expects( $this->once() )
			->method( 'save' )
			->willReturnCallback(
				static function ( Audit_Comparison $comparison ): Audit_Comparison {
					$comparison->set_id( 1 );
					return $comparison;
				}
			);

		$this->service->run_audit( 1 );
	}

	public function test_run_audit_skips_comparison_when_no_previous_audit(): void {
		$url = $this->make_url( 1 );
		$this->url_repository->method( 'find_by_id' )->with( 1 )->willReturn( $url );
		$this->url_repository->method( 'update' )->willReturnArgument( 0 );

		$api_response = $this->make_api_response( 85 );
		$this->pagespeed_client->method( 'run_audit' )->willReturn( $api_response );

		$this->audit_repository->method( 'save' )->willReturnCallback(
			static function ( Audit $audit ): Audit {
				$audit->set_id( 1 );
				return $audit;
			}
		);

		$this->audit_repository->method( 'update' )->willReturnCallback(
			static fn( Audit $audit ): Audit => $audit
		);

		$this->audit_repository
			->method( 'find_latest_completed_by_url_id_and_strategy' )
			->willReturn( null );

		$this->issue_repository->method( 'save_many' )->willReturnArgument( 0 );

		$this->comparison_repository
			->expects( $this->never() )
			->method( 'save' );

		$this->service->run_audit( 1 );
	}

	private function make_url( int $id ): Url {
		$now = new \DateTimeImmutable();

		return new Url(
			$id,
			null,
			new Url_Address( 'https://example.com' ),
			'Example',
			Audit_Frequency::WEEKLY,
			Audit_Strategy::DESKTOP,
			true,
			false,
			null,
			null,
			null,
			$now,
			$now,
		);
	}

	private function make_api_response( int $score ): Api_Response {
		$json = wp_json_encode(
			[
				'lighthouseResult' => [
					'categories' => [
						'accessibility' => [ 'score' => $score / 100 ],
					],
					'audits'     => [],
				],
			]
		);

		return Api_Response::from_json( $json );
	}
}
