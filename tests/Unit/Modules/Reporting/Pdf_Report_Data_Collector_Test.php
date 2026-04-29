<?php
/**
 * Pdf_Report_Data_Collector unit tests.
 *
 * Ported from the source app's PdfReportDataCollectorTest. Mocks the three
 * repositories so the collector's grouping / deduplication / sorting logic
 * is exercised in isolation.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Reporting;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Issue;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Issue_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Category;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Severity;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Dashboard\Application\Services\Dashboard_Statistics;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Pdf_Report_Data_Collector;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Project_Name;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Url_Address;
use LEAStudios\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class Pdf_Report_Data_Collector_Test extends TestCase {

	private Url_Repository_Interface&MockObject $url_repository;

	private Audit_Repository_Interface&MockObject $audit_repository;

	private Issue_Repository_Interface&MockObject $issue_repository;

	private Pdf_Report_Data_Collector $collector;

	public function set_up(): void {
		parent::set_up();

		$this->url_repository   = $this->createMock( Url_Repository_Interface::class );
		$this->audit_repository = $this->createMock( Audit_Repository_Interface::class );
		$this->issue_repository = $this->createMock( Issue_Repository_Interface::class );

		$this->collector = new Pdf_Report_Data_Collector(
			$this->url_repository,
			$this->audit_repository,
			$this->issue_repository,
			new Dashboard_Statistics()
		);
	}

	public function test_collects_report_data_for_project(): void {
		$project = $this->make_project( 1, 'Test Project' );
		$url     = $this->make_url( 1, 1, 'https://example.com', 'Homepage' );
		$audit   = $this->make_audit( 10, 1, 85 );
		$issue   = $this->make_issue( 1, 10, Issue_Severity::SERIOUS, Issue_Category::COLOR_CONTRAST, 'Low contrast' );

		$this->url_repository->method( 'find_by_project_id' )->with( 1 )->willReturn( [ $url ] );
		$this->audit_repository->method( 'find_by_url_id' )->with( 1 )->willReturn( [ $audit ] );
		$this->issue_repository->method( 'find_by_audit_id' )->with( 10 )->willReturn( [ $issue ] );

		$report = $this->collector->collect( $project );

		$this->assertSame( 'Test Project', $report->project_name() );
		$this->assertCount( 1, $report->url_summaries() );
		$this->assertSame( 1, $report->total_issues() );
		$this->assertSame( 1, $report->severity_counts()['serious'] );
		$this->assertArrayHasKey( 'Color Contrast', $report->issues_by_category() );
	}

	public function test_handles_project_with_no_urls(): void {
		$project = $this->make_project( 1, 'Empty Project' );
		$this->url_repository->method( 'find_by_project_id' )->with( 1 )->willReturn( [] );

		$report = $this->collector->collect( $project );

		$this->assertSame( 'Empty Project', $report->project_name() );
		$this->assertSame( 0, $report->summary()->total_urls() );
		$this->assertSame( [], $report->url_summaries() );
		$this->assertSame( [], $report->issues_by_category() );
		$this->assertSame( 0, $report->total_issues() );
	}

	public function test_handles_url_with_no_audits(): void {
		$project = $this->make_project( 1, 'No Audits' );
		$url     = $this->make_url( 1, 1, 'https://example.com', 'Homepage' );

		$this->url_repository->method( 'find_by_project_id' )->with( 1 )->willReturn( [ $url ] );
		$this->audit_repository->method( 'find_by_url_id' )->with( 1 )->willReturn( [] );

		$report = $this->collector->collect( $project );

		$this->assertSame( 1, $report->summary()->total_urls() );
		$this->assertSame( [], $report->issues_by_category() );
		$this->assertSame( 0, $report->total_issues() );
	}

	public function test_deduplicates_same_issue_across_urls(): void {
		$project = $this->make_project( 1, 'Multi URL' );
		$url1    = $this->make_url( 1, 1, 'https://example.com', 'Homepage' );
		$url2    = $this->make_url( 2, 1, 'https://example.com/about', 'About' );
		$audit1  = $this->make_audit( 10, 1, 80 );
		$audit2  = $this->make_audit( 20, 2, 70 );

		$issue1 = $this->make_issue( 1, 10, Issue_Severity::SERIOUS, Issue_Category::COLOR_CONTRAST, 'Low contrast', 'Low contrast text' );
		$issue2 = $this->make_issue( 2, 20, Issue_Severity::SERIOUS, Issue_Category::COLOR_CONTRAST, 'Low contrast', 'Low contrast text' );

		$this->url_repository->method( 'find_by_project_id' )->with( 1 )->willReturn( [ $url1, $url2 ] );
		$this->audit_repository->method( 'find_by_url_id' )->willReturnCallback(
			static fn ( int $url_id ): array => match ( $url_id ) {
				1       => [ $audit1 ],
				2       => [ $audit2 ],
				default => [],
			}
		);
		$this->issue_repository->method( 'find_by_audit_id' )->willReturnCallback(
			static fn ( int $audit_id ): array => match ( $audit_id ) {
				10      => [ $issue1 ],
				20      => [ $issue2 ],
				default => [],
			}
		);

		$report                = $this->collector->collect( $project );
		$color_contrast_issues = $report->issues_by_category()['Color Contrast'];

		$this->assertCount( 1, $color_contrast_issues );
		$this->assertCount( 2, $color_contrast_issues[0]['affected_urls'] );
	}

	public function test_sorts_issues_by_severity_weight_descending(): void {
		$project = $this->make_project( 1, 'Severity Sort' );
		$url     = $this->make_url( 1, 1, 'https://example.com', 'Homepage' );
		$audit   = $this->make_audit( 10, 1, 60 );

		$minor_issue    = $this->make_issue( 1, 10, Issue_Severity::MINOR, Issue_Category::ARIA, 'Minor issue', 'Minor' );
		$critical_issue = $this->make_issue( 2, 10, Issue_Severity::CRITICAL, Issue_Category::ARIA, 'Critical issue', 'Critical' );

		$this->url_repository->method( 'find_by_project_id' )->willReturn( [ $url ] );
		$this->audit_repository->method( 'find_by_url_id' )->willReturn( [ $audit ] );
		$this->issue_repository->method( 'find_by_audit_id' )->willReturn( [ $minor_issue, $critical_issue ] );

		$report      = $this->collector->collect( $project );
		$aria_issues = $report->issues_by_category()['ARIA'];

		$this->assertSame( 'Critical', $aria_issues[0]['severity'] );
		$this->assertSame( 'Minor', $aria_issues[1]['severity'] );
	}

	public function test_counts_severity_breakdown_correctly(): void {
		$project = $this->make_project( 1, 'Severity Count' );
		$url     = $this->make_url( 1, 1, 'https://example.com', 'Homepage' );
		$audit   = $this->make_audit( 10, 1, 50 );

		$issues = [
			$this->make_issue( 1, 10, Issue_Severity::CRITICAL, Issue_Category::ARIA, 'Critical 1', 'C1' ),
			$this->make_issue( 2, 10, Issue_Severity::CRITICAL, Issue_Category::FORMS, 'Critical 2', 'C2' ),
			$this->make_issue( 3, 10, Issue_Severity::MODERATE, Issue_Category::IMAGES, 'Moderate 1', 'M1' ),
		];

		$this->url_repository->method( 'find_by_project_id' )->willReturn( [ $url ] );
		$this->audit_repository->method( 'find_by_url_id' )->willReturn( [ $audit ] );
		$this->issue_repository->method( 'find_by_audit_id' )->willReturn( $issues );

		$report = $this->collector->collect( $project );

		$this->assertSame( 2, $report->severity_counts()['critical'] );
		$this->assertSame( 0, $report->severity_counts()['serious'] );
		$this->assertSame( 1, $report->severity_counts()['moderate'] );
		$this->assertSame( 0, $report->severity_counts()['minor'] );
		$this->assertSame( 3, $report->total_issues() );
	}

	private function make_project( int $id, string $name ): Project {
		$now = new \DateTimeImmutable();

		return new Project(
			$id,
			new Project_Name( $name ),
			null,
			$now,
			$now
		);
	}

	private function make_url( int $id, int $project_id, string $address, string $name ): Url {
		$now = new \DateTimeImmutable();

		return new Url(
			$id,
			$project_id,
			new Url_Address( $address ),
			$name,
			Audit_Frequency::WEEKLY,
			Audit_Strategy::BOTH,
			true,
			false,
			null,
			null,
			null,
			$now,
			$now
		);
	}

	private function make_audit( int $id, int $url_id, int $score ): Audit {
		$now = new \DateTimeImmutable();

		return new Audit(
			$id,
			$url_id,
			new Accessibility_Score( $score ),
			Audit_Status::COMPLETED,
			Run_Strategy::DESKTOP,
			$now,
			null,
			null,
			0,
			$now
		);
	}

	private function make_issue( int $id, int $audit_id, Issue_Severity $severity, Issue_Category $category, string $description, ?string $title = null ): Issue {
		return new Issue(
			$id,
			$audit_id,
			$severity,
			$category,
			$description,
			null,
			null,
			new \DateTimeImmutable(),
			$title
		);
	}
}
