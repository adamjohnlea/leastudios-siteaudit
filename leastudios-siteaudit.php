<?php
/**
 * Plugin Name:       LEA Studios Site Audit
 * Plugin URI:        https://leastudios.com/plugins/leastudios-siteaudit
 * Description:       Accessibility monitoring dashboard powered by the Google PageSpeed Insights API. Track scores over time, get email alerts on regressions, export PDF and CSV reports.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            leaStudios
 * Author URI:        https://leastudios.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       leastudios-siteaudit
 * Domain Path:       /languages
 *
 * @package LEAStudios\SiteAudit
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'LEASTUDIOS_SITEAUDIT_VERSION', '1.0.1' );
define( 'LEASTUDIOS_SITEAUDIT_FILE', __FILE__ );
define( 'LEASTUDIOS_SITEAUDIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'LEASTUDIOS_SITEAUDIT_URL', plugin_dir_url( __FILE__ ) );

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong>: %s</p></div>',
				esc_html__( 'LEA Studios Site Audit', 'leastudios-siteaudit' ),
				esc_html__( 'Plugin dependencies are missing. Run "composer install" in the plugin directory.', 'leastudios-siteaudit' )
			);
		}
	);
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

// Action Scheduler ships its own bootstrap that hooks into `plugins_loaded`
// at priority -10 to register its load mechanism (it coordinates versions
// across multiple plugins that bundle it). Require it here, before our
// `plugins_loaded` callback, so the `as_*()` API is available by the time
// our Plugin::init runs.
require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';

/**
 * Initialize the plugin.
 *
 * @return void
 */
function leastudios_siteaudit_init(): void {
	if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
		add_action( 'admin_notices', 'leastudios_siteaudit_php_version_notice' );
		return;
	}

	$plugin = new LEAStudios\SiteAudit\Plugin();
	$plugin->init();
}
add_action( 'plugins_loaded', 'leastudios_siteaudit_init' );

/**
 * Display PHP version notice.
 *
 * @return void
 */
function leastudios_siteaudit_php_version_notice(): void {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'LEA Studios Site Audit requires PHP 8.1 or higher.', 'leastudios-siteaudit' )
	);
}

/**
 * Run on plugin activation.
 *
 * @return void
 */
function leastudios_siteaudit_activate(): void {
	( new LEAStudios\SiteAudit\Activation() )->run();
}
register_activation_hook( __FILE__, 'leastudios_siteaudit_activate' );

/**
 * Run on plugin deactivation.
 *
 * @return void
 */
function leastudios_siteaudit_deactivate(): void {
	( new LEAStudios\SiteAudit\Deactivation() )->run();
}
register_deactivation_hook( __FILE__, 'leastudios_siteaudit_deactivate' );
