<?php
/**
 * Plugin activation handler.
 *
 * @package LEAStudios\SiteAudit
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Database\Migration;

/**
 * Runs once when the plugin is activated: creates tables, registers
 * capabilities, seeds default options.
 */
final class Activation {

	/**
	 * Default plugin options.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_options(): array {
		return [
			'pagespeed_api_key'       => '',
			'pagespeed_rate_limit'    => 5,
			'pagespeed_retry_count'   => 3,
			'default_audit_frequency' => 'weekly',
			'default_audit_strategy'  => 'both',
		];
	}

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public function run(): void {
		( new Migration() )->maybe_migrate();

		Capabilities::add();

		if ( false === get_option( 'leastudios_siteaudit_options' ) ) {
			update_option( 'leastudios_siteaudit_options', self::default_options() );
		}
	}
}
