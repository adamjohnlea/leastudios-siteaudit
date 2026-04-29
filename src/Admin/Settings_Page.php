<?php
/**
 * Settings page handler.
 *
 * @package LEAStudios\SiteAudit\Admin
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Admin;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Activation;
use LEAStudios\SiteAudit\Capabilities;

/**
 * Top-level "LEA Studios Site Audit" admin menu plus its Settings sub-page.
 *
 * Phase 1 ships only the Settings page (PageSpeed API key, rate limit, retry
 * count, default frequency, default strategy). The dashboard / projects / urls
 * sub-pages are added in subsequent phases.
 */
final class Settings_Page {

	private const OPTION_GROUP = 'leastudios_siteaudit_settings';
	private const OPTION_NAME  = 'leastudios_siteaudit_options';
	private const PAGE_SLUG    = 'leastudios-siteaudit-settings';
	private const MENU_SLUG    = 'leastudios-siteaudit';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register the top-level menu and the Settings sub-page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'LEA Studios Site Audit', 'leastudios-siteaudit' ),
			__( 'Site Audit', 'leastudios-siteaudit' ),
			Capabilities::VIEW,
			self::MENU_SLUG,
			[ $this, 'render_placeholder' ],
			'dashicons-universal-access-alt',
			80
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Site Audit Settings', 'leastudios-siteaudit' ),
			__( 'Settings', 'leastudios-siteaudit' ),
			Capabilities::MANAGE,
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Register the option, settings section, and individual fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_options' ],
				'default'           => Activation::default_options(),
			]
		);

		add_settings_section(
			'leastudios_siteaudit_pagespeed',
			__( 'PageSpeed Insights', 'leastudios-siteaudit' ),
			static function (): void {
				echo '<p>';
				echo esc_html__( 'Audits use the Google PageSpeed Insights API. A free key is sufficient for most installs; rate-limited installs should request a higher quota.', 'leastudios-siteaudit' );
				echo '</p>';
			},
			self::PAGE_SLUG
		);

		add_settings_field(
			'pagespeed_api_key',
			__( 'API key', 'leastudios-siteaudit' ),
			[ $this, 'render_text_field' ],
			self::PAGE_SLUG,
			'leastudios_siteaudit_pagespeed',
			[
				'key'         => 'pagespeed_api_key',
				'type'        => 'password',
				'description' => __( 'Get a key from the Google Cloud Console.', 'leastudios-siteaudit' ),
			]
		);

		add_settings_field(
			'pagespeed_rate_limit',
			__( 'Rate limit (requests/sec)', 'leastudios-siteaudit' ),
			[ $this, 'render_number_field' ],
			self::PAGE_SLUG,
			'leastudios_siteaudit_pagespeed',
			[
				'key' => 'pagespeed_rate_limit',
				'min' => 1,
				'max' => 60,
			]
		);

		add_settings_field(
			'pagespeed_retry_count',
			__( 'Retry count', 'leastudios-siteaudit' ),
			[ $this, 'render_number_field' ],
			self::PAGE_SLUG,
			'leastudios_siteaudit_pagespeed',
			[
				'key' => 'pagespeed_retry_count',
				'min' => 0,
				'max' => 10,
			]
		);

		add_settings_section(
			'leastudios_siteaudit_defaults',
			__( 'Defaults for new URLs', 'leastudios-siteaudit' ),
			'__return_empty_string',
			self::PAGE_SLUG
		);

		add_settings_field(
			'default_audit_frequency',
			__( 'Audit frequency', 'leastudios-siteaudit' ),
			[ $this, 'render_select_field' ],
			self::PAGE_SLUG,
			'leastudios_siteaudit_defaults',
			[
				'key'     => 'default_audit_frequency',
				'options' => [
					'daily'    => __( 'Daily', 'leastudios-siteaudit' ),
					'weekly'   => __( 'Weekly', 'leastudios-siteaudit' ),
					'biweekly' => __( 'Biweekly', 'leastudios-siteaudit' ),
					'monthly'  => __( 'Monthly', 'leastudios-siteaudit' ),
				],
			]
		);

		add_settings_field(
			'default_audit_strategy',
			__( 'Audit strategy', 'leastudios-siteaudit' ),
			[ $this, 'render_select_field' ],
			self::PAGE_SLUG,
			'leastudios_siteaudit_defaults',
			[
				'key'     => 'default_audit_strategy',
				'options' => [
					'desktop' => __( 'Desktop only', 'leastudios-siteaudit' ),
					'mobile'  => __( 'Mobile only', 'leastudios-siteaudit' ),
					'both'    => __( 'Desktop and mobile', 'leastudios-siteaudit' ),
				],
			]
		);
	}

	/**
	 * Sanitize submitted options.
	 *
	 * @param array<string, mixed>|mixed $input Raw input from `options.php`.
	 * @return array<string, mixed> Sanitized values, merged over defaults.
	 */
	public function sanitize_options( $input ): array {
		$defaults  = Activation::default_options();
		$current   = get_option( self::OPTION_NAME, $defaults );
		$base      = is_array( $current ) ? array_merge( $defaults, $current ) : $defaults;
		$input_arr = is_array( $input ) ? $input : [];

		$sanitized = $base;

		if ( array_key_exists( 'pagespeed_api_key', $input_arr ) ) {
			$sanitized['pagespeed_api_key'] = sanitize_text_field( (string) $input_arr['pagespeed_api_key'] );
		}

		if ( array_key_exists( 'pagespeed_rate_limit', $input_arr ) ) {
			$sanitized['pagespeed_rate_limit'] = max( 1, min( 60, absint( $input_arr['pagespeed_rate_limit'] ) ) );
		}

		if ( array_key_exists( 'pagespeed_retry_count', $input_arr ) ) {
			$sanitized['pagespeed_retry_count'] = max( 0, min( 10, absint( $input_arr['pagespeed_retry_count'] ) ) );
		}

		$valid_frequencies = [ 'daily', 'weekly', 'biweekly', 'monthly' ];
		if ( isset( $input_arr['default_audit_frequency'] ) && in_array( (string) $input_arr['default_audit_frequency'], $valid_frequencies, true ) ) {
			$sanitized['default_audit_frequency'] = (string) $input_arr['default_audit_frequency'];
		}

		$valid_strategies = [ 'desktop', 'mobile', 'both' ];
		if ( isset( $input_arr['default_audit_strategy'] ) && in_array( (string) $input_arr['default_audit_strategy'], $valid_strategies, true ) ) {
			$sanitized['default_audit_strategy'] = (string) $input_arr['default_audit_strategy'];
		}

		return $sanitized;
	}

	/**
	 * Render a text/password input bound to a key in the options array.
	 *
	 * @param array<string, mixed> $args Field args ('key', 'type', 'description').
	 * @return void
	 */
	public function render_text_field( array $args ): void {
		$key         = (string) ( $args['key'] ?? '' );
		$type        = (string) ( $args['type'] ?? 'text' );
		$description = (string) ( $args['description'] ?? '' );
		$value       = (string) ( $this->get_option_value( $key ) ?? '' );

		printf(
			'<input type="%s" id="%s" name="%s[%s]" value="%s" class="regular-text" autocomplete="off" />',
			esc_attr( $type ),
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( $value )
		);

		if ( '' !== $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	/**
	 * Render a number input bound to a key in the options array.
	 *
	 * @param array<string, mixed> $args Field args ('key', 'min', 'max').
	 * @return void
	 */
	public function render_number_field( array $args ): void {
		$key   = (string) ( $args['key'] ?? '' );
		$min   = (int) ( $args['min'] ?? 0 );
		$max   = (int) ( $args['max'] ?? PHP_INT_MAX );
		$value = $this->get_option_value( $key );

		printf(
			'<input type="number" id="%s" name="%s[%s]" value="%s" min="%s" max="%s" class="small-text" />',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( (string) ( null === $value ? '' : $value ) ),
			esc_attr( (string) $min ),
			esc_attr( (string) $max )
		);
	}

	/**
	 * Render a select bound to a key in the options array.
	 *
	 * @param array<string, mixed> $args Field args ('key', 'options' => label map).
	 * @return void
	 */
	public function render_select_field( array $args ): void {
		$key     = (string) ( $args['key'] ?? '' );
		$options = is_array( $args['options'] ?? null ) ? $args['options'] : [];
		$current = (string) ( $this->get_option_value( $key ) ?? '' );

		printf(
			'<select id="%s" name="%s[%s]">',
			esc_attr( $key ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key )
		);

		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( (string) $value ),
				selected( (string) $value, $current, false ),
				esc_html( (string) $label )
			);
		}

		echo '</select>';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html( get_admin_page_title() ) );
		echo '<form action="options.php" method="post">';
		settings_fields( self::OPTION_GROUP );
		do_settings_sections( self::PAGE_SLUG );
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render the placeholder dashboard until Phase 4 ships the real one.
	 *
	 * @return void
	 */
	public function render_placeholder(): void {
		if ( ! current_user_can( Capabilities::VIEW ) ) {
			return;
		}

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'LEA Studios Site Audit', 'leastudios-siteaudit' ) );
		printf(
			'<p>%s</p>',
			esc_html__( 'The dashboard ships in a later phase. For now, configure your PageSpeed API key under Settings.', 'leastudios-siteaudit' )
		);
		echo '</div>';
	}

	/**
	 * Read a single value from the stored options array, falling back to defaults.
	 *
	 * @param string $key Option key.
	 * @return mixed
	 */
	private function get_option_value( string $key ) {
		$defaults = Activation::default_options();
		$stored   = get_option( self::OPTION_NAME, $defaults );
		$merged   = is_array( $stored ) ? array_merge( $defaults, $stored ) : $defaults;

		return $merged[ $key ] ?? null;
	}
}
