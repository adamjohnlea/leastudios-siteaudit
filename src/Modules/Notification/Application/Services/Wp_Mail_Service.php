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
 * bytes to a unique temp file under the uploads dir, pass the path, and
 * always `unlink()` in a `finally` — even when `wp_mail` throws. The temp
 * directory is created lazily on first use.
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
		$temp_file = $this->write_temp_file( $attachment_bytes, $attachment_filename );

		if ( null === $temp_file ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional error logging at the email boundary.
			error_log( sprintf( '[leastudios-siteaudit] could not write temp attachment for %s', $to ) );
			return false;
		}

		try {
			$result = wp_mail( $to, $subject, $body, $this->html_headers(), [ $temp_file ] );

			if ( ! $result ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional error logging at the email boundary.
				error_log( sprintf( '[leastudios-siteaudit] wp_mail returned false for %s (subject: %s, attachment: %s)', $to, $subject, $attachment_filename ) );
			}

			return (bool) $result;
		} finally {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- best-effort cleanup; we don't care if the file is already gone.
			@unlink( $temp_file );
		}
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
