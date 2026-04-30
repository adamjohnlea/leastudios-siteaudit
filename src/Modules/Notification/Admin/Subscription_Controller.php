<?php
/**
 * Admin handler for the per-project subscribe/unsubscribe toggle.
 *
 * @package LEAStudios\SiteAudit\Modules\Notification\Admin
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Notification\Admin;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Admin\Notice_Service;
use LEAStudios\SiteAudit\Capabilities;
use LEAStudios\SiteAudit\Modules\Dashboard\Admin\Dashboard_Controller;
use LEAStudios\SiteAudit\Modules\Notification\Domain\Repositories\Email_Subscription_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Project_Repository_Interface;

/**
 * Owns one admin-post handler:
 *
 *   `admin-post.php?action=leastudios_siteaudit_toggle_subscription`
 *
 * POSTs only — flips the current user's subscription state for the given
 * project, drops a flash notice, and redirects back to the project detail
 * page. Capability is `view`: anyone who can see the project can subscribe
 * themselves to it.
 */
final class Subscription_Controller {

	public const ACTION_TOGGLE = 'leastudios_siteaudit_toggle_subscription';

	/**
	 * Project repository.
	 *
	 * @var Project_Repository_Interface
	 */
	private Project_Repository_Interface $project_repository;

	/**
	 * Subscription repository.
	 *
	 * @var Email_Subscription_Repository_Interface
	 */
	private Email_Subscription_Repository_Interface $subscription_repository;

	/**
	 * Constructor.
	 *
	 * @param Project_Repository_Interface            $project_repository      Project repo.
	 * @param Email_Subscription_Repository_Interface $subscription_repository Subscription repo.
	 */
	public function __construct(
		Project_Repository_Interface $project_repository,
		Email_Subscription_Repository_Interface $subscription_repository
	) {
		$this->project_repository      = $project_repository;
		$this->subscription_repository = $subscription_repository;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_' . self::ACTION_TOGGLE, [ $this, 'handle_toggle' ] );
	}

	/**
	 * Handle POST: flip the current user's subscription state.
	 *
	 * @return void
	 */
	public function handle_toggle(): void {
		if ( ! current_user_can( Capabilities::VIEW ) ) {
			wp_die(
				esc_html__( 'You do not have permission to manage subscriptions.', 'leastudios-siteaudit' ),
				esc_html__( 'Subscription forbidden', 'leastudios-siteaudit' ),
				[ 'response' => 403 ]
			);
		}

		check_admin_referer( self::ACTION_TOGGLE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above.
		$project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( (string) $_POST['project_id'] ) ) : 0;
		$user_id    = get_current_user_id();

		if ( $project_id <= 0 || 0 === $user_id ) {
			Notice_Service::enqueue( 'error', __( 'Invalid project.', 'leastudios-siteaudit' ) );
			wp_safe_redirect( $this->dashboard_url() );
			exit;
		}

		$project = $this->project_repository->find_by_id( $project_id );
		if ( null === $project ) {
			Notice_Service::enqueue( 'error', __( 'Project not found.', 'leastudios-siteaudit' ) );
			wp_safe_redirect( $this->dashboard_url() );
			exit;
		}

		if ( $this->subscription_repository->is_subscribed( $user_id, $project_id ) ) {
			$this->subscription_repository->unsubscribe( $user_id, $project_id );
			Notice_Service::enqueue( 'success', __( 'Unsubscribed from this project.', 'leastudios-siteaudit' ) );
		} else {
			$this->subscription_repository->subscribe( $user_id, $project_id );
			Notice_Service::enqueue( 'success', __( 'Subscribed to this project.', 'leastudios-siteaudit' ) );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'action' => 'project',
					'id'     => $project_id,
				],
				$this->dashboard_url()
			)
		);
		exit;
	}

	/**
	 * Build the dashboard overview URL.
	 *
	 * @return string
	 */
	private function dashboard_url(): string {
		return add_query_arg( 'page', Dashboard_Controller::PARENT_SLUG, admin_url( 'admin.php' ) );
	}
}
