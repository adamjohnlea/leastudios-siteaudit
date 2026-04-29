<?php
/**
 * Admin controller for the dashboard, project-detail, and URL-detail views.
 *
 * @package LEAStudios\SiteAudit\Modules\Dashboard\Admin
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Dashboard\Admin;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Admin\Notice_Service;
use LEAStudios\SiteAudit\Capabilities;
use LEAStudios\SiteAudit\Modules\Audit\Application\Services\Trend_Calculator;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Issue_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;
use LEAStudios\SiteAudit\Modules\Dashboard\Application\Services\Dashboard_Statistics;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Project_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;

/**
 * Owns the top-level "Site Audit" menu and three read-only views:
 *
 *   - `?page=leastudios-siteaudit`                     → dashboard overview
 *   - `?page=leastudios-siteaudit&action=project&id=N` → project detail
 *   - `?page=leastudios-siteaudit&action=url&id=N`     → URL detail
 *
 * No mutations live here; "Run audit now" and CRUD are handled by
 * `Url_Controller` / `Project_Controller`.
 */
final class Dashboard_Controller {

	public const PARENT_SLUG = 'leastudios-siteaudit';

	/**
	 * Project repository.
	 *
	 * @var Project_Repository_Interface
	 */
	private Project_Repository_Interface $project_repository;

	/**
	 * URL repository.
	 *
	 * @var Url_Repository_Interface
	 */
	private Url_Repository_Interface $url_repository;

	/**
	 * Audit repository.
	 *
	 * @var Audit_Repository_Interface
	 */
	private Audit_Repository_Interface $audit_repository;

	/**
	 * Issue repository.
	 *
	 * @var Issue_Repository_Interface
	 */
	private Issue_Repository_Interface $issue_repository;

	/**
	 * Dashboard statistics service.
	 *
	 * @var Dashboard_Statistics
	 */
	private Dashboard_Statistics $statistics;

	/**
	 * Trend calculator.
	 *
	 * @var Trend_Calculator
	 */
	private Trend_Calculator $trend_calculator;

	/**
	 * Constructor.
	 *
	 * @param Project_Repository_Interface $project_repository Project repo.
	 * @param Url_Repository_Interface     $url_repository     URL repo.
	 * @param Audit_Repository_Interface   $audit_repository   Audit repo.
	 * @param Issue_Repository_Interface   $issue_repository   Issue repo.
	 * @param Dashboard_Statistics         $statistics         Aggregator service.
	 * @param Trend_Calculator             $trend_calculator   Trend calculator.
	 */
	public function __construct(
		Project_Repository_Interface $project_repository,
		Url_Repository_Interface $url_repository,
		Audit_Repository_Interface $audit_repository,
		Issue_Repository_Interface $issue_repository,
		Dashboard_Statistics $statistics,
		Trend_Calculator $trend_calculator
	) {
		$this->project_repository = $project_repository;
		$this->url_repository     = $url_repository;
		$this->audit_repository   = $audit_repository;
		$this->issue_repository   = $issue_repository;
		$this->statistics         = $statistics;
		$this->trend_calculator   = $trend_calculator;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register the parent "Site Audit" menu plus its Dashboard landing submenu.
	 *
	 * The submenu shares the parent slug so WordPress treats them as the same
	 * entry — the dashboard is what users land on when clicking the top-level
	 * menu item, and gets a "Dashboard" label in the submenu list.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'LEA Studios Site Audit', 'leastudios-siteaudit' ),
			__( 'Site Audit', 'leastudios-siteaudit' ),
			Capabilities::VIEW,
			self::PARENT_SLUG,
			[ $this, 'render_page' ],
			'dashicons-universal-access-alt',
			80
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Dashboard', 'leastudios-siteaudit' ),
			__( 'Dashboard', 'leastudios-siteaudit' ),
			Capabilities::VIEW,
			self::PARENT_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Enqueue the dashboard stylesheet on plugin pages only.
	 *
	 * @param string $hook Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, self::PARENT_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'leastudios-siteaudit-dashboard',
			LEASTUDIOS_SITEAUDIT_URL . 'assets/css/dashboard.css',
			[],
			defined( 'LEASTUDIOS_SITEAUDIT_VERSION' ) ? (string) LEASTUDIOS_SITEAUDIT_VERSION : '0.0.0'
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

		if ( 'project' === $action ) {
			$this->render_project();
			return;
		}

		if ( 'url' === $action ) {
			$this->render_url();
			return;
		}

