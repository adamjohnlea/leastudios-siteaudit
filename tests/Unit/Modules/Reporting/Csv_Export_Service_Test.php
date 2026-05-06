<?php
/**
 * Csv_Export_Service unit tests.
 *
 * Ported from the source app's CsvExportServiceTest, adapted for the
 * snake_case API and the Url_Summary VO (vs the source's plain array).
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Reporting;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Url_Summary;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Csv_Export_Service;
use LEAStudios\Tests\TestCase;

final class Csv_Export_Service_Test extends TestCase {

	private Csv_Export_Service $service;

	public function set_up(): void {
		parent::set_up();
		$this->service = new Csv_Export_Service();
	}

	public function test_export_audits_emits_header_row(): void {
		$csv   = $this->service->export_audits( [] );
		$lines = explode( "\n", trim( $csv ) );

		$this->assertSame( 'Date,Score,Status,Grade', $lines[0] );
	}

	public function test_export_audits_renders_one_row_per_audit(): void {
		$audits = [
			$this->make_audit( 85, '2024-01-15', Audit_Status::COMPLETED ),
			$this->make_audit( 70, '2024-01-08', Audit_Status::COMPLETED ),
		];

		$csv   = $this->service->export_audits( $audits );
		$lines = explode( "\n", trim( $csv ) );

		$this->assertCount( 3, $lines );
		$this->assertStringContainsString( '2024-01-15', $lines[1] );
		$this->assertStringContainsString( '85', $lines[1] );
		$this->assertStringContainsString( 'Completed', $lines[1] );
		$this->assertStringContainsString( 'B', $lines[1] );
	}

	public function test_export_audits_renders_failed_status_label(): void {
		$audits = [ $this->make_audit( 0, '2024-01-15', Audit_Status::FAILED ) ];

		$csv   = $this->service->export_audits( $audits );
		$lines = explode( "\n", trim( $csv ) );

		$this->assertStringContainsString( 'Failed', $lines[1] );
	}

	public function test_export_audits_assigns_correct_grades(): void {
		$audits = [
			$this->make_audit( 95, '2024-01-04', Audit_Status::COMPLETED ),
			$this->make_audit( 75, '2024-01-03', Audit_Status::COMPLETED ),
			$this->make_audit( 55, '2024-01-02', Audit_Status::COMPLETED ),
			$this->make_audit( 30, '2024-01-01', Audit_Status::COMPLETED ),
		];

		$csv   = $this->service->export_audits( $audits );
		$lines = explode( "\n", trim( $csv ) );

		$this->assertStringContainsString( ',A', $lines[1] );
		$this->assertStringContainsString( ',B', $lines[2] );
		$this->assertStringContainsString( ',C', $lines[3] );
		$this->assertStringContainsString( ',F', $lines[4] );
	}

	public function test_export_url_summaries_emits_header_and_rows(): void {
		$summaries = [
			new Url_Summary( 1, 'Example', 'https://example.com', 85, 5, 'Weekly', true ),
			new Url_Summary( 2, 'Test', 'https://test.com', null, 0, 'Daily', true ),
		];

		$csv   = $this->service->export_url_summaries( $summaries );
		$lines = explode( "\n", trim( $csv ) );

		$this->assertSame( 'Name,URL,Latest Score,Total Audits,Frequency', $lines[0] );
		$this->assertCount( 3, $lines );
		$this->assertStringContainsString( 'Example', $lines[1] );
		$this->assertStringContainsString( '85', $lines[1] );
		$this->assertStringContainsString( 'N/A', $lines[2] );
	}

	public function test_export_audits_converts_audit_date_from_utc_to_wp_timezone(): void {
		// Audits are stored as UTC `DateTimeImmutable`s; the dashboard renders them in
		// WP's display timezone via Datetime_Util::format_immutable_for_display. The CSV
		// must match — otherwise spreadsheet readers see a different time than the
		// admin UI.
		update_option( 'timezone_string', 'America/Boise' );

		$utc_date = new \DateTimeImmutable( '2024-01-15 06:30:00', new \DateTimeZone( 'UTC' ) );
		$audit    = new Audit(
			null,
			1,
			new Accessibility_Score( 85 ),
			Audit_Status::COMPLETED,
			Run_Strategy::DESKTOP,
			$utc_date,
			null,
			null,
			0,
			$utc_date
		);

		$csv   = $this->service->export_audits( [ $audit ] );
		$lines = explode( "\n", trim( $csv ) );

		// 06:30 UTC on 2024-01-15 is 23:30 the previous day in America/Boise (MST, UTC-7).
		$this->assertStringContainsString( '2024-01-14 23:30:00', $lines[1] );

		// Restore default for any subsequent test that assumes UTC.
		update_option( 'timezone_string', '' );
	}

	public function test_export_url_summaries_quotes_cells_with_commas_or_quotes(): void {
		$summaries = [
			new Url_Summary( 1, 'Acme, Inc.', 'https://example.com/path,with,commas', 85, 5, 'Weekly', true ),
			new Url_Summary( 2, 'Has "quotes" inside', 'https://test.com', 90, 3, 'Daily', true ),
		];

		$csv   = $this->service->export_url_summaries( $summaries );
		$lines = explode( "\n", trim( $csv ) );

		// Cell with comma is wrapped in quotes.
		$this->assertStringContainsString( '"Acme, Inc."', $lines[1] );
		$this->assertStringContainsString( '"https://example.com/path,with,commas"', $lines[1] );

		// Embedded quotes are doubled, whole cell is wrapped in quotes.
		$this->assertStringContainsString( '"Has ""quotes"" inside"', $lines[2] );
	}

	private function make_audit( int $score, string $date, Audit_Status $status ): Audit {
		$audit_date = new \DateTimeImmutable( $date );

		return new Audit(
			null,
			1,
			new Accessibility_Score( $score ),
			$status,
			Run_Strategy::DESKTOP,
			$audit_date,
			null,
			null,
			0,
			$audit_date
		);
	}
}
