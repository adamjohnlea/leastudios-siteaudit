<?php
/**
 * Reporting_Controller integration test.
 *
 * Covers the wp_die-terminating paths (capability denial, nonce failure,
 * 404 on missing record) for all three download endpoints. The successful
 * streaming paths end with `header()` + `echo` + `exit` — testing those
 * requires `runInSeparateProcess`, which is heavy and brittle; the body
 * shape is exercised by `Csv_Export_Service_Test` and
 * `Pdf_Report_Data_Collector_Test` at the service layer, and the
 * end-to-end behavior is covered by the manual smoke test in the Phase 6
 * verification checklist.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Admin;

use LEAStudios\SiteAudit\Activation;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Audit_Repository;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Issue_Repository;
use LEAStudios\SiteAudit\Modules\Dashboard\Application\Services\Dashboard_Statistics;
use LEAStudios\SiteAudit\Modules\Reporting\Admin\Reporting_Controller;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Csv_Export_Service;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Pdf_Report_Data_Collector;
use LEAStudios\SiteAudit\Modules\Reporting\Application\Services\Pdf_Report_Service;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Project_Repository;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Url_Repository;
use LEAStudios\Tests\TestCase;

final class Reporting_Controller_Test extends TestCase {

	private Reporting_Controller $controller;

	private Wpdb_Project_Repository $project_repository;

	private Wpdb_Url_Repository $url_repository;

	public function set_up(): void {
		parent::set_up();

		( new Activation() )->run();

		$this->project_repository = new Wpdb_Project_Repository();
		$this->url_repository     = new Wpdb_Url_Repository();
		$audit_repository         = new Wpdb_Audit_Repository();
		$issue_repository         = new Wpdb_Issue_Repository();
		$statistics               = new Dashboard_Statistics();

		$this->controller = new Reporting_Controller(
			$this->project_repository,
			$this->url_repository,
			$audit_repository,
			$statistics,
			new Csv_Export_Service(),
			new Pdf_Report_Data_Collector(
				$this->url_repository,
				$audit_repository,
				$issue_repository,
				$statistics
			),
			new Pdf_Report_Service()
		);

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
	}

	public function tear_down(): void {
		$_GET = [];
		parent::tear_down();
	}

	public function test_init_registers_three_admin_post_hooks(): void {
		$this->controller->init();

		$this->assertNotFalse( has_action( 'admin_post_' . Reporting_Controller::ACTION_EXPORT_AUDITS ) );
		$this->assertNotFalse( has_action( 'admin_post_' . Reporting_Controller::ACTION_EXPORT_SUMMARY ) );
		$this->assertNotFalse( has_action( 'admin_post_' . Reporting_Controller::ACTION_EXPORT_PDF ) );
	}

	public function test_export_audits_dies_for_user_without_view_capability(): void {
		$logged_out_user = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		// Subscribers don't have `view_leastudios_siteaudit`.
		wp_set_current_user( $logged_out_user );

		$this->expectException( \WPDieException::class );
		$this->controller->handle_export_audits();
	}

	public function test_export_audits_dies_when_nonce_is_missing(): void {
		// View-cap user with no nonce in the request — check_admin_referer dies.
		$_GET = [ 'url_id' => '1' ];

		$this->expectException( \WPDieException::class );
		$this->controller->handle_export_audits();
	}

	public function test_export_audits_dies_when_url_does_not_exist(): void {
		$_GET = [
			'url_id'   => '99999',
			'_wpnonce' => wp_create_nonce( Reporting_Controller::ACTION_EXPORT_AUDITS ),
		];

		$this->expectException( \WPDieException::class );
		$this->controller->handle_export_audits();
	}

	public function test_export_summary_dies_when_project_does_not_exist(): void {
		$_GET = [
			'project_id' => '99999',
			'_wpnonce'   => wp_create_nonce( Reporting_Controller::ACTION_EXPORT_SUMMARY ),
		];

		$this->expectException( \WPDieException::class );
		$this->controller->handle_export_summary();
	}

	public function test_export_pdf_dies_when_project_does_not_exist(): void {
		$_GET = [
			'project_id' => '99999',
			'_wpnonce'   => wp_create_nonce( Reporting_Controller::ACTION_EXPORT_PDF ),
		];

		$this->expectException( \WPDieException::class );
		$this->controller->handle_export_pdf();
	}

	public function test_export_pdf_dies_for_subscriber_user(): void {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );

		$_GET = [];

		$this->expectException( \WPDieException::class );
		$this->controller->handle_export_pdf();
	}
}
