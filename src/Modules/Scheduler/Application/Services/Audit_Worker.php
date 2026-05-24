<?php
/**
 * Action Scheduler worker for per-URL audit runs.
 *
 * @package LEAStudios\SiteAudit\Modules\Scheduler\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Scheduler\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Audit_Service_Interface;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;

/**
 * Listens to `leastudios_siteaudit_run_audit` and runs the audit synchronously
 * within Action Scheduler's worker request.
 *
 * The worker request is a separate HTTP/CLI request from whatever enqueued
 * the action, so PageSpeed's ~30s response time tying up an FPM worker no
 * longer blocks the user-facing request that triggered the audit. The PHP
 * timeout is also lifted up-front because each audit can fire 1–2 PageSpeed
 * calls back-to-back.
 *
 * If the URL was deleted between enqueue and execution, `Audit_Service`
 * throws `Validation_Exception` and we swallow it: the action is treated as
 * succeeded (no retry), since the work is genuinely no longer needed.
 */
final class Audit_Worker {

	/**
	 * Audit orchestration service.
	 *
	 * @var Audit_Service_Interface
	 */
	private Audit_Service_Interface $audit_service;

	/**
	 * Constructor.
	 *
	 * @param Audit_Service_Interface $audit_service Audit application service.
	 */
	public function __construct( Audit_Service_Interface $audit_service ) {
		$this->audit_service = $audit_service;
	}

	/**
	 * Execute one audit for the URL identified by `$url_id`.
	 *
	 * @param int $url_id URL row id.
	 *
	 * @return void
	 */
	public function run( int $url_id ): void {
		// Lift the per-request timeout — Action Scheduler runs its own loopback
		// requests via wp-cron, which inherit PHP's default `max_execution_time`.
		// PageSpeed regularly exceeds 30s and `audit_strategy=both` runs two calls
		// back-to-back, so we'd otherwise fatal mid-flight. Guard with
		// function_exists() because some hosts disable set_time_limit via
		// disable_functions.
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 );
		}

		try {
			$this->audit_service->run_audit( $url_id );
		} catch ( Validation_Exception $e ) {
			// URL deleted between enqueue and run; nothing to do.
			return;
		}
	}
}
