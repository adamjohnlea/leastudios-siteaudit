<?php
/**
 * Wp_Mail_Service integration test.
 *
 * Uses WordPress's built-in MockPHPMailer (`reset_phpmailer_instance()` +
 * `tests_retrieve_phpmailer_instance()`) to capture what would have been
 * sent without making any real network calls.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Notification;

use LEAStudios\SiteAudit\Modules\Notification\Application\Services\Wp_Mail_Service;
use LEAStudios\Tests\TestCase;

final class Wp_Mail_Service_Test extends TestCase {

	private Wp_Mail_Service $service;

	public function set_up(): void {
		parent::set_up();
		reset_phpmailer_instance();
		$this->service = new Wp_Mail_Service();
	}

	public function tear_down(): void {
		reset_phpmailer_instance();
		parent::tear_down();
	}

	public function test_send_dispatches_html_email_to_recipient(): void {
		$ok = $this->service->send( 'recipient@example.com', 'Hello', '<p>World</p>' );

		$this->assertTrue( $ok );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertNotFalse( $mailer );
		$this->assertSame( 'Hello', $mailer->get_sent()->subject );
		$this->assertStringContainsString( '<p>World</p>', $mailer->get_sent()->body );

		// Confirm Content-Type header was set to text/html.
		$this->assertStringContainsString( 'text/html', $mailer->get_sent()->header );
	}

	public function test_send_with_attachment_includes_the_attachment(): void {
		$pdf_bytes = '%PDF-1.4 fake bytes here';

		$ok = $this->service->send_with_attachment(
			'recipient@example.com',
			'Audit Report',
			'<p>Body</p>',
			$pdf_bytes,
			'report-test.pdf'
		);

		$this->assertTrue( $ok );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertNotFalse( $mailer );

		// `getAttachments()` returns a list of arrays; the file path is index 0.
		$attachments = $mailer->getAttachments();
		$this->assertCount( 1, $attachments );

		// The temp file is unlinked after wp_mail returns, so we can't read
		// it; but we can confirm the attached path was under our temp dir.
		$this->assertStringContainsString( 'leastudios-siteaudit-tmp', $attachments[0][0] );
	}

	public function test_send_with_attachment_cleans_up_temp_file_after_send(): void {
		$upload_dir = wp_upload_dir();
		$temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'leastudios-siteaudit-tmp';

		$before_files = is_dir( $temp_dir ) ? glob( $temp_dir . '/*' ) : [];
		$before_count = is_array( $before_files ) ? count( $before_files ) : 0;

		$this->service->send_with_attachment(
			'recipient@example.com',
			'Subject',
			'Body',
			'bytes',
			'file.pdf'
		);

		$after_files = is_dir( $temp_dir ) ? glob( $temp_dir . '/*' ) : [];
		$after_count = is_array( $after_files ) ? count( $after_files ) : 0;

		$this->assertSame( $before_count, $after_count, 'Temp attachment file must be unlinked after send.' );
	}
}
