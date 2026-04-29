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

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );

		( new Migration() )->maybe_migrate();

		if ( is_admin() ) {
			$this->init_admin();
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
	 * Initialize admin-specific functionality.
	 *
	 * @return void
	 */
	private function init_admin(): void {
		( new Settings_Page() )->init();

		$project_repository    = new Wpdb_Project_Repository();
		$url_repository        = new Wpdb_Url_Repository();
		$audit_repository      = new Wpdb_Audit_Repository();
		$issue_repository      = new Wpdb_Issue_Repository();
		$comparison_repository = new Wpdb_Audit_Comparison_Repository();

		$project_service     = new Project_Service( $project_repository );
		$url_service         = new Url_Service( $url_repository );
		$bulk_import_service = new Bulk_Import_Service( $url_repository );

		$audit_service = $this->build_audit_service(
			$url_repository,
			$audit_repository,
			$issue_repository,
			$comparison_repository
		);

		( new Dashboard_Controller(
			$project_repository,
			$url_repository,
			$audit_repository,
			$issue_repository,
			new Dashboard_Statistics(),
			new Trend_Calculator()
		) )->init();

		( new Project_Controller( $project_service ) )->init();
		( new Url_Controller(
			$url_service,
			$project_service,
			$bulk_import_service,
			$audit_service,
			$audit_repository
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
