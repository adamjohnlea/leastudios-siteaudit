<?php
/**
 * GDPR exporter / eraser hooks for email_subscriptions.
 *
 * @package LEAStudios\SiteAudit\Modules\Notification\Application
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Notification\Application;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Database\Schema;

/**
 * Registers WordPress's personal-data exporter and eraser callbacks so a
 * site owner running a Tools → Export Personal Data or Erase Personal Data
 * request will surface and remove this user's email-subscription rows.
 *
 * The `email_subscriptions` table stores `user_id` plus `project_id`, both
 * of which are derived from the WP user identity, so the exporter records
 * "subscribed to project X" and the eraser deletes every subscription row
 * for the matched user.
 */
final class Privacy_Hooks {

	/**
	 * Register WP privacy filters.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
	}

	/**
	 * Append our exporter to WP's exporter registry.
	 *
	 * @param array<string, array{exporter_friendly_name: string, callback: callable}> $exporters Existing exporters.
	 * @return array<string, array{exporter_friendly_name: string, callback: callable}>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['leastudios-siteaudit-subscriptions'] = [
			'exporter_friendly_name' => __( 'LEA Studios Site Audit — Project Subscriptions', 'leastudios-siteaudit' ),
			'callback'               => [ $this, 'export_user_data' ],
		];

		return $exporters;
	}

	/**
	 * Append our eraser to WP's eraser registry.
	 *
	 * @param array<string, array{eraser_friendly_name: string, callback: callable}> $erasers Existing erasers.
	 * @return array<string, array{eraser_friendly_name: string, callback: callable}>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['leastudios-siteaudit-subscriptions'] = [
			'eraser_friendly_name' => __( 'LEA Studios Site Audit — Project Subscriptions', 'leastudios-siteaudit' ),
			'callback'             => [ $this, 'erase_user_data' ],
		];

		return $erasers;
	}

	/**
	 * Export every project-subscription row owned by the given email.
	 *
	 * @param string $email_address The email address being exported.
	 * @return array{data: array<int, array{group_id: string, group_label: string, item_id: string, data: array<int, array{name: string, value: string}>}>, done: bool}
	 */
	public function export_user_data( string $email_address ): array {
		$user = get_user_by( 'email', $email_address );

		if ( false === $user ) {
			return [
				'data' => [],
				'done' => true,
			];
		}

		global $wpdb;
		$subscriptions_table = Schema::table( Schema::TABLE_EMAIL_SUBSCRIPTIONS );
		$projects_table      = Schema::table( Schema::TABLE_PROJECTS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id, s.project_id, s.created_at, p.name AS project_name FROM `{$subscriptions_table}` AS s LEFT JOIN `{$projects_table}` AS p ON p.id = s.project_id WHERE s.user_id = %d",
				$user->ID
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$exported = [];

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$exported[] = [
					'group_id'    => 'leastudios-siteaudit-subscriptions',
					'group_label' => __( 'LEA Studios Site Audit — Project Subscriptions', 'leastudios-siteaudit' ),
					'item_id'     => 'subscription-' . (int) $row['id'],
					'data'        => [
						[
							'name'  => __( 'Project', 'leastudios-siteaudit' ),
							'value' => (string) ( $row['project_name'] ?? sprintf( '#%d', (int) $row['project_id'] ) ),
						],
						[
							'name'  => __( 'Subscribed at (UTC)', 'leastudios-siteaudit' ),
							'value' => (string) ( $row['created_at'] ?? '' ),
						],
					],
				];
			}
		}

		return [
			'data' => $exported,
			'done' => true,
		];
	}

	/**
	 * Erase every subscription row owned by the given email.
	 *
	 * @param string $email_address The email address being erased.
	 * @return array{items_removed: int, items_retained: int, messages: array<int, string>, done: bool}
	 */
	public function erase_user_data( string $email_address ): array {
		$user = get_user_by( 'email', $email_address );

		if ( false === $user ) {
			return [
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => [],
				'done'           => true,
			];
		}

		global $wpdb;
		$subscriptions_table = Schema::table( Schema::TABLE_EMAIL_SUBSCRIPTIONS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$removed = $wpdb->delete(
			$subscriptions_table,
			[ 'user_id' => $user->ID ],
			[ '%d' ]
		);

		return [
			'items_removed'  => is_int( $removed ) ? $removed : 0,
			'items_retained' => 0,
			'messages'       => [],
			'done'           => true,
		];
	}
}
