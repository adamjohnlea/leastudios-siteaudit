<?php
/**
 * `wp_mail()`-backed email service.
 *
 * @package LEAStudios\SiteAudit\Modules\Notification\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Notification\Application\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Implements {@see Email_Service_Interface} via WordPress core's `wp_mail()`.
 *
 * For attachments, `wp_mail()` only accepts file paths. We write the raw
 * bytes to a unique temp file under the uploads dir, pass the path to
 * `wp_mail`, and *defer* the cleanup via Action Scheduler. Immediate
 * cleanup races with async mail plugins (FluentSMTP, WP Mail SMTP, SES,
 * Postmark, Mailgun) that intercept `pre_wp_mail`, capture the file path,
 * and read the file later in their own queue worker — by then a sync
 * `unlink` has already deleted it, so the email arrives without the
 * attachment. Five minutes of grace is enough for any reasonable
 * transactional pipeline.
 *
 * Sender address is left to WordPress's defaults (`get_option('admin_email')`)
 * so transactional-email plugins like `leastudios-mailer` can intercept and
 * route through SES / Postmark / Mailgun without configuration here.
 */
final class Wp_Mail_Service implements Email_Service_Interface {

	/**
	 * Subdirectory under the uploads basedir used for short-lived attachments.
	 */
	private const TEMP_SUBDIR = 'leastudios-siteaudit-tmp';

	/**
	 * Action Scheduler hook used to clean up an attachment temp file after
	 * any async mail pipeline has had time to read it.
	 */
	public const CLEANUP_HOOK = 'leastudios_siteaudit_cleanup_attachment';

	/**
	 * Delay before the cleanup action fires, in seconds.
	 */
	private const CLEANUP_DELAY_SECONDS = 300;

	/**
	 * Send a plain HTML email.
	 *
	 * @param string $to      Recipient address.
	 * @param string $subject Subject line.
	 * @param string $body    HTML body.
	 *
	 * @return bool
	 */
	public function send( string $to, string $subject, string $body ): bool {
		$result = wp_mail( $to, $subject, $body, $this->html_headers() );

		if ( ! $result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional error logging at the email boundary.
			error_log( sprintf( '[leastudios-siteaudit] wp_mail returned false for %s (subject: %s)', $to, $subject ) );
		}

		return (bool) $result;
	}

	/**
	 * Send an HTML email with one in-memory attachment.
	 *
	 * @param string $to                  Recipient address.
	 * @param string $subject             Subject line.
	 * @param string $body                HTML body.
	 * @param string $attachment_bytes    Raw bytes.
	 * @param string $attachment_filename Filename.
	 *
	 * @return bool
	 */
	public function send_with_attachment( string $to, string $subject, string $body, string $attachment_bytes, string $attachment_filename ): bool {
		if ( '' === $attachment_bytes ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional error logging at the email boundary.
			error_log( sprintf( '[leastudios-siteaudit] refusing to send 0-byte attachment to %s (subject: %s); falling back to body-only', $to, $subject ) );
			return $this->send( $to, $subject, $body );
		}

		$temp_file = $this->write_temp_file( $attachment_bytes, $attachment_filename );

		if ( null === $temp_file ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional error logging at the email boundary.
			error_log( sprintf( '[leastudios-siteaudit] could not write temp attachment for %s', $to ) );
			return false;
		}

		$result = wp_mail( $to, $subject, $body, $this->html_headers(), [ $temp_file ] );

		if ( ! $result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional error logging at the email boundary.
			error_log( sprintf( '[leastudios-siteaudit] wp_mail returned false for %s (subject: %s, attachment: %s)', $to, $subject, $attachment_filename ) );
		}

		$this->schedule_cleanup( $temp_file );

		return (bool) $result;
	}

	/**
	 * Action Scheduler listener: delete a temp attachment once any async
	 * mail pipeline has finished reading it. Defensive guard refuses paths
	 * outside our temp subdir so a hostile caller can't trick us into
	 * deleting arbitrary files.
	 *
	 * @param string $path Absolute path to the attachment file.
	 *
	 * @return void
	 */
	public function cleanup_attachment( string $path ): void {
		if ( ! str_contains( $path, self::TEMP_SUBDIR ) ) {
			return;
		}

		if ( file_exists( $path ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- best-effort cleanup; the file may already be gone.
			@unlink( $path );
		}
	}

	/**
	 * Schedule a deferred cleanup of the given temp attachment.
	 *
	 * @param string $temp_file Absolute path.
	 *
	 * @return void
	 */
	private function schedule_cleanup( string $temp_file ): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + self::CLEANUP_DELAY_SECONDS,
				self::CLEANUP_HOOK,
				[ $temp_file ],
				'leastudios-siteaudit'
			);
			return;
		}

		// Fall back to immediate cleanup if AS isn't available — accept the
		// race for native PHPMailer rather than leaking the file forever.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- best-effort cleanup.
		@unlink( $temp_file );
	}

	/**
	 * Standard headers for HTML mail.
	 *
	 * @return array<int, string>
	 */
	private function html_headers(): array {
		return [ 'Content-Type: text/html; charset=UTF-8' ];
	}

	/**
	 * Write the attachment bytes to a unique temp file. Returns the absolute
	 * path, or null on filesystem failure.
	 *
	 * @param string $bytes    Raw bytes.
	 * @param string $filename Display filename (used to choose extension).
	 *
	 * @return string|null
	 */
	private function write_temp_file( string $bytes, string $filename ): ?string {
		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['basedir'] ) ) {
			return null;
		}

		$dir = trailingslashit( $upload_dir['basedir'] ) . self::TEMP_SUBDIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}

		$extension = pathinfo( $filename, PATHINFO_EXTENSION );
		$extension = '' !== $extension ? '.' . sanitize_file_name( $extension ) : '';
		$path      = trailingslashit( $dir ) . uniqid( 'lsa-att-', true ) . $extension;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- targeted temp file under uploads, lifecycle managed locally.
		$written = file_put_contents( $path, $bytes );

		return false === $written ? null : $path;
	}
}
