<?php
/**
 * Main plugin bootstrap class.
 *
 * @package LEAStudios\SiteAudit
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Admin\Settings_Page;
use LEAStudios\SiteAudit\Database\Migration;
use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Audit_Service;
use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Audit_Service_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Comparison_Service;
use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Trend_Calculator;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Comparison_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Issue_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\PageSpeed_Api_Client;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Wp_Http_Client;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\RateLimiting\Retry_Strategy;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Audit_Comparison_Repository;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Audit_Repository;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Issue_Repository;
use LEAStudios\SiteAudit\Modules\Dashboard\Admin\Dashboard_Controller;
use LEAStudios\SiteAudit\Modules\Dashboard\Application\Services\Dashboard_Statistics;
use LEAStudios\SiteAudit\Modules\Reporting\Admin\Reporting_Controller;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Csv_Export_Service;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Pdf_Report_Data_Collector;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Pdf_Report_Service;
use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Action_Enqueuer_Interface;
use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Audit_Worker;
use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Frequency_Interval;
use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Tick_Dispatcher;
use LEAStudios\SiteAudit\Modules\Scheduler\Infrastructure\As_Action_Enqueuer;
use LEAStudios\SiteAudit\Modules\Url\Admin\Project_Controller;
use LEAStudios\SiteAudit\Modules\Url\Admin\Url_Controller;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Bulk_Import_Service;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Project_Service;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Url_Service;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Project_Repository;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Url_Repository;

/**
 * Wires all plugin components together.
 */
final class Plugin {

