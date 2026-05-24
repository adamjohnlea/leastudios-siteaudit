<?php
/**
 * Admin controller for URL CRUD plus bulk import.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Admin
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Admin;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Admin\Notice_Service;
use LEAStudios\SiteAudit\Capabilities;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Action_Enqueuer_Interface;
use LEAStudios\SiteAudit\Modules\Scheduler\Application\Services\Tick_Dispatcher;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Bulk_Import_Service;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Project_Service;
use LEAStudios\SiteAudit\Modules\Url\Application\Services\Url_Service;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Bulk_Import_Result;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;
use LEAStudios\SiteAudit\Shared\Template_Renderer;

/**
 * Renders the URLs submenu and handles `admin-post.php` mutations.
 *
 * GET requests dispatch on `?action=` between list / create / edit /
 * bulk-import / bulk-import-result. POST requests are routed through
 * `admin_post_*` listeners that delegate to `Url_Service` or
 * `Bulk_Import_Service`.
 */
final class Url_Controller {

	public const PAGE_SLUG   = 'leastudios-siteaudit-urls';
	public const PARENT_SLUG = 'leastudios-siteaudit';

	private const ACTION_CREATE      = 'leastudios_siteaudit_create_url';
	private const ACTION_UPDATE      = 'leastudios_siteaudit_update_url';
	private const ACTION_DELETE      = 'leastudios_siteaudit_delete_url';
	private const ACTION_BULK_IMPORT = 'leastudios_siteaudit_bulk_import_urls';
	private const ACTION_RUN_AUDIT   = 'leastudios_siteaudit_run_audit';

	private const PER_PAGE                = 20;
	private const BULK_RESULT_TTL_SECONDS = 300;
	/** Cap on uploaded CSV size — bulk URL imports in practice fit in tens of KB. */
	private const CSV_MAX_BYTES = 5 * 1024 * 1024;

	/**
	 * URL application service.
	 *
	 * @var Url_Service
	 */
	private Url_Service $url_service;

	/**
	 * Project application service.
	 *
	 * @var Project_Service
	 */
	private Project_Service $project_service;

	/**
	 * Bulk import service.
	 *
	 * @var Bulk_Import_Service
	 */
	private Bulk_Import_Service $bulk_import_service;

	/**
	 * Async action enqueuer for the "Run audit now" button.
	 *
	 * @var Action_Enqueuer_Interface
	 */
	private Action_Enqueuer_Interface $enqueuer;

	/**
	 * Audit repository (read-only use here, for the URL list score column).
	 *
	 * @var Audit_Repository_Interface
	 */
	private Audit_Repository_Interface $audit_repository;

	/**
	 * Template renderer.
	 *
	 * @var Template_Renderer
	 */
	private Template_Renderer $template_renderer;

	/**
	 * Constructor.
	 *
	 * @param Url_Service                $url_service         URL application service.
	 * @param Project_Service            $project_service     Project application service.
	 * @param Bulk_Import_Service        $bulk_import_service Bulk import service.
	 * @param Action_Enqueuer_Interface  $enqueuer            Async action enqueuer (for run-audit dispatch).
	 * @param Audit_Repository_Interface $audit_repository    Audit repository (for list score lookup).
	 * @param Template_Renderer          $template_renderer   Renders admin partials.
	 */
	public function __construct(
		Url_Service $url_service,
		Project_Service $project_service,
		Bulk_Import_Service $bulk_import_service,
		Action_Enqueuer_Interface $enqueuer,
		Audit_Repository_Interface $audit_repository,
		Template_Renderer $template_renderer
	) {
		$this->url_service         = $url_service;
		$this->project_service     = $project_service;
		$this->bulk_import_service = $bulk_import_service;
		$this->enqueuer            = $enqueuer;
		$this->audit_repository    = $audit_repository;
		$this->template_renderer   = $template_renderer;
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
		add_action( 'admin_post_' . self::ACTION_BULK_IMPORT, [ $this, 'handle_bulk_import' ] );
		add_action( 'admin_post_' . self::ACTION_RUN_AUDIT, [ $this, 'handle_run_audit' ] );
	}

