<?php
/**
 * Recurring-tick dispatcher: finds due URLs and enqueues per-URL audit actions.
 *
 * @package LEAStudios\SiteAudit\Modules\Scheduler\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Scheduler\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;
use LEAStudios\SiteAudit\Shared\Datetime_Util;

/**
 * Periodic tick handler. Loops every enabled URL, decides whether each is
 * due for re-audit based on its frequency and `last_audited_at`, and enqueues
 * one `leastudios_siteaudit_run_audit` async action per due URL.
 *
 * The recurring tick is cheap (a single SELECT + a handful of enqueue calls);
 * the heavy work happens in the per-URL worker requests, so one slow PageSpeed
 * call cannot starve other URLs in the same tick.
 *
 * Direct port of `ScheduledAuditRunner` from the source app, with one change:
 * the source ran audits inline; this version enqueues them asynchronously via
 * Action Scheduler.
 */
final class Tick_Dispatcher {

	public const RUN_AUDIT_HOOK = 'leastudios_siteaudit_run_audit';

	/**
	 * URL repository.
	 *
	 * @var Url_Repository_Interface
	 */
	private Url_Repository_Interface $url_repository;

	/**
	 * Action enqueuer.
	 *
	 * @var Action_Enqueuer_Interface
	 */
	private Action_Enqueuer_Interface $enqueuer;

	/**
	 * Frequency-to-interval mapper.
	 *
	 * @var Frequency_Interval
	 */
	private Frequency_Interval $interval;

	/**
	 * Constructor.
	 *
	 * @param Url_Repository_Interface  $url_repository URL repo.
	 * @param Action_Enqueuer_Interface $enqueuer       Async action enqueuer.
	 * @param Frequency_Interval        $interval       Frequency-to-hours mapper.
	 */
	public function __construct(
		Url_Repository_Interface $url_repository,
		Action_Enqueuer_Interface $enqueuer,
		Frequency_Interval $interval
	) {
		$this->url_repository = $url_repository;
		$this->enqueuer       = $enqueuer;
		$this->interval       = $interval;
	}

	/**
	 * Run one tick: enqueue per-URL audit actions for every due URL.
	 *
	 * @param \DateTimeImmutable|null $now Reference "current" time for due-checking. Defaults to system time; injected for testability.
	 *
	 * @return int Number of URLs whose audits were enqueued by this tick.
	 */
	public function tick( ?\DateTimeImmutable $now = null ): int {
		$now      = $now ?? Datetime_Util::now();
		$enqueued = 0;

		foreach ( $this->url_repository->find_enabled() as $url ) {
			if ( ! $this->is_due_for_audit( $url, $now ) ) {
				continue;
			}

			$id = $url->id();
			if ( null === $id ) {
				continue;
			}

			// Idempotency guard: a previous tick may have queued this URL but
			// the worker has not run yet (slow PageSpeed call in flight). Skip
			// rather than stack a duplicate.
			if ( $this->enqueuer->has_pending( self::RUN_AUDIT_HOOK, [ $id ] ) ) {
				continue;
			}

			$this->enqueuer->enqueue_async( self::RUN_AUDIT_HOOK, [ $id ] );
			++$enqueued;
		}

		return $enqueued;
	}

	/**
	 * Whether the URL is due for its next scheduled audit.
	 *
	 * @param Url                $url URL to check.
	 * @param \DateTimeImmutable $now Reference time.
	 *
	 * @return bool
	 */
	private function is_due_for_audit( Url $url, \DateTimeImmutable $now ): bool {
		$last_audited_at = $url->last_audited_at();

		if ( null === $last_audited_at ) {
			return true;
		}

		$next_due_at = $last_audited_at->modify( '+' . $this->interval->hours( $url->audit_frequency() ) . ' hours' );

		return $now >= $next_due_at;
	}
}
