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
		$bytes_size = strlen( $attachment_bytes );
		$this->diag( sprintf( 'send_with_attachment: to=%s subject="%s" filename=%s bytes=%d', $to, $subject, $attachment_filename, $bytes_size ) );

		if ( '' === $attachment_bytes ) {
			$this->diag( '  → 0-byte attachment, falling back to body-only send()' );
			return $this->send( $to, $subject, $body );
		}

		$temp_file = $this->write_temp_file( $attachment_bytes, $attachment_filename );

		if ( null === $temp_file ) {
			$this->diag( '  → write_temp_file FAILED' );
			return false;
		}

		$file_size = file_exists( $temp_file ) ? (int) filesize( $temp_file ) : -1;
		$this->diag( sprintf( '  → wrote temp file: %s (%d bytes on disk)', $temp_file, $file_size ) );

		// Use WordPress's `[display_name => path]` attachment form (added in
		// WP 5.6). Without this, PHPMailer derives the attachment's display
		// name from `basename($path)` — which exposes our internal temp
		// filename to the recipient. On installs whose `sanitize_file_name`
		// filter chain mangles the path (e.g. some security plugins inject
		// "unnamed-file"), the resulting attachment name gets flagged and
		// silently stripped by Gmail / mail security gateways.
		$result = wp_mail( $to, $subject, $body, $this->html_headers(), [ $attachment_filename => $temp_file ] );

		$still_exists = file_exists( $temp_file );
		$this->diag( sprintf( '  → wp_mail returned %s (file still on disk: %s)', $result ? 'true' : 'false', $still_exists ? 'yes' : 'no' ) );

		$this->schedule_cleanup( $temp_file );
		$this->diag( '  → cleanup scheduled' );

		return (bool) $result;
	}

	/**
	 * Always-on diagnostic logger that writes to a fixed file under uploads
	 * regardless of `WP_DEBUG_LOG`. Temporary instrumentation for tracing
	 * the missing-attachment bug; can be removed once the cause is known.
	 *
	 * @param string $line Message.
	 *
	 * @return void
	 */
	private function diag( string $line ): void {
		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['basedir'] ) ) {
			return;
		}

		$path  = trailingslashit( $upload_dir['basedir'] ) . 'leastudios-siteaudit-mail.log';
		$entry = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] ' . $line . "\n";

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- targeted diagnostic file write.
		@file_put_contents( $path, $entry, FILE_APPEND );
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
			$this->diag( sprintf( 'cleanup_attachment: REFUSED (path outside tmp subdir): %s', $path ) );
			return;
		}

		$existed = file_exists( $path );
		if ( $existed ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- best-effort cleanup; the file may already be gone.
			@unlink( $path );
		}
		$this->diag( sprintf( 'cleanup_attachment: %s (existed: %s)', $path, $existed ? 'yes' : 'no' ) );
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

		// Don't run the extension through `sanitize_file_name()` — some
		// install-level filters (security plugins) inject `unnamed-file`
		// when the input looks "weird" to them. Limit to lowercase alnum
		// instead; the extension always comes from filenames we control.
		$raw_extension = (string) pathinfo( $filename, PATHINFO_EXTENSION );
		$extension     = preg_replace( '/[^a-z0-9]/', '', strtolower( $raw_extension ) ) ?? '';
		$path          = trailingslashit( $dir ) . uniqid( 'lsa-att-', true );
		if ( '' !== $extension ) {
			$path .= '.' . $extension;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- targeted temp file under uploads, lifecycle managed locally.
		$written = file_put_contents( $path, $bytes );

		return false === $written ? null : $path;
	}
}
