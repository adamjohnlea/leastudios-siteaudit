<?php
/**
 * Dashboard_Statistics unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Dashboard;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Dashboard\Application\Services\Dashboard_Statistics;
use LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Dashboard_Summary;
use LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Url_Summary;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Url_Address;
use LEAStudios\Tests\TestCase;

final class Dashboard_Statistics_Test extends TestCase {

	private Dashboard_Statistics $statistics;

	public function set_up(): void {
		parent::set_up();
		$this->statistics = new Dashboard_Statistics();
	}

	public function test_calculates_summary_from_urls_and_audits(): void {
		$urls = [
			$this->make_url( 1, 'https://example.com', 'Example' ),
			$this->make_url( 2, 'https://test.com', 'Test' ),
		];

		$audits_by_url = [
			1 => [ $this->make_audit( 90, '2024-01-15' ), $this->make_audit( 85, '2024-01-08' ) ],
			2 => [ $this->make_audit( 60, '2024-01-15' ), $this->make_audit( 65, '2024-01-08' ) ],
		];

		$summary = $this->statistics->calculate_summary( $urls, $audits_by_url );

		$this->assertInstanceOf( Dashboard_Summary::class, $summary );
		$this->assertSame( 2, $summary->total_urls() );
		$this->assertSame( 4, $summary->total_audits() );
		$this->assertSame( 75, $summary->average_score() );
	}

	public function test_identifies_urls_needing_attention(): void {
		$urls = [
			$this->make_url( 1, 'https://good.com', 'Good' ),
			$this->make_url( 2, 'https://bad.com', 'Bad' ),
			$this->make_url( 3, 'https://ok.com', 'OK' ),
		];

		$audits_by_url = [
			1 => [ $this->make_audit( 95, '2024-01-15' ) ],
			2 => [ $this->make_audit( 45, '2024-01-15' ) ],
			3 => [ $this->make_audit( 70, '2024-01-15' ) ],
		];

		$summary = $this->statistics->calculate_summary( $urls, $audits_by_url );

		$this->assertSame( 1, $summary->urls_needing_attention() );
	}

	public function test_calculates_score_distribution(): void {
		$urls = [
			$this->make_url( 1, 'https://a.com', 'A' ),
			$this->make_url( 2, 'https://b.com', 'B' ),
			$this->make_url( 3, 'https://c.com', 'C' ),
			$this->make_url( 4, 'https://d.com', 'D' ),
		];

		$audits_by_url = [
			1 => [ $this->make_audit( 95, '2024-01-15' ) ],
			2 => [ $this->make_audit( 80, '2024-01-15' ) ],
			3 => [ $this->make_audit( 55, '2024-01-15' ) ],
			4 => [ $this->make_audit( 30, '2024-01-15' ) ],
		];

		$summary      = $this->statistics->calculate_summary( $urls, $audits_by_url );
		$distribution = $summary->score_distribution();

		$this->assertSame( 1, $distribution['excellent'] );
		$this->assertSame( 1, $distribution['good'] );
		$this->assertSame( 1, $distribution['needs_work'] );
		$this->assertSame( 1, $distribution['poor'] );
	}

	public function test_generates_url_summaries(): void {
		$urls = [
			$this->make_url( 1, 'https://example.com', 'Example' ),
		];

		$audits_by_url = [
			1 => [
				$this->make_audit( 85, '2024-01-15' ),
				$this->make_audit( 80, '2024-01-08' ),
				$this->make_audit( 75, '2024-01-01' ),
			],
		];

		$url_summaries = $this->statistics->generate_url_summaries( $urls, $audits_by_url );

		$this->assertCount( 1, $url_summaries );
		$this->assertInstanceOf( Url_Summary::class, $url_summaries[0] );
		$this->assertSame( 1, $url_summaries[0]->url_id() );
		$this->assertSame( 'Example', $url_summaries[0]->name() );
		$this->assertSame( 'https://example.com', $url_summaries[0]->address() );
		$this->assertSame( 85, $url_summaries[0]->latest_score() );
		$this->assertSame( 3, $url_summaries[0]->total_audits() );
	}

	public function test_handles_empty_data(): void {
		$summary = $this->statistics->calculate_summary( [], [] );

		$this->assertSame( 0, $summary->total_urls() );
		$this->assertSame( 0, $summary->total_audits() );
		$this->assertSame( 0, $summary->average_score() );
		$this->assertSame( 0, $summary->urls_needing_attention() );
	}

	public function test_handles_url_with_no_audits(): void {
		$urls = [
			$this->make_url( 1, 'https://example.com', 'Example' ),
		];

		$url_summaries = $this->statistics->generate_url_summaries( $urls, [] );

		$this->assertCount( 1, $url_summaries );
		$this->assertNull( $url_summaries[0]->latest_score() );
		$this->assertSame( 0, $url_summaries[0]->total_audits() );
	}

	private function make_url( int $id, string $address, string $name ): Url {
		$now = new \DateTimeImmutable();

		return new Url(
			$id,
			null,
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

	private function make_audit( int $score, string $date ): Audit {
		$audit_date = new \DateTimeImmutable( $date );

		return new Audit(
			null,
			1,
			new Accessibility_Score( $score ),
			Audit_Status::COMPLETED,
			Run_Strategy::DESKTOP,
			$audit_date,
			null,
			null,
			0,
			$audit_date
		);
	}
}
