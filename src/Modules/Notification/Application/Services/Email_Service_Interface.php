<?php
/**
 * Outbound email service contract.
 *
 * @package LEAStudios\SiteAudit\Modules\Notification\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Notification\Application\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Tiny abstraction over `wp_mail()` so notifiers can be unit-tested without
 * a global state mutation. Implementations swallow recoverable failures —
 * a bad address must not stop the loop sending to other subscribers — and
 * `error_log` the cause for the admin to debug.
 */
interface Email_Service_Interface {

	/**
	 * Send a plain HTML email with no attachments.
	 *
	 * @param string $to      Recipient address.
	 * @param string $subject Subject line.
	 * @param string $body    HTML body.
	 *
	 * @return bool Whether `wp_mail()` accepted the message.
	 */
	public function send( string $to, string $subject, string $body ): bool;

	/**
	 * Send an HTML email with one in-memory attachment (e.g. the PDF report).
	 *
	 * @param string $to                  Recipient address.
	 * @param string $subject             Subject line.
	 * @param string $body                HTML body.
	 * @param string $attachment_bytes    Raw bytes of the attachment.
	 * @param string $attachment_filename Filename to expose to the recipient.
	 *
	 * @return bool Whether `wp_mail()` accepted the message.
	 */
	public function send_with_attachment( string $to, string $subject, string $body, string $attachment_bytes, string $attachment_filename ): bool;
}
