<?php
/**
 * Audit orchestration service.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Issue;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Comparison_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Issue_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Category;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Severity;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Api_Exception;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Api_Response;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\PageSpeed_Client_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Rate_Limit_Exception;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\RateLimiting\Retry_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;

/**
 * Drives one audit run from "Run audit now" or scheduled cron through to
 * persisted Audit + Issue + Audit_Comparison rows.
 *
 * Lifecycle for each (URL, strategy) tuple:
 *   1. Insert a placeholder audit row in `IN_PROGRESS` state so the failure
 *      branch always has a row to update.
 *   2. Call PageSpeed via {@see PageSpeed_Client_Interface}, retrying on 429
 *      using {@see Retry_Strategy} (the audit's `retry_count` is incremented
 *      in place so it survives a final failure).
 *   3. On success: persist score + raw response, mark COMPLETED, extract
 *      issues, and (if a previous COMPLETED audit exists for the same
 *      strategy) persist an Audit_Comparison.
 *   4. On failure: mark FAILED with the exception message.
 *
 * Finally, regardless of per-strategy outcome, `urls.last_audited_at` is
 * stamped so the cron scheduler treats the URL as "freshly attempted".
 */
final class Audit_Service implements Audit_Service_Interface {

	/**
	 * URL repository.
	 *
	 * @var Url_Repository_Interface
	 */
	private readonly Url_Repository_Interface $url_repository;

	/**
	 * Audit repository.
	 *
	 * @var Audit_Repository_Interface
	 */
	private readonly Audit_Repository_Interface $audit_repository;

	/**
	 * Issue repository.
	 *
	 * @var Issue_Repository_Interface
	 */
	private readonly Issue_Repository_Interface $issue_repository;

	/**
	 * PageSpeed client.
	 *
	 * @var PageSpeed_Client_Interface
	 */
	private readonly PageSpeed_Client_Interface $pagespeed_client;

	/**
	 * Retry policy applied to 429 rate-limit responses.
	 *
	 * @var Retry_Strategy
	 */
	private readonly Retry_Strategy $retry_strategy;

	/**
	 * Pure-function audit comparator.
	 *
	 * @var Comparison_Service
	 */
	private readonly Comparison_Service $comparison_service;

	/**
	 * Audit-comparison repository.
	 *
	 * @var Audit_Comparison_Repository_Interface
	 */
	private readonly Audit_Comparison_Repository_Interface $comparison_repository;

	/**
	 * Constructor.
	 *
	 * @param Url_Repository_Interface              $url_repository        URL repo.
	 * @param Audit_Repository_Interface            $audit_repository      Audit repo.
	 * @param Issue_Repository_Interface            $issue_repository      Issue repo.
	 * @param PageSpeed_Client_Interface            $pagespeed_client      PageSpeed client.
	 * @param Retry_Strategy                        $retry_strategy        Retry policy.
	 * @param Comparison_Service                    $comparison_service    Comparator.
	 * @param Audit_Comparison_Repository_Interface $comparison_repository Comparison repo.
	 */
	public function __construct(
		Url_Repository_Interface $url_repository,
		Audit_Repository_Interface $audit_repository,
		Issue_Repository_Interface $issue_repository,
		PageSpeed_Client_Interface $pagespeed_client,
		Retry_Strategy $retry_strategy,
		Comparison_Service $comparison_service,
		Audit_Comparison_Repository_Interface $comparison_repository
	) {
		$this->url_repository        = $url_repository;
		$this->audit_repository      = $audit_repository;
		$this->issue_repository      = $issue_repository;
		$this->pagespeed_client      = $pagespeed_client;
		$this->retry_strategy        = $retry_strategy;
		$this->comparison_service    = $comparison_service;
		$this->comparison_repository = $comparison_repository;
	}

