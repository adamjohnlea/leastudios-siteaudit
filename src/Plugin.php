<?php
/**
 * Main plugin bootstrap class.
 *
 * @package LEAStudios\SiteAudit
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Admin\Api_Key_Notice;
use LEAStudios\SiteAudit\Admin\Settings_Page;
use LEAStudios\SiteAudit\Database\Migration;
use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Audit_Pipeline;
use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Audit_Service;
use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Comparison_Service;
use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Trend_Calculator;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\PageSpeed_Api_Client;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api\Wp_Http_Client;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\RateLimiting\Retry_Strategy;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Audit_Comparison_Repository;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Audit_Repository;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Issue_Repository;
use LEAStudios\SiteAudit\Modules\Dashboard\Admin\Dashboard_Controller;
use LEAStudios\SiteAudit\Modules\Dashboard\Application\Services\Dashboard_Statistics;
use LEAStudios\SiteAudit\Modules\Notification\Admin\Subscription_Controller;
use LEAStudios\SiteAudit\Modules\Notification\Application\Privacy_Hooks;
use LEAStudios\SiteAudit\Modules\Notification\Application\Services\Alert_Notifier;
use LEAStudios\SiteAudit\Modules\Notification\Application\Services\Audit_Report_Notifier;
use LEAStudios\SiteAudit\Modules\Notification\Application\Services\Wp_Mail_Service;
use LEAStudios\SiteAudit\Modules\Notification\Infrastructure\Repositories\Wpdb_Email_Subscription_Repository;
use LEAStudios\SiteAudit\Modules\Reporting\Admin\Reporting_Controller;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Csv_Export_Service;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Pdf_Report_Data_Collector;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Pdf_Report_Service;
use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Audit_Worker;
use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Frequency_Interval;
use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Tick_Dispatcher;
use LEAStudios\SiteAudit\Modules\Scheduler\Infrastructure\As_Action_Enqueuer;
use LEAStudios\SiteAudit\Modules\Url\Admin\Project_Controller;
use LEAStudios\SiteAudit\Modules\Url\Admin\Url_Controller;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Bulk_Import_Service;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Project_Service;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Url_Service;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Project_Repository;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Url_Repository;
use LEAStudios\SiteAudit\Shared\Container;
use LEAStudios\SiteAudit\Shared\Template_Renderer;

/**
 * Wires all plugin components together.
 */
final class Plugin {

	public const TICK_HOOK = 'leastudios_siteaudit_tick';

	/**
	 * Service container, populated once in init() and consumed by the
	 * register_scheduler_hooks / register_notification_hooks / init_admin
	 * helpers below.
	 *
	 * @var Container
	 */
	private Container $container;

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

		$this->container = new Container();
		$this->register_services( $this->container );

		// Register WP privacy exporter/eraser callbacks for the
		// email_subscriptions table (the only PII surface we own).
		( new Privacy_Hooks() )->register();

		// Scheduler hooks fire on Action Scheduler's own loopback requests
		// (which are not admin context), so wire them up unconditionally.
		$this->register_scheduler_hooks( $this->container );

		// Notification hooks fire on every successful audit, including those
		// running in the Action Scheduler worker (non-admin context). Wire
		// unconditionally for the same reason.
		$this->register_notification_hooks( $this->container );

		if ( is_admin() ) {
			$this->init_admin( $this->container );
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
		// Once we've confirmed the recurring action is scheduled, cache the
		// affirmative for an hour so we skip the AS lookup on every page
		// load. We re-check on a 1h window so a manually-cleared schedule
		// gets healed in at most an hour.
		if ( false !== get_transient( 'leastudios_siteaudit_tick_scheduled' ) ) {
			return;
		}

		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( as_has_scheduled_action( self::TICK_HOOK ) ) {
			set_transient( 'leastudios_siteaudit_tick_scheduled', 1, HOUR_IN_SECONDS );
			return;
		}

		as_schedule_recurring_action( time(), HOUR_IN_SECONDS, self::TICK_HOOK, [], 'leastudios-siteaudit' );
		set_transient( 'leastudios_siteaudit_tick_scheduled', 1, HOUR_IN_SECONDS );
	}

