<?php
/**
 * Admin controller for Project CRUD.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Admin
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Admin;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Admin\Notice_Service;
use LEAStudios\SiteAudit\Capabilities;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Project_Service;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;
use LEAStudios\SiteAudit\Shared\Template_Renderer;

/**
 * Renders the Projects submenu and handles `admin-post.php` mutations.
 *
 * GET requests dispatch on `?action=` to either the list or the create/edit
 * form partial. POST requests come through three `admin_post_*` listeners
 * that verify the nonce + capability, delegate to `Project_Service`, and
 * `wp_safe_redirect()` back with a flash notice.
 */
final class Project_Controller {

	public const PAGE_SLUG   = 'leastudios-siteaudit-projects';
	public const PARENT_SLUG = 'leastudios-siteaudit';

	private const ACTION_CREATE = 'leastudios_siteaudit_create_project';
	private const ACTION_UPDATE = 'leastudios_siteaudit_update_project';
	private const ACTION_DELETE = 'leastudios_siteaudit_delete_project';

	/**
	 * Project application service.
	 *
	 * @var Project_Service
	 */
	private Project_Service $project_service;

	/**
	 * Template renderer.
	 *
	 * @var Template_Renderer
	 */
	private Template_Renderer $template_renderer;

	/**
	 * Constructor.
	 *
	 * @param Project_Service   $project_service   Application service.
	 * @param Template_Renderer $template_renderer Renders admin partials.
	 */
	public function __construct( Project_Service $project_service, Template_Renderer $template_renderer ) {
		$this->project_service   = $project_service;
		$this->template_renderer = $template_renderer;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ], 11 );
		add_action( 'admin_post_' . self::ACTION_CREATE, [ $this, 'handle_create' ] );
		add_action( 'admin_post_' . self::ACTION_UPDATE, [ $this, 'handle_update' ] );
		add_action( 'admin_post_' . self::ACTION_DELETE, [ $this, 'handle_delete' ] );
	}

	/**
	 * Register the Projects submenu page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Projects', 'leastudios-siteaudit' ),
			__( 'Projects', 'leastudios-siteaudit' ),
			Capabilities::VIEW,
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Dispatch GET renders based on the `action` query arg.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( Capabilities::VIEW ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'leastudios-siteaudit' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only dispatch on a GET tab; mutations have their own nonce checks.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';

		if ( 'create' === $action || 'edit' === $action ) {
			if ( ! current_user_can( Capabilities::MANAGE ) ) {
				wp_die( esc_html__( 'You do not have permission to manage projects.', 'leastudios-siteaudit' ) );
			}

			$project = null;
			if ( 'edit' === $action ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only edit screen; mutations have their own nonce.
				$id      = isset( $_GET['id'] ) ? absint( wp_unslash( (string) $_GET['id'] ) ) : 0;
				$project = $id > 0 ? $this->project_service->find_by_id( $id ) : null;

				if ( null === $project ) {
					wp_safe_redirect( $this->list_url() );
					exit;
				}
			}

			$this->template_renderer->render(
				'projects/form.php',
				[
					'leastudios_siteaudit_project'       => $project,
					'leastudios_siteaudit_create_url'    => admin_url( 'admin-post.php' ),
					'leastudios_siteaudit_list_url'      => $this->list_url(),
					'leastudios_siteaudit_create_action' => self::ACTION_CREATE,
					'leastudios_siteaudit_update_action' => self::ACTION_UPDATE,
				]
			);
			return;
		}

		$projects = $this->project_service->find_all();
		$this->template_renderer->render(
			'projects/index.php',
			[
				'leastudios_siteaudit_projects'      => $projects,
				'leastudios_siteaudit_list_url'      => $this->list_url(),
				'leastudios_siteaudit_create_url'    => add_query_arg( 'action', 'create', $this->list_url() ),
				'leastudios_siteaudit_edit_base_url' => $this->list_url(),
				'leastudios_siteaudit_delete_url'    => admin_url( 'admin-post.php' ),
				'leastudios_siteaudit_delete_action' => self::ACTION_DELETE,
			]
		);
	}

	/**
	 * Handle POST: create a project.
	 *
	 * @return void
	 */
	public function handle_create(): void {
		$this->guard_post( self::ACTION_CREATE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['description'] ) ) : '';

		try {
			$this->project_service->create( $name, '' !== $description ? $description : null );
			Notice_Service::enqueue( 'success', __( 'Project created.', 'leastudios-siteaudit' ) );
			wp_safe_redirect( $this->list_url() );
			exit;
		} catch ( Validation_Exception $e ) {
			Notice_Service::enqueue( 'error', $e->getMessage() );
			wp_safe_redirect( add_query_arg( 'action', 'create', $this->list_url() ) );
			exit;
		}
	}

	/**
	 * Handle POST: update a project.
	 *
	 * @return void
	 */
	public function handle_update(): void {
		$this->guard_post( self::ACTION_UPDATE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$id = isset( $_POST['id'] ) ? absint( wp_unslash( (string) $_POST['id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['description'] ) ) : '';

		if ( $id <= 0 ) {
			Notice_Service::enqueue( 'error', __( 'Invalid project id.', 'leastudios-siteaudit' ) );
			wp_safe_redirect( $this->list_url() );
			exit;
		}

		try {
			$this->project_service->update( $id, $name, $description );
			Notice_Service::enqueue( 'success', __( 'Project updated.', 'leastudios-siteaudit' ) );
			wp_safe_redirect( $this->list_url() );
			exit;
		} catch ( Validation_Exception $e ) {
			Notice_Service::enqueue( 'error', $e->getMessage() );
			wp_safe_redirect(
				add_query_arg(
					[
						'action' => 'edit',
						'id'     => $id,
					],
					$this->list_url()
				)
			);
			exit;
		}
	}

	/**
	 * Handle POST: delete a project.
	 *
	 * @return void
	 */
	public function handle_delete(): void {
		$this->guard_post( self::ACTION_DELETE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$id = isset( $_POST['id'] ) ? absint( wp_unslash( (string) $_POST['id'] ) ) : 0;

		if ( $id <= 0 ) {
			Notice_Service::enqueue( 'error', __( 'Invalid project id.', 'leastudios-siteaudit' ) );
			wp_safe_redirect( $this->list_url() );
			exit;
		}

		try {
			$this->project_service->delete( $id );
			Notice_Service::enqueue( 'success', __( 'Project deleted.', 'leastudios-siteaudit' ) );
		} catch ( Validation_Exception $e ) {
			Notice_Service::enqueue( 'error', $e->getMessage() );
		}

		wp_safe_redirect( $this->list_url() );
		exit;
	}

	/**
	 * Verify the nonce for the given action and the current user's MANAGE capability.
	 *
	 * @param string $action Action name (must match nonce action and admin_post action).
	 *
	 * @return void
	 */
	private function guard_post( string $action ): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage projects.', 'leastudios-siteaudit' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Build the canonical Projects list URL.
	 *
	 * @return string
	 */
	private function list_url(): string {
		return add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) );
	}
}
