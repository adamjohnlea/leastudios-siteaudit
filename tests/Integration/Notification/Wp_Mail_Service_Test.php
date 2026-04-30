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

		// `getAttachments()` returns a list of arrays. Index 0 is the path,
		// index 2 is the display name PHPMailer will use in the MIME part.
		$attachments = $mailer->getAttachments();
		$this->assertCount( 1, $attachments );

		// The on-disk file lives under our temp dir.
		$this->assertStringContainsString( 'leastudios-siteaudit-tmp', $attachments[0][0] );

		// The recipient sees the clean display name we passed in, not the
		// scrambled internal temp filename. Without this, mail security
		// gateways (Gmail's anti-malware in particular) flag attachments
		// with placeholder-looking names like `lsa-att-XXX.unnamed-file.pdf`
		// and silently drop them — which was the original bug.
		$this->assertSame( 'report-test.pdf', $attachments[0][2] );
	}

	public function test_send_with_attachment_schedules_deferred_cleanup(): void {
		// Action Scheduler is bootstrapped by the plugin's main file at
		// PHPUnit run time; if the function is unavailable in this env, skip.
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler not loaded in this test environment.' );
		}

		// Clear any pending cleanup actions queued by earlier tests.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Wp_Mail_Service::CLEANUP_HOOK );
		}

		$this->service->send_with_attachment(
			'recipient@example.com',
			'Subject',
			'Body',
			'bytes',
			'file.pdf'
		);

		$pending = as_get_scheduled_actions(
			[
				'hook'   => Wp_Mail_Service::CLEANUP_HOOK,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			],
			'ids'
		);

		$this->assertCount( 1, $pending, 'A single cleanup action must be queued for the attachment.' );

		// Tidy up so this test doesn't bleed into others.
		as_unschedule_all_actions( Wp_Mail_Service::CLEANUP_HOOK );
	}

	public function test_cleanup_attachment_deletes_files_in_tmp_subdir(): void {
		$upload_dir = wp_upload_dir();
		$temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'leastudios-siteaudit-tmp';
		wp_mkdir_p( $temp_dir );
		$path = $temp_dir . '/test-cleanup-' . uniqid( '', true ) . '.pdf';
		file_put_contents( $path, 'fake' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$this->assertFileExists( $path );
		$this->service->cleanup_attachment( $path );
		$this->assertFileDoesNotExist( $path );
	}

	public function test_cleanup_attachment_refuses_paths_outside_tmp_subdir(): void {
		$path = sys_get_temp_dir() . '/lsa-dangerous-' . uniqid( '', true ) . '.txt';
		file_put_contents( $path, 'fake' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$this->service->cleanup_attachment( $path );

		$this->assertFileExists( $path, 'Files outside the temp subdir must not be deleted.' );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- test cleanup.
		@unlink( $path );
	}

	public function test_send_with_attachment_falls_back_to_body_only_for_zero_byte_attachment(): void {
		$ok = $this->service->send_with_attachment(
			'recipient@example.com',
			'Subject',
			'<p>Body only</p>',
			'',
			'empty.pdf'
		);

		$this->assertTrue( $ok );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertNotFalse( $mailer );
		$this->assertSame( [], $mailer->getAttachments(), 'No attachment must be sent for an empty body.' );
	}
}
