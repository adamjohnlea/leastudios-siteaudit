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
use LEAStudios\SiteAudit\Modules\Url\Admin\Project_Controller;
use LEAStudios\SiteAudit\Modules\Url\Admin\Url_Controller;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Bulk_Import_Service;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Project_Service;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Url_Service;
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

		$project_repository = new Wpdb_Project_Repository();
		$url_repository     = new Wpdb_Url_Repository();

		$project_service     = new Project_Service( $project_repository );
		$url_service         = new Url_Service( $url_repository );
		$bulk_import_service = new Bulk_Import_Service( $url_repository );

		( new Project_Controller( $project_service ) )->init();
		( new Url_Controller( $url_service, $project_service, $bulk_import_service ) )->init();
	}
}