	public const TICK_HOOK = 'leastudios_siteaudit_tick';

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );

		// Self-healing recurring-tick registration. Runs on every request at
		// `init` priority 20 (after Action Scheduler is fully loaded). The
		// `as_*` calls are gated so first-page-load schedules and every
		// subsequent load is a no-op. This avoids the activation-timing trap
		// where AS isn't loaded yet during register_activation_hook callbacks.
		add_action( 'init', [ $this, 'register_recurring_tick' ], 20 );

		( new Migration() )->maybe_migrate();

		$project_repository    = new Wpdb_Project_Repository();
		$url_repository        = new Wpdb_Url_Repository();
		$audit_repository      = new Wpdb_Audit_Repository();
		$issue_repository      = new Wpdb_Issue_Repository();
		$comparison_repository = new Wpdb_Audit_Comparison_Repository();

		$audit_service = $this->build_audit_service(
			$url_repository,
			$audit_repository,
			$issue_repository,
			$comparison_repository
		);

		$enqueuer = new As_Action_Enqueuer();

		// Scheduler hooks fire on Action Scheduler's own loopback requests
		// (which are not admin context), so wire them up unconditionally.
		$this->register_scheduler_hooks( $url_repository, $audit_service, $enqueuer );

		if ( is_admin() ) {
			$this->init_admin(
				$project_repository,
				$url_repository,
				$audit_repository,
				$issue_repository,
				$enqueuer
			);
		}
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'leastudios-siteaudit',
			false,
			dirname( plugin_basename( LEASTUDIOS_SITEAUDIT_FILE ) ) . '/languages'
		);
	}

	/**
	 * Schedule the recurring tick if not already scheduled.
	 *
	 * Idempotent and self-healing — runs every page load. We can't do this
	 * from `register_activation_hook` because Action Scheduler bootstraps on
	 * `plugins_loaded` priority -10, which is later than activation callbacks.
	 *
	 * @return void
	 */
	public function register_recurring_tick(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( as_has_scheduled_action( self::TICK_HOOK ) ) {
			return;
		}

		as_schedule_recurring_action( time(), HOUR_IN_SECONDS, self::TICK_HOOK, [], 'leastudios-siteaudit' );
	}

	/**
	 * Wire the recurring-tick and per-URL audit hooks to their handlers.
	 *
	 * @param Url_Repository_Interface  $url_repository URL repo.
	 * @param Audit_Service_Interface   $audit_service  Audit application service.
	 * @param Action_Enqueuer_Interface $enqueuer       Async action enqueuer.
	 *
	 * @return void
	 */
	private function register_scheduler_hooks(
		Url_Repository_Interface $url_repository,
		Audit_Service_Interface $audit_service,
		Action_Enqueuer_Interface $enqueuer
	): void {
		$dispatcher = new Tick_Dispatcher( $url_repository, $enqueuer, new Frequency_Interval() );
		$worker     = new Audit_Worker( $audit_service );

		add_action(
			self::TICK_HOOK,
			static function () use ( $dispatcher ): void {
				$dispatcher->tick();
			}
		);

		add_action(
			Tick_Dispatcher::RUN_AUDIT_HOOK,
			static function ( int $url_id ) use ( $worker ): void {
				$worker->run( $url_id );
			},
			10,
			1
		);
	}

	/**
	 * Initialize admin-specific functionality.
	 *
	 * @param Wpdb_Project_Repository   $project_repository Project repo.
	 * @param Wpdb_Url_Repository       $url_repository     URL repo.
	 * @param Wpdb_Audit_Repository     $audit_repository   Audit repo.
	 * @param Wpdb_Issue_Repository     $issue_repository   Issue repo.
	 * @param Action_Enqueuer_Interface $enqueuer           Async action enqueuer for "Run audit now".
	 *
	 * @return void
	 */
	private function init_admin(
		Wpdb_Project_Repository $project_repository,
		Wpdb_Url_Repository $url_repository,
		Wpdb_Audit_Repository $audit_repository,
		Wpdb_Issue_Repository $issue_repository,
		Action_Enqueuer_Interface $enqueuer
	): void {
		( new Settings_Page() )->init();

		$project_service     = new Project_Service( $project_repository );
		$url_service         = new Url_Service( $url_repository );
		$bulk_import_service = new Bulk_Import_Service( $url_repository );

		$statistics = new Dashboard_Statistics();

		( new Dashboard_Controller(
			$project_repository,
			$url_repository,
			$audit_repository,
			$issue_repository,
			$statistics,
			new Trend_Calculator()
		) )->init();

		( new Project_Controller( $project_service ) )->init();
		( new Url_Controller(
			$url_service,
			$project_service,
			$bulk_import_service,
			$enqueuer,
			$audit_repository
		) )->init();

		( new Reporting_Controller(
			$project_repository,
			$url_repository,
			$audit_repository,
			$statistics,
			new Csv_Export_Service(),
			new Pdf_Report_Data_Collector(
				$url_repository,
				$audit_repository,
				$issue_repository,
				$statistics
			),
			new Pdf_Report_Service()
		) )->init();
	}

	/**
	 * Assemble the audit pipeline (HTTP + PageSpeed client + retry + service).
	 *
	 * @param Url_Repository_Interface              $url_repository        URL repo.
	 * @param Audit_Repository_Interface            $audit_repository      Audit repo.
	 * @param Issue_Repository_Interface            $issue_repository      Issue repo.
	 * @param Audit_Comparison_Repository_Interface $comparison_repository Comparison repo.
	 *
	 * @return Audit_Service
	 */
	private function build_audit_service(
		Url_Repository_Interface $url_repository,
		Audit_Repository_Interface $audit_repository,
		Issue_Repository_Interface $issue_repository,
		Audit_Comparison_Repository_Interface $comparison_repository
	): Audit_Service {
		$defaults = Activation::default_options();
		$stored   = get_option( Settings_Page::OPTION_NAME, $defaults );
		$options  = is_array( $stored ) ? array_merge( $defaults, $stored ) : $defaults;

		$api_key     = (string) ( $options['pagespeed_api_key'] ?? '' );
		$retry_count = (int) ( $options['pagespeed_retry_count'] ?? 3 );

		return new Audit_Service(
			$url_repository,
			$audit_repository,
			$issue_repository,
			new PageSpeed_Api_Client( new Wp_Http_Client(), $api_key ),
			new Retry_Strategy( $retry_count ),
			new Comparison_Service(),
			$comparison_repository
		);
	}
}
