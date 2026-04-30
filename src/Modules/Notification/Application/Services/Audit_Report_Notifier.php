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
	 * Constructor.
	 *
	 * @param Project_Repository_Interface            $project_repository      Project repo.
	 * @param Email_Subscription_Repository_Interface $subscription_repository Subscription repo.
	 * @param Pdf_Report_Data_Collector_Interface     $data_collector          PDF data collector.
	 * @param Pdf_Report_Service_Interface            $pdf_service             PDF service.
	 * @param Email_Service_Interface                 $email_service           Mail transport.
	 */
	public function __construct(
		Project_Repository_Interface $project_repository,
		Email_Subscription_Repository_Interface $subscription_repository,
		Pdf_Report_Data_Collector_Interface $data_collector,
		Pdf_Report_Service_Interface $pdf_service,
		Email_Service_Interface $email_service
	) {
		$this->project_repository      = $project_repository;
		$this->subscription_repository = $subscription_repository;
		$this->data_collector          = $data_collector;
		$this->pdf_service             = $pdf_service;
		$this->email_service           = $email_service;
	}

	/**
	 * Listener for `leastudios_siteaudit_audit_completed`.
	 *
	 * Resolves the project from the audited URL and dispatches a per-project
	 * report. URLs with no project (`Unassigned URLs` in the dashboard) are
	 * skipped — there's no project to subscribe to.
	 *
	 * @param Audit      $audit          Just-completed audit.
	 * @param Url        $url            URL the audit ran on.
	 * @param Audit|null $previous_audit Unused; here to match the action signature.
	 *
	 * @return void
	 */
	public function on_audit_completed( Audit $audit, Url $url, ?Audit $previous_audit = null ): void {
		unset( $previous_audit ); // Listener signature parity; not needed here.

		if ( Audit_Status::COMPLETED !== $audit->status() ) {
			return;
		}

		$project_id = $url->project_id();
		if ( null === $project_id ) {
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

		$report   = $this->data_collector->collect( $project );
		$pdf      = $this->pdf_service->generate( $report );
		$filename = 'report-' . sanitize_title( $project->name()->value() ) . '.pdf';

		$subject = sprintf(
			/* translators: %s: project name. */
			__( 'Audit Report: %s', 'leastudios-siteaudit' ),
			$project->name()->value()
		);

		$body = $this->render_template(
			[
				'project_name'  => $project->name()->value(),
				'date'          => wp_date( 'Y-m-d H:i' ),
				'average_score' => $report->summary()->average_score(),
				'total_urls'    => $report->summary()->total_urls(),
				'total_issues'  => $report->total_issues(),
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

	/**
	 * Render the report email body partial with output buffering.
	 *
	 * @param array<string, mixed> $context Variables to extract.
	 *
	 * @return string
	 */
	private function render_template( array $context ): string {
		$file = LEASTUDIOS_SITEAUDIT_DIR . 'templates/emails/audit-report.php';

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- partials use bare names.
		extract( $context, EXTR_SKIP );
		include $file;
		$html = ob_get_clean();

		return false === $html ? '' : (string) $html;
	}
}
