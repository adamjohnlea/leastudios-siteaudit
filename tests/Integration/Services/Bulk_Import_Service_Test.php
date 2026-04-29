<?php
/**
 * Bulk_Import_Service integration tests against the real wpdb repository.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Services;

use LEAStudios\SiteAudit\Modules\Url\Application\Services\Bulk_Import_Service;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Url_Repository;
use LEAStudios\Tests\TestCase;

final class Bulk_Import_Service_Test extends TestCase {

	private Wpdb_Url_Repository $repository;
	private Bulk_Import_Service $service;

	public function set_up(): void {
		parent::set_up();
		$this->repository = new Wpdb_Url_Repository();
		$this->service    = new Bulk_Import_Service( $this->repository );
	}

	public function test_import_from_list_creates_urls_from_text(): void {
		$text = "https://example.com\nhttps://test.com\nhttps://other.org";

		$result = $this->service->import_from_list( $text, 'weekly', null );

		$this->assertSame( 3, $result->imported_count );
		$this->assertSame( 0, $result->skipped_count );
		$this->assertSame( [], $result->errors );
		$this->assertCount( 3, $this->repository->find_all() );
	}

	public function test_import_from_list_skips_empty_lines(): void {
		$text = "https://example.com\n\n\nhttps://test.com\n  \n";

		$result = $this->service->import_from_list( $text, 'weekly', null );

		$this->assertSame( 2, $result->imported_count );
		$this->assertCount( 2, $this->repository->find_all() );
	}

	public function test_import_from_list_skips_duplicate_urls_in_database(): void {
		$this->service->import_from_list( 'https://existing.com', 'weekly', null );

		$result = $this->service->import_from_list( "https://existing.com\nhttps://new-site.com", 'weekly', null );

		$this->assertSame( 1, $result->imported_count );
		$this->assertSame( 1, $result->skipped_count );
		$this->assertCount( 2, $this->repository->find_all() );
	}

	public function test_import_from_list_skips_duplicate_urls_within_batch(): void {
		$text = "https://example.com\nhttps://example.com\nhttps://example.com";

		$result = $this->service->import_from_list( $text, 'weekly', null );

		$this->assertSame( 1, $result->imported_count );
		$this->assertSame( 2, $result->skipped_count );
		$this->assertCount( 1, $this->repository->find_all() );
	}

	public function test_import_from_list_reports_invalid_urls(): void {
		$text = "https://valid.com\nnot-a-url\nhttps://also-valid.com\nftp://wrong-scheme.com";

		$result = $this->service->import_from_list( $text, 'weekly', null );

		$this->assertSame( 2, $result->imported_count );
		$this->assertCount( 2, $result->errors );
		$this->assertSame( 'not-a-url', $result->errors[0]['url'] );
	}

	public function test_import_from_csv_parses_header_and_rows(): void {
		$csv = "url,name,frequency\nhttps://example.com,Example Site,daily\nhttps://test.com,Test Site,weekly";

		$result = $this->service->import_from_csv( $csv, 'weekly', null );

		$this->assertSame( 2, $result->imported_count );
		$this->assertSame( 0, $result->skipped_count );

		$urls  = $this->repository->find_all();
		$names = array_map( static fn( $u ) => $u->name(), $urls );
		sort( $names );
		$this->assertSame( [ 'Example Site', 'Test Site' ], $names );
	}

	public function test_import_from_csv_uses_default_frequency_when_column_missing(): void {
		$csv = "url\nhttps://example.com\nhttps://test.com";

		$result = $this->service->import_from_csv( $csv, 'monthly', null );

		$this->assertSame( 2, $result->imported_count );
		foreach ( $this->repository->find_all() as $url ) {
			$this->assertSame( 'monthly', $url->audit_frequency()->value );
		}
	}

	public function test_import_from_csv_handles_blank_name_column(): void {
		$csv = "url,name\nhttps://example.com,My Site\nhttps://test.com,";

		$result = $this->service->import_from_csv( $csv, 'weekly', null );

		$this->assertSame( 2, $result->imported_count );

		$urls   = $this->repository->find_all();
		$by_url = [];
		foreach ( $urls as $u ) {
			$by_url[ $u->url()->value() ] = $u->name();
		}

		$this->assertSame( 'My Site', $by_url['https://example.com'] );
		$this->assertSame( 'https://test.com', $by_url['https://test.com'] );
	}

	public function test_import_from_csv_skips_duplicates(): void {
		$this->service->import_from_list( 'https://existing.com', 'weekly', null );

		$csv = "url,name\nhttps://existing.com,Existing\nhttps://new-site.com,New";

		$result = $this->service->import_from_csv( $csv, 'weekly', null );

		$this->assertSame( 1, $result->imported_count );
		$this->assertSame( 1, $result->skipped_count );
	}

	public function test_import_from_csv_returns_error_when_url_column_missing(): void {
		$csv = "name,frequency\nMy Site,weekly";

		$result = $this->service->import_from_csv( $csv, 'weekly', null );

		$this->assertSame( 0, $result->imported_count );
		$this->assertCount( 1, $result->errors );
		$this->assertSame( 'CSV must contain a "url" column header', $result->errors[0]['error'] );
	}
}
