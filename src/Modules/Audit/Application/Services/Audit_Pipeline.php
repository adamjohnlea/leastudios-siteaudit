<?php
/**
 * Per-strategy audit pipeline.
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
use LEAStudios\SiteAudit\Shared\Datetime_Util;

/**
 * Runs one audit for a single (URL, strategy) tuple, persists the result,
 * extracts issues from the API response, and writes the comparison row when
 * a previous completed audit exists.
 *
 * The lifecycle, in order:
 *   1. Insert a placeholder audit row in `IN_PROGRESS` so the failure branch
 *      always has a row to update.
 *   2. Call PageSpeed via {@see PageSpeed_Client_Interface}, retrying on 429
 *      using {@see Retry_Strategy}. The audit's `retry_count` is mutated in
 *      place so it survives a final failure.
 *   3. On success: persist score + raw response, mark COMPLETED, extract
 *      issues, persist an Audit_Comparison if a previous audit exists.
 *   4. On failure: mark FAILED with the exception message.
 *
 * Multi-strategy fan-out and the `audit_completed` action live one level up
 * in {@see Audit_Service} — this class only knows about a single tuple.
 */
final class Audit_Pipeline {

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
	 * @param Audit_Repository_Interface            $audit_repository      Audit repo (placeholder + final row).
	 * @param Issue_Repository_Interface            $issue_repository      Issue repo (batch insert per audit).
	 * @param PageSpeed_Client_Interface            $pagespeed_client      PageSpeed transport.
	 * @param Retry_Strategy                        $retry_strategy        429-retry policy.
	 * @param Comparison_Service                    $comparison_service    Pure-function comparator.
	 * @param Audit_Comparison_Repository_Interface $comparison_repository Comparison repo.
	 */
	public function __construct(
		Audit_Repository_Interface $audit_repository,
		Issue_Repository_Interface $issue_repository,
		PageSpeed_Client_Interface $pagespeed_client,
		Retry_Strategy $retry_strategy,
		Comparison_Service $comparison_service,
		Audit_Comparison_Repository_Interface $comparison_repository
	) {
		$this->audit_repository      = $audit_repository;
		$this->issue_repository      = $issue_repository;
		$this->pagespeed_client      = $pagespeed_client;
		$this->retry_strategy        = $retry_strategy;
		$this->comparison_service    = $comparison_service;
		$this->comparison_repository = $comparison_repository;
	}

	/**
	 * Run a single audit for one (URL, strategy) tuple.
	 *
	 * @param Url          $url            URL to audit.
	 * @param Run_Strategy $strategy       Device profile.
	 * @param Audit|null   $previous_audit Prior completed audit for this strategy, or null.
	 *
	 * @return Audit
	 */
	public function run( Url $url, Run_Strategy $strategy, ?Audit $previous_audit ): Audit {
		$now   = Datetime_Util::now();
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
				Datetime_Util::now(),
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
		// Substring → category, evaluated in declaration order. The first
		// matching keyword wins, so order matters: more-specific keywords
		// come first (e.g. `color-contrast` before the generic `color`),
		// and ambiguous overlaps (an audit id containing both `image` and
		// `form`) resolve to whichever appears earlier here.
		$rules = [
			'color-contrast' => Issue_Category::COLOR_CONTRAST,
			'aria'           => Issue_Category::ARIA,
			'label'          => Issue_Category::FORMS,
			'form'           => Issue_Category::FORMS,
			'image'          => Issue_Category::IMAGES,
			'alt'            => Issue_Category::IMAGES,
			'tabindex'       => Issue_Category::NAVIGATION,
			'focus'          => Issue_Category::NAVIGATION,
			'link'           => Issue_Category::NAVIGATION,
			'table'          => Issue_Category::TABLES,
			'th'             => Issue_Category::TABLES,
			'td'             => Issue_Category::TABLES,
		];

		foreach ( $rules as $keyword => $category ) {
			if ( str_contains( $audit_id, $keyword ) ) {
				return $category;
			}
		}

		return Issue_Category::OTHER;
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
