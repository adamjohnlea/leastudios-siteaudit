<?php
/**
 * Persistent admin notice when no PageSpeed API key is configured.
 *
 * @package LEAStudios\SiteAudit\Admin
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Admin;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Activation;
use LEAStudios\SiteAudit\Capabilities;

/**
 * Surfaces a warning notice on every admin page until the site owner
 * configures a PageSpeed API key. Without a key the audit pipeline is inert,
 * so a quiet failure mode would leave operators wondering why the dashboard
 * never populates after activation.
 *
 * Suppressed on the plugin's own Settings screen — the form is already there
 * and a stacked banner just adds noise.
 */
final class Api_Key_Notice {

	/**
	 * Register the `admin_notices` hook.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_notices', [ $this, 'maybe_render' ] );
	}

	/**
	 * Render the notice if the prerequisites match. Public so it can be
	 * exercised directly from tests without re-running the hook.
	 *
	 * @return void
	 */
	public function maybe_render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}

		if ( $this->has_api_key() ) {
			return;
		}

		if ( $this->is_on_settings_screen() ) {
			return;
		}

		$settings_url = add_query_arg(
			'page',
			Settings_Page::PAGE_SLUG,
			admin_url( 'admin.php' )
		);

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong>: %s <a href="%s">%s</a>.</p></div>',
			esc_html__( 'LEA Studios Site Audit', 'leastudios-siteaudit' ),
			esc_html__( 'A Google PageSpeed Insights API key is required to run audits.', 'leastudios-siteaudit' ),
			esc_url( $settings_url ),
			esc_html__( 'Add your API key in Settings', 'leastudios-siteaudit' )
		);
	}

	/**
	 * Check whether a non-empty API key is stored.
	 *
	 * @return bool
	 */
	private function has_api_key(): bool {
		$defaults = Activation::default_options();
		$stored   = get_option( Settings_Page::OPTION_NAME, $defaults );
		$merged   = is_array( $stored ) ? array_merge( $defaults, $stored ) : $defaults;
		$api_key  = (string) ( $merged['pagespeed_api_key'] ?? '' );

		return '' !== trim( $api_key );
	}

	/**
	 * Detect whether the current screen is the plugin's Settings page.
	 *
	 * @return bool
	 */
	private function is_on_settings_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( null === $screen ) {
			return false;
		}

		return false !== strpos( (string) $screen->id, Settings_Page::PAGE_SLUG );
	}
}
