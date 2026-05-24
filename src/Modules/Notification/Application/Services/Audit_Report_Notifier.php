<?php
/**
 * Per-project PDF report notifier.
 *
 * @package LEAStudios\SiteAudit\Modules\Notification\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Notification\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Notification\Domain\Repositories\Email_Subscription_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Pdf_Report_Data_Collector_Interface;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Pdf_Report_Service_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Project_Repository_Interface;
use LEAStudios\SiteAudit\Shared\Template_Renderer;

/**
 * Sends the freshly-rendered project PDF to every subscriber after each
 * successful audit.
 *
 * Per the Phase 7 design notes: per-audit firing matches the source app's
 * cadence and avoids the "are all audits done yet?" race introduced by
 * trying to batch at the tick boundary. Volume is gated by the subscriber
 * list — projects with no subscribers do no work.
 */
final class Audit_Report_Notifier {

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
	 * PDF data collector.
	 *
	 * @var Pdf_Report_Data_Collector_Interface
	 */
	private Pdf_Report_Data_Collector_Interface $data_collector;

	/**
	 * PDF rendering service.
	 *
	 * @var Pdf_Report_Service_Interface
	 */
	private Pdf_Report_Service_Interface $pdf_service;

	/**
	 * Email service.
	 *
	 * @var Email_Service_Interface
	 */
	private Email_Service_Interface $email_service;

	/**
	 * Template renderer.
	 *
	 * @var Template_Renderer
	 */
	private Template_Renderer $template_renderer;

	/**
	 * Constructor.
	 *
	 * @param Project_Repository_Interface            $project_repository      Project repo.
	 * @param Email_Subscription_Repository_Interface $subscription_repository Subscription repo.
	 * @param Pdf_Report_Data_Collector_Interface     $data_collector          PDF data collector.
	 * @param Pdf_Report_Service_Interface            $pdf_service             PDF service.
	 * @param Email_Service_Interface                 $email_service           Mail transport.
	 * @param Template_Renderer                       $template_renderer       Renders the report body partial.
	 */
	public function __construct(
		Project_Repository_Interface $project_repository,
		Email_Subscription_Repository_Interface $subscription_repository,
		Pdf_Report_Data_Collector_Interface $data_collector,
		Pdf_Report_Service_Interface $pdf_service,
		Email_Service_Interface $email_service,
		Template_Renderer $template_renderer
	) {
		$this->project_repository      = $project_repository;
		$this->subscription_repository = $subscription_repository;
		$this->data_collector          = $data_collector;
		$this->pdf_service             = $pdf_service;
		$this->email_service           = $email_service;
		$this->template_renderer       = $template_renderer;
	}

	/**
	 * Listener for `leastudios_siteaudit_audit_completed`.
	 *
	 * Fires once per `run_audit()` call (not once per strategy). Skips if
	 * the URL is unassigned or every strategy in this run failed.
	 *
	 * @param Url                       $url             URL audited.
	 * @param array<int, Audit>         $audits          Audits produced by this run.
	 * @param array<string, Audit|null> $previous_audits Map of strategy value => prior audit. Unused; signature parity with the action.
	 *
	 * @return void
	 */
	public function on_audit_completed( Url $url, array $audits, array $previous_audits = [] ): void {
		unset( $previous_audits ); // Listener signature parity; not needed here.

		$project_id = $url->project_id();
		if ( null === $project_id ) {
			return;
		}

		// Skip if every strategy failed — no completed data to report on.
		$any_completed = false;
		foreach ( $audits as $audit ) {
			if ( Audit_Status::COMPLETED === $audit->status() ) {
				$any_completed = true;
				break;
			}
		}
		if ( ! $any_completed ) {
			return;
		}

		$this->notify_for_project( $project_id );
	}

	/**
	 * Render and dispatch the report PDF for one project.
	 *
	 * @param int $project_id Project id.
	 *
	 * @return void
	 */
	public function notify_for_project( int $project_id ): void {
		$project = $this->project_repository->find_by_id( $project_id );
		if ( null === $project ) {
			return;
		}

		$subscribers = $this->subscription_repository->find_subscribers_by_project_id( $project_id );
		if ( [] === $subscribers ) {
			return;
		}

		// Dompdf is memory-hungry (~30MB per render). The Action Scheduler
		// worker that fires us inherits PHP's default memory_limit; bump to
		// the admin tier so projects with many issues don't OOM silently and
		// produce empty PDF bytes — which would then trip the Wp_Mail_Service
		// fallback to body-only.
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		$report   = $this->data_collector->collect( $project );
		$pdf      = $this->pdf_service->generate( $report );
		$filename = 'report-' . sanitize_title( $project->name()->value() ) . '.pdf';

		$subject = sprintf(
			/* translators: %s: project name. */
			__( 'Audit Report: %s', 'leastudios-siteaudit' ),
			$project->name()->value()
		);

		$body = $this->template_renderer->render_to_string(
			'emails/audit-report.php',
			[
				'leastudios_siteaudit_project_name'  => $project->name()->value(),
				'leastudios_siteaudit_date'          => wp_date( 'Y-m-d H:i' ),
				'leastudios_siteaudit_average_score' => $report->summary()->average_score(),
				'leastudios_siteaudit_total_urls'    => $report->summary()->total_urls(),
				'leastudios_siteaudit_total_issues'  => $report->total_issues(),
			]
		);

		foreach ( $subscribers as $subscriber ) {
			$this->email_service->send_with_attachment(
				(string) $subscriber->user_email,
				$subject,
				$body,
				$pdf,
				$filename
			);
		}
	}
}
