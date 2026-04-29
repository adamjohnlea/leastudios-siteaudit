<?php
/**
 * Admin controller exposing CSV and PDF download endpoints.
 *
 * @package LEAStudios\SiteAudit\Modules\Reporting\Admin
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Reporting\Admin;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Capabilities;
use LEAStudios\SiteAudit\Modules\Audit\Domain\Repositories\Audit_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Dashboard\Application\Services\Dashboard_Statistics;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Csv_Export_Service;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Pdf_Report_Data_Collector;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Pdf_Report_Service;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Project_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;

/**
 * Owns three `admin_post_*` endpoints, all GET-based since downloads are
 * read-only. Each handler verifies the nonce, checks the `view` capability,
 * validates input, sets `Content-Type` + `Content-Disposition: attachment`
 * headers, streams the body, and exits.
 *
 *   - `admin-post.php?action=leastudios_siteaudit_export_audits&url_id=N&_wpnonce=...`
 *     → CSV of one URL's audit history
 *   - `admin-post.php?action=leastudios_siteaudit_export_summary&project_id=N&_wpnonce=...`
 *     → CSV of one project's URL summaries
 *   - `admin-post.php?action=leastudios_siteaudit_export_pdf&project_id=N&_wpnonce=...`
 *     → PDF of one project's full report
 */
final class Reporting_Controller {

	public const ACTION_EXPORT_AUDITS  = 'leastudios_siteaudit_export_audits';
	public const ACTION_EXPORT_SUMMARY = 'leastudios_siteaudit_export_summary';
	public const ACTION_EXPORT_PDF     = 'leastudios_siteaudit_export_pdf';

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
	 * Dashboard statistics service (used by the URL summary CSV).
	 *
	 * @var Dashboard_Statistics
	 */
	private Dashboard_Statistics $statistics;

	/**
	 * CSV export service.
	 *
	 * @var Csv_Export_Service
	 */
	private Csv_Export_Service $csv_export_service;

	/**
	 * PDF report data collector.
	 *
	 * @var Pdf_Report_Data_Collector
	 */
	private Pdf_Report_Data_Collector $pdf_data_collector;

	/**
	 * PDF report service.
	 *
	 * @var Pdf_Report_Service
	 */
	private Pdf_Report_Service $pdf_report_service;

	/**
	 * Constructor.
	 *
	 * @param Project_Repository_Interface $project_repository  Project repo.
	 * @param Url_Repository_Interface     $url_repository      URL repo.
	 * @param Audit_Repository_Interface   $audit_repository    Audit repo.
	 * @param Dashboard_Statistics         $statistics          Stats service.
	 * @param Csv_Export_Service           $csv_export_service  CSV service.
	 * @param Pdf_Report_Data_Collector    $pdf_data_collector  PDF data collector.
	 * @param Pdf_Report_Service           $pdf_report_service  PDF rendering service.
	 */
	public function __construct(
		Project_Repository_Interface $project_repository,
		Url_Repository_Interface $url_repository,
		Audit_Repository_Interface $audit_repository,
		Dashboard_Statistics $statistics,
		Csv_Export_Service $csv_export_service,
		Pdf_Report_Data_Collector $pdf_data_collector,
		Pdf_Report_Service $pdf_report_service
	) {
		$this->project_repository = $project_repository;
		$this->url_repository     = $url_repository;
		$this->audit_repository   = $audit_repository;
		$this->statistics         = $statistics;
		$this->csv_export_service = $csv_export_service;
		$this->pdf_data_collector = $pdf_data_collector;
		$this->pdf_report_service = $pdf_report_service;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_' . self::ACTION_EXPORT_AUDITS, [ $this, 'handle_export_audits' ] );
		add_action( 'admin_post_' . self::ACTION_EXPORT_SUMMARY, [ $this, 'handle_export_summary' ] );
		add_action( 'admin_post_' . self::ACTION_EXPORT_PDF, [ $this, 'handle_export_pdf' ] );
	}

	/**
	 * Handle GET: stream a CSV of one URL's audit history.
	 *
	 * @return void
	 */
	public function handle_export_audits(): void {
		$this->guard( self::ACTION_EXPORT_AUDITS );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- guarded above.
		$url_id = isset( $_GET['url_id'] ) ? absint( wp_unslash( (string) $_GET['url_id'] ) ) : 0;
		$url    = $url_id > 0 ? $this->url_repository->find_by_id( $url_id ) : null;

		if ( null === $url ) {
			wp_die(
				esc_html__( 'URL not found.', 'leastudios-siteaudit' ),
				esc_html__( 'Export failed', 'leastudios-siteaudit' ),
				[ 'response' => 404 ]
			);
		}

		$audits   = $this->audit_repository->find_by_url_id( (int) $url->id() );
		$body     = $this->csv_export_service->export_audits( $audits );
		$filename = $this->csv_filename( 'audit-history-' . ( $url->name() ?? $url->url()->value() ) );

		$this->stream_csv( $body, $filename );
	}