		$this->render_overview();
	}

	/**
	 * Render the dashboard overview (project cards + unassigned URLs card).
	 *
	 * @return void
	 */
	private function render_overview(): void {
		$projects      = $this->project_repository->find_all();
		$project_cards = [];

		foreach ( $projects as $project ) {
			$project_id      = $project->id() ?? 0;
			$urls            = $this->url_repository->find_by_project_id( $project_id );
			$audits_by_url   = $this->build_audits_by_url( $urls );
			$summary         = $this->statistics->calculate_summary( $urls, $audits_by_url );
			$project_cards[] = [
				'project' => $project,
				'summary' => $summary,
			];
		}

		$unassigned_urls    = $this->url_repository->find_unassigned();
		$unassigned_audits  = $this->build_audits_by_url( $unassigned_urls );
		$unassigned_summary = $this->statistics->calculate_summary( $unassigned_urls, $unassigned_audits );

		$this->include_template(
			'dashboard/index.php',
			[
				'project_cards'      => $project_cards,
				'unassigned_summary' => $unassigned_summary,
				'has_any_urls'       => count( $unassigned_urls ) > 0 || count( $projects ) > 0,
				'detail_base_url'    => $this->page_base_url(),
			]
		);
	}

	/**
	 * Render the project-detail view (`?action=project&id=N`).
	 *
	 * Passing `id=0` (or omitting it) renders the unassigned-URLs equivalent.
	 *
	 * @return void
	 */
	private function render_project(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only dispatch on a GET tab; mutations have their own nonce checks.
		$id = isset( $_GET['id'] ) ? absint( wp_unslash( (string) $_GET['id'] ) ) : 0;

		$project = $id > 0 ? $this->project_repository->find_by_id( $id ) : null;

		if ( $id > 0 && null === $project ) {
			Notice_Service::enqueue( 'error', __( 'Project not found.', 'leastudios-siteaudit' ) );
			wp_safe_redirect( $this->page_base_url() );
			exit;
		}

		if ( null !== $project ) {
			$urls = $this->url_repository->find_by_project_id( $project->id() ?? 0 );
		} else {
			$urls = $this->url_repository->find_unassigned();
		}

		$audits_by_url = $this->build_audits_by_url( $urls );
		$summary       = $this->statistics->calculate_summary( $urls, $audits_by_url );
		$url_summaries = $this->statistics->generate_url_summaries( $urls, $audits_by_url );

		$this->include_template(
			'dashboard/project.php',
			[
				'project'         => $project,
				'summary'         => $summary,
				'url_summaries'   => $url_summaries,
				'detail_base_url' => $this->page_base_url(),
				'overview_url'    => $this->page_base_url(),
			]
		);
	}

	/**
	 * Render the URL-detail view (`?action=url&id=N`).
	 *
	 * @return void
	 */
	private function render_url(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only dispatch on a GET tab; mutations have their own nonce checks.
		$id = isset( $_GET['id'] ) ? absint( wp_unslash( (string) $_GET['id'] ) ) : 0;

		$url = $id > 0 ? $this->url_repository->find_by_id( $id ) : null;

		if ( null === $url ) {
			Notice_Service::enqueue( 'error', __( 'URL not found.', 'leastudios-siteaudit' ) );
			wp_safe_redirect( $this->page_base_url() );
			exit;
		}

		$project    = null;
		$project_id = $url->project_id();
		if ( null !== $project_id ) {
			$project = $this->project_repository->find_by_id( $project_id );
		}

		$audits        = $this->audit_repository->find_by_url_id( $url->id() ?? 0 );
		$trend         = $this->trend_calculator->calculate_trend( $audits );
		$graph_data    = $this->trend_calculator->generate_graph_data( $audits );
		$average_score = $this->trend_calculator->calculate_average( $audits );
		$latest_score  = [] !== $audits ? $audits[0]->score()->value() : null;

		$latest_desktop = $this->audit_repository->find_latest_completed_by_url_id_and_strategy( $url->id() ?? 0, Run_Strategy::DESKTOP );
		$latest_mobile  = $this->audit_repository->find_latest_completed_by_url_id_and_strategy( $url->id() ?? 0, Run_Strategy::MOBILE );

		$desktop_issues = null !== $latest_desktop ? $this->issue_repository->find_by_audit_id( $latest_desktop->id() ?? 0 ) : [];
		$mobile_issues  = null !== $latest_mobile ? $this->issue_repository->find_by_audit_id( $latest_mobile->id() ?? 0 ) : [];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ?tab is a presentation toggle, not a state mutation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';
		if ( 'mobile' !== $tab ) {
			$tab = 'desktop';
		}

		$this->include_template(
			'dashboard/url.php',
			[
				'url'             => $url,
				'project'         => $project,
				'audits'          => $audits,
				'trend'           => $trend,
				'graph_data'      => $graph_data,
				'average_score'   => $average_score,
				'latest_score'    => $latest_score,
				'desktop_issues'  => $desktop_issues,
				'mobile_issues'   => $mobile_issues,
				'has_desktop'     => null !== $latest_desktop,
				'has_mobile'      => null !== $latest_mobile,
				'active_tab'      => $tab,
				'overview_url'    => $this->page_base_url(),
				'detail_base_url' => $this->page_base_url(),
			]
		);
	}

	/**
	 * Build a `[url_id => Audit[]]` map for a list of URLs.
	 *
	 * @param array<int, Url> $urls URLs to look up.
	 *
	 * @return array<int, array<int, Audit>>
	 */
	private function build_audits_by_url( array $urls ): array {
		$audits_by_url = [];

		foreach ( $urls as $url ) {
			$url_id                   = $url->id() ?? 0;
			$audits_by_url[ $url_id ] = $this->audit_repository->find_by_url_id( $url_id );
		}

		return $audits_by_url;
	}

	/**
	 * Build the canonical dashboard page URL (no action).
	 *
	 * @return string
	 */
	private function page_base_url(): string {
		return add_query_arg( 'page', self::PARENT_SLUG, admin_url( 'admin.php' ) );
	}

	/**
	 * Render a template partial with the given context variables in scope.
	 *
	 * @param string               $relative_path Path under `templates/`.
	 * @param array<string, mixed> $context       Variables to extract.
	 *
	 * @return void
	 */
	private function include_template( string $relative_path, array $context ): void {
		$file = LEASTUDIOS_SITEAUDIT_DIR . 'templates/' . $relative_path;

		if ( ! file_exists( $file ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- partials use bare names; admin-only context.
		extract( $context, EXTR_SKIP );
		include $file;
	}
}
