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
	}
}