	/**
	 * Run audits for the given URL across all applicable strategies.
	 *
	 * @param int $url_id URL id.
	 *
	 * @return array<int, Audit>
	 *
	 * @throws Validation_Exception When the URL does not exist.
	 */
	public function run_audit( int $url_id ): array {
		$url = $this->url_repository->find_by_id( $url_id );

		if ( null === $url ) {
			throw new Validation_Exception( 'URL not found' );
		}

		$strategies = match ( $url->audit_strategy() ) {
			Audit_Strategy::DESKTOP => [ Run_Strategy::DESKTOP ],
			Audit_Strategy::MOBILE  => [ Run_Strategy::MOBILE ],
			Audit_Strategy::BOTH    => [ Run_Strategy::DESKTOP, Run_Strategy::MOBILE ],
		};

		$results = [];
		foreach ( $strategies as $strategy ) {
			$results[] = $this->run_single_audit( $url, $strategy );
		}

		$now = new \DateTimeImmutable();
		$url->set_last_audited_at( $now );
		$url->set_updated_at( $now );
		$this->url_repository->update( $url );

		return $results;
	}

	/**
	 * Run a single audit for one (URL, strategy) tuple.
	 *
	 * @param Url          $url      URL to audit.
	 * @param Run_Strategy $strategy Device profile.
	 *
	 * @return Audit
	 */
	private function run_single_audit( Url $url, Run_Strategy $strategy ): Audit {
		$now   = new \DateTimeImmutable();
		$audit = new Audit(
			null,
			$url->id() ?? 0,
			new Accessibility_Score( 0 ),
			Audit_Status::IN_PROGRESS,
			$strategy,
			$now,
			null,
			null,
			0,
			$now,
		);

		$audit = $this->audit_repository->save( $audit );

		// Snapshot the prior completed audit BEFORE the current row is marked
		// COMPLETED. Otherwise the find_latest_completed query can return the
		// current row itself (it has the latest audit_date). This snapshot
		// is also what we hand to the audit-completed action, so listeners
		// (e.g. Alert_Notifier) can compute "score dropped from X to Y".
		$previous_audit = $this->audit_repository->find_latest_completed_by_url_id_and_strategy(
			$url->id() ?? 0,
			$strategy
		);

		try {
			$api_response = $this->execute_with_retry( $url->url()->value(), $strategy, $audit );

			$audit->set_score( new Accessibility_Score( $api_response->score() ) );
			$audit->set_status( Audit_Status::COMPLETED );
			$audit->set_raw_response( $api_response->raw_json() );
			$this->audit_repository->update( $audit );

			$this->extract_and_save_issues( $audit, $api_response );

			if ( null !== $previous_audit ) {
				$comparison = $this->comparison_service->compare( $audit, $previous_audit );
				$this->comparison_repository->save( $comparison );
			}

			/**
			 * Fires after a successful audit run, once score, issues, and
			 * comparison row have been persisted.
			 *
			 * @param Audit      $audit          Just-completed audit.
			 * @param Url        $url            URL the audit ran on.
			 * @param Audit|null $previous_audit Prior completed audit for the same (URL, strategy), or null if first run.
			 */
			do_action( 'leastudios_siteaudit_audit_completed', $audit, $url, $previous_audit );
		} catch ( Api_Exception $e ) {
			$audit->set_status( Audit_Status::FAILED );
			$audit->set_error_message( $e->getMessage() );
			$this->audit_repository->update( $audit );
		}

		return $audit;
	}

