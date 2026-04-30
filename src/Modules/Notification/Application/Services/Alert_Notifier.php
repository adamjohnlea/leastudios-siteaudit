<?php
/**
 * Score-threshold alert notifier.
 *
 * @package LEAStudios\SiteAudit\Modules\Notification\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Notification\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Notification\Domain\Repositories\Email_Subscription_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;

/**
 * Sends an alert email to project subscribers when an audit breaches a
 * configured threshold. Two threshold types are evaluated independently:
 *
 *   - **score-below**: `score <= alert_threshold_score`
 *   - **drop-by**:    `previous_score - score >= alert_threshold_drop`
 *
 * Either one triggers a single alert email per subscriber. Both being set
 * and both being breached still results in one email.
 *
 * Hook target: this class is registered as a listener on the
 * `leastudios_siteaudit_audit_completed` action by `Plugin::init()`. Audit
 * service knows nothing about it.
 */
final class Alert_Notifier {

	/**
	 * Subscription repository.
	 *
	 * @var Email_Subscription_Repository_Interface
	 */
	private Email_Subscription_Repository_Interface $subscription_repository;

	/**
	 * Email service.
	 *
	 * @var Email_Service_Interface
	 */
	private Email_Service_Interface $email_service;

	/**
	 * Constructor.
	 *
	 * @param Email_Subscription_Repository_Interface $subscription_repository Subscription repo.
	 * @param Email_Service_Interface                 $email_service           Mail transport.
	 */
	public function __construct(
		Email_Subscription_Repository_Interface $subscription_repository,
		Email_Service_Interface $email_service
	) {
		$this->subscription_repository = $subscription_repository;
		$this->email_service           = $email_service;
	}

	/**
	 * Listener for `leastudios_siteaudit_audit_completed`.
	 *
	 * @param Audit      $audit          Just-completed audit.
	 * @param Url        $url            URL the audit ran on.
	 * @param Audit|null $previous_audit Latest completed audit prior to this one (null if none).
	 *
	 * @return void
	 */
	public function notify_if_threshold_breached( Audit $audit, Url $url, ?Audit $previous_audit ): void {
		if ( Audit_Status::COMPLETED !== $audit->status() ) {
			return;
		}
		if ( ! $url->alerts_enabled() ) {
			return;
		}

		$project_id = $url->project_id();
		if ( null === $project_id ) {
			return;
		}

		$current_score   = $audit->score()->value();
		$threshold_score = $url->alert_threshold_score();
		$threshold_drop  = $url->alert_threshold_drop();
		$previous_score  = null !== $previous_audit ? $previous_audit->score()->value() : null;

		$score_breached = null !== $threshold_score && $current_score <= $threshold_score;
		$drop_breached  = null !== $threshold_drop
			&& null !== $previous_score
			&& ( $previous_score - $current_score ) >= $threshold_drop;

		if ( ! $score_breached && ! $drop_breached ) {
			return;
		}

		$subscribers = $this->subscription_repository->find_subscribers_by_project_id( $project_id );
		if ( [] === $subscribers ) {
			return;
		}

		$display_name = $url->name() ?? $url->url()->value();
		$subject      = sprintf(
			/* translators: %s: URL display name. */
			__( 'Score Alert: %s', 'leastudios-siteaudit' ),
			$display_name
		);

		$body = $this->render_template(
			[
				'url_name'                 => $display_name,
				'url_address'              => $url->url()->value(),
				'current_score'            => $current_score,
				'previous_score'           => $previous_score,
				'score_drop'               => null !== $previous_score ? $previous_score - $current_score : null,
				'threshold_score'          => $threshold_score,
				'threshold_drop'           => $threshold_drop,
				'score_threshold_breached' => $score_breached,
				'drop_threshold_breached'  => $drop_breached,
			]
		);

		foreach ( $subscribers as $subscriber ) {
			$this->email_service->send( (string) $subscriber->user_email, $subject, $body );
		}
	}

	/**
	 * Render the alert email body partial with output buffering.
	 *
	 * @param array<string, mixed> $context Variables to extract.
	 *
	 * @return string
	 */
	private function render_template( array $context ): string {
		$file = LEASTUDIOS_SITEAUDIT_DIR . 'templates/emails/alert-score.php';

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- partials use bare names; admin-only context.
		extract( $context, EXTR_SKIP );
		include $file;
		$html = ob_get_clean();

		return false === $html ? '' : (string) $html;
	}
}