	/**
	 * Handle GET: stream a CSV of one project's URL summaries.
	 *
	 * @return void
	 */
	public function handle_export_summary(): void {
		$this->guard( self::ACTION_EXPORT_SUMMARY );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- guarded above.
		$project_id = isset( $_GET['project_id'] ) ? absint( wp_unslash( (string) $_GET['project_id'] ) ) : 0;

		if ( $project_id > 0 ) {
			$project = $this->project_repository->find_by_id( $project_id );
			if ( null === $project ) {
				wp_die(
					esc_html__( 'Project not found.', 'leastudios-siteaudit' ),
					esc_html__( 'Export failed', 'leastudios-siteaudit' ),
					[ 'response' => 404 ]
				);
			}
			$urls            = $this->url_repository->find_by_project_id( (int) $project->id() );
			$filename_source = $project->name()->value();
		} else {
			// project_id=0 → unassigned URLs, mirroring the dashboard convention.
			$urls            = $this->url_repository->find_unassigned();
			$filename_source = 'unassigned-urls';
		}

		$audits_by_url = [];
		foreach ( $urls as $url ) {
			$id                        = $url->id();
			$audits_by_url[ $id ?? 0 ] = null === $id ? [] : $this->audit_repository->find_by_url_id( $id );
		}

		$url_summaries = $this->statistics->generate_url_summaries( $urls, $audits_by_url );
		$body          = $this->csv_export_service->export_url_summaries( $url_summaries );
		$filename      = $this->csv_filename( 'project-' . $filename_source );

		$this->stream_csv( $body, $filename );
	}

	/**
	 * Handle GET: stream a PDF of one project's full report.
	 *
	 * @return void
	 */
	public function handle_export_pdf(): void {
		$this->guard( self::ACTION_EXPORT_PDF );

		// PDF rendering can be memory-hungry for large projects; bump to admin tier.
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- guarded above.
		$project_id = isset( $_GET['project_id'] ) ? absint( wp_unslash( (string) $_GET['project_id'] ) ) : 0;
		$project    = $project_id > 0 ? $this->project_repository->find_by_id( $project_id ) : null;

		if ( null === $project ) {
			wp_die(
				esc_html__( 'Project not found.', 'leastudios-siteaudit' ),
				esc_html__( 'Export failed', 'leastudios-siteaudit' ),
				[ 'response' => 404 ]
			);
		}

		$report   = $this->pdf_data_collector->collect( $project );
		$body     = $this->pdf_report_service->generate( $report );
		$filename = sanitize_file_name( 'report-' . $project->name()->value() . '-' . wp_date( 'Y-m-d' ) . '.pdf' );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $body ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw PDF binary; escaping would corrupt it.
		echo $body;
		exit;
	}

	/**
	 * Verify nonce + view capability for a download endpoint.
	 *
	 * @param string $action Action name (also the nonce name).
	 *
	 * @return void
	 */
	private function guard( string $action ): void {
		if ( ! current_user_can( Capabilities::VIEW ) ) {
			wp_die(
				esc_html__( 'You do not have permission to export.', 'leastudios-siteaudit' ),
				esc_html__( 'Export forbidden', 'leastudios-siteaudit' ),
				[ 'response' => 403 ]
			);
		}

		// `check_admin_referer` accepts both POST and GET nonces.
		check_admin_referer( $action );
	}

	/**
	 * Build a sanitized CSV filename with the current date.
	 *
	 * @param string $base Logical base (e.g. URL display name, project name).
	 *
	 * @return string
	 */
	private function csv_filename( string $base ): string {
		return sanitize_file_name( $base . '-' . wp_date( 'Y-m-d' ) . '.csv' );
	}

	/**
	 * Stream a CSV body with the appropriate download headers and exit.
	 *
	 * @param string $body     CSV body.
	 * @param string $filename Sanitized filename.
	 *
	 * @return void
	 */
	private function stream_csv( string $body, string $filename ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV body is composed by Csv_Export_Service which already RFC 4180-escapes cells.
		echo $body;
		exit;
	}
}