	/**
	 * Register every service factory on the container. Each `set()` is a
	 * lazy closure — services are constructed on first `get()` and cached.
	 *
	 * @param Container $c Container to populate.
	 *
	 * @return void
	 */
	private function register_services( Container $c ): void {
		// Repositories.
		$c->set( 'project_repository', static fn() => new Wpdb_Project_Repository() );
		$c->set( 'url_repository', static fn() => new Wpdb_Url_Repository() );
		$c->set( 'audit_repository', static fn() => new Wpdb_Audit_Repository() );
		$c->set( 'issue_repository', static fn() => new Wpdb_Issue_Repository() );
		$c->set( 'comparison_repository', static fn() => new Wpdb_Audit_Comparison_Repository() );
		$c->set( 'subscription_repository', static fn() => new Wpdb_Email_Subscription_Repository() );

		// Domain services.
		$c->set( 'statistics', static fn() => new Dashboard_Statistics() );
		$c->set( 'trend_calculator', static fn() => new Trend_Calculator() );
		$c->set( 'comparison_service', static fn() => new Comparison_Service() );
		$c->set( 'csv_export_service', static fn() => new Csv_Export_Service() );

		// Infrastructure.
		$c->set( 'enqueuer', static fn() => new As_Action_Enqueuer() );
		$c->set( 'email_service', static fn() => new Wp_Mail_Service() );
		$c->set( 'pdf_report_service', static fn() => new Pdf_Report_Service() );
		$c->set(
			'template_renderer',
			static fn() => new Template_Renderer( LEASTUDIOS_SITEAUDIT_DIR . 'templates' )
		);

		// Composite services with dependencies.
		$c->set(
			'pdf_data_collector',
			static fn( Container $c ) => new Pdf_Report_Data_Collector(
				$c->get( 'url_repository' ),
				$c->get( 'audit_repository' ),
				$c->get( 'issue_repository' ),
				$c->get( 'statistics' )
			)
		);

		// Audit pipeline + service.
		$c->set(
			'audit_pipeline',
			static function ( Container $c ): Audit_Pipeline {
				$defaults = Activation::default_options();
				$stored   = get_option( Settings_Page::OPTION_NAME, $defaults );
				$options  = is_array( $stored ) ? array_merge( $defaults, $stored ) : $defaults;

				$api_key     = (string) ( $options['pagespeed_api_key'] ?? '' );
				$retry_count = (int) ( $options['pagespeed_retry_count'] ?? 3 );

				return new Audit_Pipeline(
					$c->get( 'audit_repository' ),
					$c->get( 'issue_repository' ),
					new PageSpeed_Api_Client( new Wp_Http_Client(), $api_key ),
					new Retry_Strategy( $retry_count ),
					$c->get( 'comparison_service' ),
					$c->get( 'comparison_repository' )
				);
			}
		);
		$c->set(
			'audit_service',
			static fn( Container $c ) => new Audit_Service(
				$c->get( 'url_repository' ),
				$c->get( 'audit_repository' ),
				$c->get( 'audit_pipeline' )
			)
		);

		// Notifiers.
		$c->set(
			'alert_notifier',
			static fn( Container $c ) => new Alert_Notifier(
				$c->get( 'subscription_repository' ),
				$c->get( 'email_service' ),
				$c->get( 'template_renderer' )
			)
		);
		$c->set(
			'report_notifier',
			static fn( Container $c ) => new Audit_Report_Notifier(
				$c->get( 'project_repository' ),
				$c->get( 'subscription_repository' ),
				$c->get( 'pdf_data_collector' ),
				$c->get( 'pdf_report_service' ),
				$c->get( 'email_service' ),
				$c->get( 'template_renderer' )
			)
		);

		// Application services (admin-side).
		$c->set(
			'project_service',
			static fn( Container $c ) => new Project_Service( $c->get( 'project_repository' ) )
		);
		$c->set(
			'url_service',
			static fn( Container $c ) => new Url_Service( $c->get( 'url_repository' ) )
		);
		$c->set(
			'bulk_import_service',
			static fn( Container $c ) => new Bulk_Import_Service( $c->get( 'url_repository' ) )
		);
	}

	/**
	 * Wire the recurring-tick and per-URL audit hooks to their handlers.
	 *
	 * @param Container $c Service container.
	 *
	 * @return void
	 */
	private function register_scheduler_hooks( Container $c ): void {
		$dispatcher = new Tick_Dispatcher( $c->get( 'url_repository' ), $c->get( 'enqueuer' ), new Frequency_Interval() );
		$worker     = new Audit_Worker( $c->get( 'audit_service' ) );

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
	 * Wire `Alert_Notifier` and `Audit_Report_Notifier` to the audit-completed
	 * action. Fires unconditionally because the worker that emits the action
	 * runs in non-admin context (Action Scheduler loopback request).
	 *
	 * @param Container $c Service container.
	 *
	 * @return void
	 */
	private function register_notification_hooks( Container $c ): void {
		add_action(
			'leastudios_siteaudit_audit_completed',
			[ $c->get( 'alert_notifier' ), 'notify_if_threshold_breached' ],
			10,
			3
		);

		add_action(
			'leastudios_siteaudit_audit_completed',
			[ $c->get( 'report_notifier' ), 'on_audit_completed' ],
			10,
			3
		);

		// Deferred cleanup of attachment temp files (see Wp_Mail_Service for why).
		add_action(
			Wp_Mail_Service::CLEANUP_HOOK,
			[ $c->get( 'email_service' ), 'cleanup_attachment' ],
			10,
			1
		);
	}

	/**
	 * Initialize admin-specific functionality.
	 *
	 * @param Container $c Service container.
	 *
	 * @return void
	 */
	private function init_admin( Container $c ): void {
		( new Settings_Page() )->init();
		( new Api_Key_Notice() )->init();

		( new Dashboard_Controller(
			$c->get( 'project_repository' ),
			$c->get( 'url_repository' ),
			$c->get( 'audit_repository' ),
			$c->get( 'issue_repository' ),
			$c->get( 'statistics' ),
			$c->get( 'trend_calculator' ),
			$c->get( 'subscription_repository' ),
			$c->get( 'template_renderer' )
		) )->init();

		( new Project_Controller( $c->get( 'project_service' ), $c->get( 'template_renderer' ) ) )->init();

		( new Url_Controller(
			$c->get( 'url_service' ),
			$c->get( 'project_service' ),
			$c->get( 'bulk_import_service' ),
			$c->get( 'enqueuer' ),
			$c->get( 'audit_repository' ),
			$c->get( 'template_renderer' )
		) )->init();

		( new Reporting_Controller(
			$c->get( 'project_repository' ),
			$c->get( 'url_repository' ),
			$c->get( 'audit_repository' ),
			$c->get( 'statistics' ),
			$c->get( 'csv_export_service' ),
			$c->get( 'pdf_data_collector' ),
			$c->get( 'pdf_report_service' )
		) )->init();

		( new Subscription_Controller(
			$c->get( 'project_repository' ),
			$c->get( 'subscription_repository' )
		) )->init();
	}
}
