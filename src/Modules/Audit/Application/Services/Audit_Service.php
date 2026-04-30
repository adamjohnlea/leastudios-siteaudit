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
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Shared\Datetime_Util;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;

/**
 * Plans the per-strategy work for a single `run_audit($url_id)` call,
 * snapshots the prior completed audits before any new audit begins, fans
 * out to {@see Audit_Pipeline}, and fires the `audit_completed` action
 * exactly once after every strategy has finished.
 *
 * The per-strategy lifecycle (PageSpeed call + retry + persist + issue
 * extraction + comparison row) lives in {@see Audit_Pipeline}; this
 * service is just the orchestrator.
 *
 * Why snapshot-then-run is service-level (not pipeline-level): listeners
 * receive a per-strategy map of `previous_audit`s, and we want every
 * snapshot in that map to reflect the state *before any audit in this run
 * has started*. Sequencing the snapshots inside the pipeline would let the
 * second strategy's snapshot see the first strategy's freshly-saved row,
 * which would change what the alert notifier sees.
 */
final class Audit_Service implements Audit_Service_Interface {

	/**
	 * URL repository.
	 *
	 * @var Url_Repository_Interface
	 */
	private readonly Url_Repository_Interface $url_repository;

	/**
	 * Audit repository (used here only for the prior-audit snapshot).
	 *
	 * @var Audit_Repository_Interface
	 */
	private readonly Audit_Repository_Interface $audit_repository;

	/**
	 * Per-strategy audit pipeline.
	 *
	 * @var Audit_Pipeline
	 */
	private readonly Audit_Pipeline $pipeline;

	/**
	 * Constructor.
	 *
	 * @param Url_Repository_Interface   $url_repository   URL repo (find target).
	 * @param Audit_Repository_Interface $audit_repository Audit repo (snapshot prior completions).
	 * @param Audit_Pipeline             $pipeline         Per-strategy pipeline.
	 */
	public function __construct(
		Url_Repository_Interface $url_repository,
		Audit_Repository_Interface $audit_repository,
		Audit_Pipeline $pipeline
	) {
		$this->url_repository   = $url_repository;
		$this->audit_repository = $audit_repository;
		$this->pipeline         = $pipeline;
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

		// Snapshot prior completed audits BEFORE running anything. The
		// per-strategy snapshots are passed to listeners so the alert
		// notifier can compute drop deltas, and they're used inline below
		// for the comparison rows.
		$previous_audits = [];
		foreach ( $strategies as $strategy ) {
			$previous_audits[ $strategy->value ] = $this->audit_repository->find_latest_completed_by_url_id_and_strategy(
				$url->id() ?? 0,
				$strategy
			);
		}

		$results = [];
		foreach ( $strategies as $strategy ) {
			$results[] = $this->pipeline->run( $url, $strategy, $previous_audits[ $strategy->value ] );
		}

		$now = Datetime_Util::now();
		$url->set_last_audited_at( $now );
		$url->set_updated_at( $now );
		$this->url_repository->update( $url );

		// Fire ONE audit-completed action per run_audit() call (not per
		// strategy). Listeners receive the URL plus the full collection of
		// audits from this run. This collapses 4 emails per `both`-strategy
		// run (2 alerts + 2 reports) to 2 (1 alert + 1 report) and
		// eliminates the second Dompdf render that was causing memory
		// pressure / empty-PDF attachments.
		/**
		 * Fires once after every strategy in a run_audit() call has finished.
		 *
		 * @param Url                       $url             URL audited.
		 * @param array<int, Audit>         $audits          Audits produced by this run, one per strategy.
		 * @param array<string, Audit|null> $previous_audits Map of `Run_Strategy::value` => prior completed audit (null if first run for that strategy).
		 */
		do_action( 'leastudios_siteaudit_audit_completed', $url, $results, $previous_audits );

		return $results;
	}
}