	/**
	 * Call the PageSpeed API with exponential-backoff retry on 429s.
	 *
	 * @param string       $url      Target URL.
	 * @param Run_Strategy $strategy Device profile.
	 * @param Audit        $audit    Audit row whose retry counter we mutate.
	 *
	 * @return Api_Response
	 *
	 * @throws Api_Exception When all retry attempts fail.
	 */
	private function execute_with_retry( string $url, Run_Strategy $strategy, Audit $audit ): Api_Response {
		$last_exception = null;

		while ( $this->retry_strategy->should_retry( $audit->retry_count() ) ) {
			try {
				return $this->pagespeed_client->run_audit( $url, $strategy->value );
			} catch ( Rate_Limit_Exception $e ) {
				$last_exception = $e;
				$audit->increment_retry_count();

				$delay_ms = $this->retry_strategy->delay_ms( $audit->retry_count() - 1 );
				if ( $delay_ms > 0 ) {
					usleep( $delay_ms * 1000 );
				}
			}
		}

		throw $last_exception ?? new Api_Exception( 'Max retries exceeded' );
	}

	/**
	 * Convert each failing Lighthouse audit to an Issue and persist them.
	 *
	 * @param Audit        $audit        Audit owning the issues.
	 * @param Api_Response $api_response Parsed response.
	 *
	 * @return void
	 */
	private function extract_and_save_issues( Audit $audit, Api_Response $api_response ): void {
		$issues = [];

		foreach ( $api_response->failing_audits() as $failing_audit ) {
			$category = $this->map_category( $failing_audit['id'] );
			$severity = $this->map_severity( $failing_audit['score'] );

			$element_selector = null;
			if ( isset( $failing_audit['details'][0]['selector'] ) ) {
				$element_selector = $failing_audit['details'][0]['selector'];
			}

			$issues[] = new Issue(
				null,
				$audit->id() ?? 0,
				$severity,
				$category,
				$this->clean_description( $failing_audit['description'] ),
				$element_selector,
				$failing_audit['helpUrl'],
				new \DateTimeImmutable(),
				$failing_audit['title'],
			);
		}

		if ( [] !== $issues ) {
			$this->issue_repository->save_many( $issues );
		}
	}

	/**
	 * Map a Lighthouse audit id (e.g., "color-contrast", "image-alt") to an Issue_Category.
	 *
	 * @param string $audit_id Lighthouse audit id.
	 *
	 * @return Issue_Category
	 */
	private function map_category( string $audit_id ): Issue_Category {
		return match ( true ) {
			str_contains( $audit_id, 'color-contrast' )                                                                            => Issue_Category::COLOR_CONTRAST,
			str_contains( $audit_id, 'aria' )                                                                                      => Issue_Category::ARIA,
			str_contains( $audit_id, 'label' ), str_contains( $audit_id, 'form' )                                                  => Issue_Category::FORMS,
			str_contains( $audit_id, 'image' ), str_contains( $audit_id, 'alt' )                                                   => Issue_Category::IMAGES,
			str_contains( $audit_id, 'tabindex' ), str_contains( $audit_id, 'focus' ), str_contains( $audit_id, 'link' )           => Issue_Category::NAVIGATION,
			str_contains( $audit_id, 'table' ), str_contains( $audit_id, 'th' ), str_contains( $audit_id, 'td' )                   => Issue_Category::TABLES,
			default                                                                                                                => Issue_Category::OTHER,
		};
	}

	/**
	 * Map a Lighthouse fractional score to an Issue_Severity.
	 *
	 * @param float|null $score Fractional score in [0, 1] or null.
	 *
	 * @return Issue_Severity
	 */
	private function map_severity( ?float $score ): Issue_Severity {
		if ( null === $score || 0.0 === $score ) {
			return Issue_Severity::CRITICAL;
		}

		return match ( true ) {
			$score < 0.25 => Issue_Severity::SERIOUS,
			$score < 0.75 => Issue_Severity::MODERATE,
			default       => Issue_Severity::MINOR,
		};
	}

	/**
	 * Strip Lighthouse "[Learn more](url)." trailing markdown from a description.
	 *
	 * @param string $description Raw description.
	 *
	 * @return string
	 */
	private function clean_description( string $description ): string {
		return trim( (string) preg_replace( '/\s*\[[^\]]*\]\(https?:\/\/[^)]+\)\.?/', '', $description ) );
	}
}
