<?php
/**
 * Builds the structured data the project-PDF template renders.
 *
 * @package LEAStudios\SiteAudit\Modules\Reporting\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Reporting\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Issue;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Issue_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Dashboard\Application\Services\Dashboard_Statistics;
use LEAStudios\SiteAudit\Modules\Reporting\Domain\ValueObjects\Project_Report_Data;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;

/**
 * Orchestrates URL + audit + issue + summary fetches into the
 * `Project_Report_Data` VO consumed by the PDF template.
 *
 * Direct port of `PdfReportDataCollector` from the source app:
 *
 *   1. Fetch all URLs in the project.
 *   2. For each URL, fetch all audits (DESC) and remember its display name.
 *   3. Compute the dashboard summary + per-URL summaries from those audits.
 *   4. For each URL's *latest* audit only, fetch its issues.
 *   5. Group + deduplicate issues by `(category, title|severity)`,
 *      collecting the affected-URL list per unique issue, sorted by severity
 *      weight descending; categories sorted alphabetically.
 *   6. Tally severity counts.
 */
final class Pdf_Report_Data_Collector implements Pdf_Report_Data_Collector_Interface {

	/**
	 * URL repository.
	 *
	 * @var Url_Repository_Interface
	 */
	private Url_Repository_Interface $url_repository;

	/**
	 * Audit repository.
	 *
	 * @var Audit_Repository_Interface
	 */
	private Audit_Repository_Interface $audit_repository;

	/**
	 * Issue repository.
	 *
	 * @var Issue_Repository_Interface
	 */
	private Issue_Repository_Interface $issue_repository;

	/**
	 * Dashboard statistics service.
	 *
	 * @var Dashboard_Statistics
	 */
	private Dashboard_Statistics $statistics;

	/**
	 * Constructor.
	 *
	 * @param Url_Repository_Interface   $url_repository    URL repo.
	 * @param Audit_Repository_Interface $audit_repository  Audit repo.
	 * @param Issue_Repository_Interface $issue_repository  Issue repo.
	 * @param Dashboard_Statistics       $statistics        Stats service.
	 */
	public function __construct(
		Url_Repository_Interface $url_repository,
		Audit_Repository_Interface $audit_repository,
		Issue_Repository_Interface $issue_repository,
		Dashboard_Statistics $statistics
	) {
		$this->url_repository   = $url_repository;
		$this->audit_repository = $audit_repository;
		$this->issue_repository = $issue_repository;
		$this->statistics       = $statistics;
	}

	/**
	 * Collect all data needed to render a project's PDF report.
	 *
	 * @param Project $project Project being reported.
	 *
	 * @return Project_Report_Data
	 */
	public function collect( Project $project ): Project_Report_Data {
		$project_id = $project->id() ?? 0;
		$urls       = $this->url_repository->find_by_project_id( $project_id );

		$audits_by_url = [];
		$url_names     = [];
		foreach ( $urls as $url ) {
			$url_id                   = $url->id() ?? 0;
			$audits_by_url[ $url_id ] = $this->audit_repository->find_by_url_id( $url_id );
			$url_names[ $url_id ]     = $url->name() ?? $url->url()->value();
		}

		$summary       = $this->statistics->calculate_summary( $urls, $audits_by_url );
		$url_summaries = $this->statistics->generate_url_summaries( $urls, $audits_by_url );

		$all_issues         = $this->collect_latest_issues( $audits_by_url, $url_names );
		$issues_by_category = $this->group_and_deduplicate_issues( $all_issues );
		$severity_counts    = $this->count_severities( $all_issues );

		return new Project_Report_Data(
			$project->name()->value(),
			( new \DateTimeImmutable() )->format( 'Y-m-d H:i:s' ),
			$summary,
			$url_summaries,
			$issues_by_category,
			count( $all_issues ),
			$severity_counts
		);
	}

	/**
	 * Pull the issues attached to each URL's *latest* audit only.
	 *
	 * @param array<int, array<int, Audit>> $audits_by_url Map of url_id => audits (DESC).
	 * @param array<int, string>            $url_names     Map of url_id => display name.
	 *
	 * @return array<int, array{issue: Issue, url_name: string}>
	 */
	private function collect_latest_issues( array $audits_by_url, array $url_names ): array {
		$all_issues = [];

		foreach ( $audits_by_url as $url_id => $audits ) {
			if ( [] === $audits ) {
				continue;
			}

			$latest_audit = $audits[0];
			$audit_id     = $latest_audit->id();
			if ( null === $audit_id ) {
				continue;
			}

			$issues   = $this->issue_repository->find_by_audit_id( $audit_id );
			$url_name = $url_names[ $url_id ] ?? 'URL #' . $url_id;

			foreach ( $issues as $issue ) {
				$all_issues[] = [
					'issue'    => $issue,
					'url_name' => $url_name,
				];
			}
		}

		return $all_issues;
	}

	/**
	 * Group issues by category, deduplicate within each category by
	 * `(title|severity)`, collect affected URL names, sort by severity weight.
	 *
	 * @param array<int, array{issue: Issue, url_name: string}> $all_issues Flat issue list.
	 *
	 * @return array<string, array<int, array{title: string, description: string, severity: string, severity_weight: int, help_url: string|null, affected_urls: array<int, string>}>>
	 */
	private function group_and_deduplicate_issues( array $all_issues ): array {
		$grouped = [];

		foreach ( $all_issues as $entry ) {
			$issue    = $entry['issue'];
			$url_name = $entry['url_name'];
			$category = $issue->category()->label();
			$title    = $issue->title() ?? $issue->description();
			$key      = $title . '|' . $issue->severity()->value;

			if ( ! isset( $grouped[ $category ][ $key ] ) ) {
				$grouped[ $category ][ $key ] = [
					'title'           => $title,
					'description'     => $issue->description(),
					'severity'        => $issue->severity()->label(),
					'severity_weight' => $issue->severity()->weight(),
					'help_url'        => $issue->help_url(),
					'affected_urls'   => [],
				];
			}

			if ( ! in_array( $url_name, $grouped[ $category ][ $key ]['affected_urls'], true ) ) {
				$grouped[ $category ][ $key ]['affected_urls'][] = $url_name;
			}
		}

		$result = [];
		foreach ( $grouped as $category => $issues ) {
			$issue_list = array_values( $issues );
			usort(
				$issue_list,
				static fn( array $a, array $b ): int => $b['severity_weight'] <=> $a['severity_weight']
			);
			$result[ $category ] = $issue_list;
		}

		ksort( $result );

		return $result;
	}

	/**
	 * Tally issues by severity.
	 *
	 * @param array<int, array{issue: Issue, url_name: string}> $all_issues Flat issue list.
	 *
	 * @return array{critical: int, serious: int, moderate: int, minor: int}
	 */
	private function count_severities( array $all_issues ): array {
		$counts = [
			'critical' => 0,
			'serious'  => 0,
			'moderate' => 0,
			'minor'    => 0,
		];

		foreach ( $all_issues as $entry ) {
			$severity = $entry['issue']->severity()->value;
			++$counts[ $severity ];
		}

		return $counts;
	}
}