	/**
	 * Register the URLs submenu page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'URLs', 'leastudios-siteaudit' ),
			__( 'URLs', 'leastudios-siteaudit' ),
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
			$this->render_form( $action );
			return;
		}

		if ( 'bulk-import' === $action ) {
			$this->render_bulk_import_form();
			return;
		}

		if ( 'bulk-import-result' === $action ) {
			$this->render_bulk_import_result();
			return;
		}

		$this->render_list();
	}

	/**
	 * Render the URL list with search + pagination.
	 *
	 * @return void
	 */
	private function render_list(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- search GET form; no state mutation.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pagination GET arg; no state mutation.
		$page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( (string) $_GET['paged'] ) ) ) : 1;

		$urls         = $this->url_service->find_paginated( $page, self::PER_PAGE, $search );
		$total        = $this->url_service->count_for_search( $search );
		$total_pages  = (int) ceil( $total / self::PER_PAGE );
		$projects     = $this->project_service->find_all();
		$projects_map = $this->index_projects( $projects );

		$url_ids = [];
		foreach ( $urls as $url_for_id ) {
			$id = $url_for_id->id();
			if ( null !== $id ) {
				$url_ids[] = $id;
			}
		}
		$latest_scores = [] === $url_ids
			? []
			: $this->audit_repository->find_latest_scores_by_url_ids( $url_ids );

		$this->template_renderer->render(
			'urls/index.php',
			[
				'leastudios_siteaudit_urls'             => $urls,
				'leastudios_siteaudit_projects_by_id'   => $projects_map,
				'leastudios_siteaudit_latest_scores'    => $latest_scores,
				'leastudios_siteaudit_total'            => $total,
				'leastudios_siteaudit_page'             => $page,
				'leastudios_siteaudit_total_pages'      => $total_pages,
				'leastudios_siteaudit_per_page'         => self::PER_PAGE,
				'leastudios_siteaudit_search'           => $search,
				'leastudios_siteaudit_list_url'         => $this->list_url(),
				'leastudios_siteaudit_create_url'       => add_query_arg( 'action', 'create', $this->list_url() ),
				'leastudios_siteaudit_bulk_import_url'  => add_query_arg( 'action', 'bulk-import', $this->list_url() ),
				'leastudios_siteaudit_edit_base_url'    => $this->list_url(),
				'leastudios_siteaudit_delete_url'       => admin_url( 'admin-post.php' ),
				'leastudios_siteaudit_delete_action'    => self::ACTION_DELETE,
				'leastudios_siteaudit_run_audit_url'    => admin_url( 'admin-post.php' ),
				'leastudios_siteaudit_run_audit_action' => self::ACTION_RUN_AUDIT,
			]
		);
	}

	/**
	 * Render the create / edit form.
	 *
	 * @param string $action Either 'create' or 'edit'.
	 *
	 * @return void
	 */
	private function render_form( string $action ): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage URLs.', 'leastudios-siteaudit' ) );
		}

		$url = null;
		if ( 'edit' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only edit screen; mutations have their own nonce.
			$id  = isset( $_GET['id'] ) ? absint( wp_unslash( (string) $_GET['id'] ) ) : 0;
			$url = $id > 0 ? $this->url_service->find_by_id( $id ) : null;

			if ( null === $url ) {
				wp_safe_redirect( $this->list_url() );
				exit;
			}
		}

		$this->template_renderer->render(
			'urls/form.php',
			[
				'leastudios_siteaudit_url_model'        => $url,
				'leastudios_siteaudit_projects'         => $this->project_service->find_all(),
				'leastudios_siteaudit_frequencies'      => Audit_Frequency::cases(),
				'leastudios_siteaudit_strategies'       => Audit_Strategy::cases(),
				'leastudios_siteaudit_post_url'         => admin_url( 'admin-post.php' ),
				'leastudios_siteaudit_list_url'         => $this->list_url(),
				'leastudios_siteaudit_create_action'    => self::ACTION_CREATE,
				'leastudios_siteaudit_update_action'    => self::ACTION_UPDATE,
				'leastudios_siteaudit_run_audit_action' => self::ACTION_RUN_AUDIT,
			]
		);
	}

	/**
	 * Render the bulk-import form.
	 *
	 * @return void
	 */
	private function render_bulk_import_form(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage URLs.', 'leastudios-siteaudit' ) );
		}

		$this->template_renderer->render(
			'urls/bulk-import.php',
			[
				'leastudios_siteaudit_projects'    => $this->project_service->find_all(),
				'leastudios_siteaudit_frequencies' => Audit_Frequency::cases(),
				'leastudios_siteaudit_post_url'    => admin_url( 'admin-post.php' ),
				'leastudios_siteaudit_list_url'    => $this->list_url(),
				'leastudios_siteaudit_action_name' => self::ACTION_BULK_IMPORT,
			]
		);
	}

	/**
	 * Render the bulk-import result page (reads + clears the result transient).
	 *
	 * @return void
	 */
	private function render_bulk_import_result(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token is single-use and looked up server-side.
		$token = isset( $_GET['token'] ) ? sanitize_key( wp_unslash( (string) $_GET['token'] ) ) : '';
		if ( '' === $token ) {
			wp_safe_redirect( $this->list_url() );
			exit;
		}

		$key = $this->bulk_result_transient_key( $token );
		// Delete-then-validate: claim the token immediately so two concurrent
		// reads from the same browser tab cannot both succeed. Whichever
		// request claims it gets the data; the other sees nothing and
		// redirects to the list view.
		$cached = get_transient( $key );
		delete_transient( $key );

		$result = $this->bulk_result_from_cache( $cached );

		if ( null === $result ) {
			wp_safe_redirect( $this->list_url() );
			exit;
		}

		$this->template_renderer->render(
			'urls/bulk-import-result.php',
			[
				'leastudios_siteaudit_result'          => $result,
				'leastudios_siteaudit_list_url'        => $this->list_url(),
				'leastudios_siteaudit_bulk_import_url' => add_query_arg( 'action', 'bulk-import', $this->list_url() ),
			]
		);
	}

	/**
	 * Reconstruct a Bulk_Import_Result from its cached primitive-array form.
	 *
	 * Storing the value object directly in a transient serializes a class
	 * with version-coupled identity, so a future class rename or upgrade
	 * could leave already-issued tokens unreadable. We persist as a plain
	 * array and rebuild the VO at read time.
	 *
	 * @param mixed $cached The transient payload.
	 * @return Bulk_Import_Result|null
	 */
	private function bulk_result_from_cache( mixed $cached ): ?Bulk_Import_Result {
		if ( ! is_array( $cached ) ) {
			return null;
		}

		if ( ! isset( $cached['imported_count'], $cached['skipped_count'], $cached['errors'] ) || ! is_array( $cached['errors'] ) ) {
			return null;
		}

		return new Bulk_Import_Result(
			(int) $cached['imported_count'],
			(int) $cached['skipped_count'],
			$cached['errors']
		);
	}

	/**
	 * Handle POST: create a URL.
	 *
	 * @return void
	 */
	public function handle_create(): void {
		$this->guard_post( self::ACTION_CREATE );

		$input = $this->read_url_form_input();

		try {
			$this->url_service->create(
				$input['url'],
				$input['name'],
				$input['frequency'],
				$input['project_id'],
				$input['strategy'],
				$input['alerts_enabled'],
				$input['alert_threshold_score'],
				$input['alert_threshold_drop']
			);
			Notice_Service::enqueue( 'success', __( 'URL added.', 'leastudios-siteaudit' ) );
			wp_safe_redirect( $this->list_url() );
			exit;
		} catch ( Validation_Exception $e ) {
			Notice_Service::enqueue( 'error', $e->getMessage() );
			wp_safe_redirect( add_query_arg( 'action', 'create', $this->list_url() ) );
			exit;
		}
	}

	/**
	 * Handle POST: update a URL.
	 *
	 * @return void
	 */
	public function handle_update(): void {
		$this->guard_post( self::ACTION_UPDATE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$id = isset( $_POST['id'] ) ? absint( wp_unslash( (string) $_POST['id'] ) ) : 0;

		if ( $id <= 0 ) {
			Notice_Service::enqueue( 'error', __( 'Invalid URL id.', 'leastudios-siteaudit' ) );
			wp_safe_redirect( $this->list_url() );
			exit;
		}

		$input = $this->read_url_form_input();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$enabled = isset( $_POST['enabled'] ) ? (bool) absint( wp_unslash( (string) $_POST['enabled'] ) ) : false;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$score_raw = isset( $_POST['alert_threshold_score'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['alert_threshold_score'] ) ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$drop_raw = isset( $_POST['alert_threshold_drop'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['alert_threshold_drop'] ) ) ) : '';

		$clear_score = ( '' === $score_raw );
		$clear_drop  = ( '' === $drop_raw );

		try {
			$this->url_service->update(
				$id,
				$input['name'],
				$input['frequency'],
				$input['strategy'],
				$enabled,
				$input['project_id'],
				$input['alerts_enabled'],
				$clear_score ? null : $input['alert_threshold_score'],
				$clear_score,
				$clear_drop ? null : $input['alert_threshold_drop'],
				$clear_drop
			);
			Notice_Service::enqueue( 'success', __( 'URL updated.', 'leastudios-siteaudit' ) );
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
	 * Handle POST: delete a URL.
	 *
	 * @return void
	 */
	public function handle_delete(): void {
		$this->guard_post( self::ACTION_DELETE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$id = isset( $_POST['id'] ) ? absint( wp_unslash( (string) $_POST['id'] ) ) : 0;

		if ( $id <= 0 ) {
			Notice_Service::enqueue( 'error', __( 'Invalid URL id.', 'leastudios-siteaudit' ) );
			wp_safe_redirect( $this->list_url() );
			exit;
		}

		try {
			$this->url_service->delete( $id );
			Notice_Service::enqueue( 'success', __( 'URL deleted.', 'leastudios-siteaudit' ) );
		} catch ( Validation_Exception $e ) {
			Notice_Service::enqueue( 'error', $e->getMessage() );
		}

		wp_safe_redirect( $this->list_url() );
		exit;
	}

	/**
	 * Handle POST: bulk-import URLs from paste-list or CSV upload.
	 *
	 * @return void
	 */
	public function handle_bulk_import(): void {
		$this->guard_post( self::ACTION_BULK_IMPORT );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$import_type = isset( $_POST['import_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['import_type'] ) ) : 'paste';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$frequency = isset( $_POST['frequency'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['frequency'] ) ) : 'weekly';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$project_id_raw = isset( $_POST['project_id'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['project_id'] ) ) ) : '';
		$project_id     = ( '' === $project_id_raw ) ? null : absint( $project_id_raw );
		if ( 0 === $project_id ) {
			$project_id = null;
		}

		$result = null;

		if ( 'csv' === $import_type ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
			if ( empty( $_FILES['csv_file'] ) || ! is_array( $_FILES['csv_file'] ) || empty( $_FILES['csv_file']['tmp_name'] ) ) {
				Notice_Service::enqueue( 'error', __( 'No CSV file was uploaded.', 'leastudios-siteaudit' ) );
				wp_safe_redirect( add_query_arg( 'action', 'bulk-import', $this->list_url() ) );
				exit;
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- file path validated as a real upload below; nonce verified in guard_post().
			$tmp_name = (string) $_FILES['csv_file']['tmp_name'];
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES['size'] is integer-cast immediately; nonce verified in guard_post().
			$file_size = (int) ( $_FILES['csv_file']['size'] ?? 0 );

			// Reject anything that isn't a genuine multipart upload — guards
			// against an attacker hijacking the field to point WP_Filesystem
			// at an arbitrary on-disk path (e.g. wp-config.php).
			if ( ! is_uploaded_file( $tmp_name ) ) {
				Notice_Service::enqueue( 'error', __( 'The uploaded file was not received correctly. Please try again.', 'leastudios-siteaudit' ) );
				wp_safe_redirect( add_query_arg( 'action', 'bulk-import', $this->list_url() ) );
				exit;
			}

			// Cap import size at 5 MB. Bulk imports of accessibility URLs
			// in practice fit in tens of KB; refusing larger files prevents
			// memory blow-ups when get_contents() loads the whole file.
			if ( $file_size > self::CSV_MAX_BYTES ) {
				Notice_Service::enqueue(
					'error',
					sprintf(
						/* translators: %s: maximum size in megabytes. */
						__( 'CSV file too large. Maximum size is %s MB.', 'leastudios-siteaudit' ),
						(string) ( self::CSV_MAX_BYTES / ( 1024 * 1024 ) )
					)
				);
				wp_safe_redirect( add_query_arg( 'action', 'bulk-import', $this->list_url() ) );
				exit;
			}

			$contents = $this->read_uploaded_file( $tmp_name );

			if ( null === $contents ) {
				Notice_Service::enqueue( 'error', __( 'Could not read the uploaded CSV file.', 'leastudios-siteaudit' ) );
				wp_safe_redirect( add_query_arg( 'action', 'bulk-import', $this->list_url() ) );
				exit;
			}

			$result = $this->bulk_import_service->import_from_csv( $contents, $frequency, $project_id );
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
			$paste = isset( $_POST['urls'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['urls'] ) ) : '';

			if ( '' === trim( $paste ) ) {
				Notice_Service::enqueue( 'error', __( 'Paste at least one URL to import.', 'leastudios-siteaudit' ) );
				wp_safe_redirect( add_query_arg( 'action', 'bulk-import', $this->list_url() ) );
				exit;
			}

			$result = $this->bulk_import_service->import_from_list( $paste, $frequency, $project_id );
		}

		$token = wp_generate_password( 16, false );
		set_transient(
			$this->bulk_result_transient_key( $token ),
			[
				'imported_count' => $result->imported_count,
				'skipped_count'  => $result->skipped_count,
				'errors'         => $result->errors,
			],
			self::BULK_RESULT_TTL_SECONDS
		);

		wp_safe_redirect(
			add_query_arg(
				[
					'action' => 'bulk-import-result',
					'token'  => $token,
				],
				$this->list_url()
			)
		);
		exit;
	}

	/**
	 * Handle POST: queue an asynchronous audit for one URL.
	 *
	 * Enqueues a `leastudios_siteaudit_run_audit` Action Scheduler action and
	 * returns immediately. The actual PageSpeed call happens in a separate
	 * worker request, so the user's click never blocks waiting for the API.
	 * The de-duplication guard prevents stacking duplicate actions when the
	 * button is double-clicked or a previous tick already queued the URL.
	 *
	 * @return void
	 */
	public function handle_run_audit(): void {
		$this->guard_post( self::ACTION_RUN_AUDIT );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guarded above by guard_post().
		$id = isset( $_POST['id'] ) ? absint( wp_unslash( (string) $_POST['id'] ) ) : 0;

		if ( $id <= 0 ) {
			Notice_Service::enqueue( 'error', __( 'Invalid URL id.', 'leastudios-siteaudit' ) );
			wp_safe_redirect( $this->list_url() );
			exit;
		}

		if ( $this->enqueuer->has_pending( Tick_Dispatcher::RUN_AUDIT_HOOK, [ $id ] ) ) {
			Notice_Service::enqueue(
				'success',
				__( 'Audit already queued — refresh in a moment to see results.', 'leastudios-siteaudit' )
			);
		} else {
			$this->enqueuer->enqueue_async( Tick_Dispatcher::RUN_AUDIT_HOOK, [ $id ] );
			Notice_Service::enqueue(
				'success',
				__( 'Audit queued — refresh in a moment to see results.', 'leastudios-siteaudit' )
			);
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
			wp_die( esc_html__( 'You do not have permission to manage URLs.', 'leastudios-siteaudit' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Read and normalize the shared URL form fields.
	 *
	 * @return array{url: string, name: string, frequency: string, strategy: string, project_id: int|null, alerts_enabled: bool, alert_threshold_score: int|null, alert_threshold_drop: int|null}
	 */
	private function read_url_form_input(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run guard_post() first.
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['url'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run guard_post() first.
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run guard_post() first.
		$frequency = isset( $_POST['frequency'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['frequency'] ) ) : 'weekly';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run guard_post() first.
		$strategy = isset( $_POST['strategy'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['strategy'] ) ) : 'both';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run guard_post() first.
		$project_id_raw = isset( $_POST['project_id'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['project_id'] ) ) ) : '';
		$project_id     = ( '' === $project_id_raw ) ? null : absint( $project_id_raw );
		if ( 0 === $project_id ) {
			$project_id = null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run guard_post() first.
		$alerts_enabled = isset( $_POST['alerts_enabled'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['alerts_enabled'] ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run guard_post() first.
		$score_raw = isset( $_POST['alert_threshold_score'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['alert_threshold_score'] ) ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run guard_post() first.
		$drop_raw = isset( $_POST['alert_threshold_drop'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['alert_threshold_drop'] ) ) ) : '';

		return [
			'url'                   => $url,
			'name'                  => $name,
			'frequency'             => $frequency,
			'strategy'              => $strategy,
			'project_id'            => $project_id,
			'alerts_enabled'        => $alerts_enabled,
			'alert_threshold_score' => ( '' === $score_raw ) ? null : (int) $score_raw,
			'alert_threshold_drop'  => ( '' === $drop_raw ) ? null : (int) $drop_raw,
		];
	}

	/**
	 * Read an uploaded file's contents via the WP_Filesystem API.
	 *
	 * @param string $path Absolute path on disk.
	 *
	 * @return string|null Contents, or null on failure.
	 */
	private function read_uploaded_file( string $path ): ?string {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		if ( ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
			return null;
		}

		$contents = $wp_filesystem->get_contents( $path );

		return false === $contents ? null : (string) $contents;
	}

	/**
	 * Index projects by id for fast lookup in the list view.
	 *
	 * @param array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project> $projects Projects.
	 *
	 * @return array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project>
	 */
	private function index_projects( array $projects ): array {
		$indexed = [];
		foreach ( $projects as $project ) {
			$id = $project->id();
			if ( null !== $id ) {
				$indexed[ $id ] = $project;
			}
		}
		return $indexed;
	}

	/**
	 * Build the canonical URLs list URL.
	 *
	 * @return string
	 */
	private function list_url(): string {
		return add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) );
	}

	/**
	 * Build the transient key for a bulk-import result.
	 *
	 * @param string $token Random token.
	 *
	 * @return string
	 */
	private function bulk_result_transient_key( string $token ): string {
		return 'leastudios_siteaudit_bulk_import_' . $token;
	}
}
