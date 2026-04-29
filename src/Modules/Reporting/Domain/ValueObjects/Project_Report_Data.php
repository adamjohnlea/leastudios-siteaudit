<?php
/**
 * Aggregated report data for a single project's PDF.
 *
 * @package LEAStudios\SiteAudit\Modules\Reporting\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Reporting\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Dashboard_Summary;
use LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Url_Summary;

/**
 * Read-only carrier of everything the project-PDF template renders.
 *
 * Built by `Pdf_Report_Data_Collector` from URL + audit + issue + summary
 * fetches; consumed by `templates/reports/project-pdf.php`.
 *
 * The `issues_by_category` shape is defined upstream in the collector — each
 * issue entry contains: `title`, `description`, `severity` (label), `severity_weight`,
 * `help_url`, `affected_urls` (list of URL display names).
 */
final class Project_Report_Data {

	/**
	 * Project name.
	 *
	 * @var string
	 */
	private string $project_name;

	/**
	 * Generated-at timestamp formatted `Y-m-d H:i:s`.
	 *
	 * @var string
	 */
	private string $generated_at;

	/**
	 * Aggregate summary.
	 *
	 * @var Dashboard_Summary
	 */
	private Dashboard_Summary $summary;

	/**
	 * Per-URL summaries.
	 *
	 * @var array<int, Url_Summary>
	 */
	private array $url_summaries;

	/**
	 * Issues grouped by category, deduplicated, sorted by severity weight desc.
	 *
	 * @var array<string, array<int, array{title: string, description: string, severity: string, severity_weight: int, help_url: string|null, affected_urls: array<int, string>}>>
	 */
	private array $issues_by_category;

	/**
	 * Total issue count.
	 *
	 * @var int
	 */
	private int $total_issues;

	/**
	 * Severity tally.
	 *
	 * @var array{critical: int, serious: int, moderate: int, minor: int}
	 */
	private array $severity_counts;

	/**
	 * Constructor.
	 *
	 * @param string                                                                                                                                                                 $project_name        Project name.
	 * @param string                                                                                                                                                                 $generated_at        Formatted timestamp.
	 * @param Dashboard_Summary                                                                                                                                                      $summary             Summary.
	 * @param array<int, Url_Summary>                                                                                                                                                $url_summaries       Per-URL summaries.
	 * @param array<string, array<int, array{title: string, description: string, severity: string, severity_weight: int, help_url: string|null, affected_urls: array<int, string>}>> $issues_by_category  Grouped issues.
	 * @param int                                                                                                                                                                    $total_issues        Total issue count.
	 * @param array{critical: int, serious: int, moderate: int, minor: int}                                                                                                          $severity_counts     Severity tally.
	 */
	public function __construct(
		string $project_name,
		string $generated_at,
		Dashboard_Summary $summary,
		array $url_summaries,
		array $issues_by_category,
		int $total_issues,
		array $severity_counts
	) {
		$this->project_name       = $project_name;
		$this->generated_at       = $generated_at;
		$this->summary            = $summary;
		$this->url_summaries      = $url_summaries;
		$this->issues_by_category = $issues_by_category;
		$this->total_issues       = $total_issues;
		$this->severity_counts    = $severity_counts;
	}

	/**
	 * Project name.
	 *
	 * @return string
	 */
	public function project_name(): string {
		return $this->project_name;
	}

	/**
	 * Generated-at timestamp.
	 *
	 * @return string
	 */
	public function generated_at(): string {
		return $this->generated_at;
	}

	/**
	 * Summary.
	 *
	 * @return Dashboard_Summary
	 */
	public function summary(): Dashboard_Summary {
		return $this->summary;
	}

	/**
	 * Per-URL summaries.
	 *
	 * @return array<int, Url_Summary>
	 */
	public function url_summaries(): array {
		return $this->url_summaries;
	}

	/**
	 * Issues grouped by category.
	 *
	 * @return array<string, array<int, array{title: string, description: string, severity: string, severity_weight: int, help_url: string|null, affected_urls: array<int, string>}>>
	 */
	public function issues_by_category(): array {
		return $this->issues_by_category;
	}

	/**
	 * Total issue count.
	 *
	 * @return int
	 */
	public function total_issues(): int {
		return $this->total_issues;
	}

	/**
	 * Severity tally.
	 *
	 * @return array{critical: int, serious: int, moderate: int, minor: int}
	 */
	public function severity_counts(): array {
		return $this->severity_counts;
	}
}
