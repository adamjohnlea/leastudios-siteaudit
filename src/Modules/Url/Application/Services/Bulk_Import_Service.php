<?php
/**
 * Bulk URL import service (paste-list and CSV).
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Bulk_Import_Result;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Url_Address;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;

/**
 * Imports a batch of URLs from either a newline-delimited paste list or a CSV.
 *
 * Both inputs are normalised to the same internal row shape, then run through
 * a single `process_rows()` loop that:
 *   - validates each URL through `Url_Address` (collects errors for invalid rows),
 *   - skips rows whose URL is already present in the database OR earlier in the batch,
 *   - persists the rest with the configured frequency, default strategy, and project id.
 *
 * Returns a `Bulk_Import_Result` summarising the outcome for the admin UI.
 */
final class Bulk_Import_Service {

	/**
	 * URL persistence boundary.
	 *
	 * @var Url_Repository_Interface
	 */
	private Url_Repository_Interface $url_repository;

	/**
	 * Constructor.
	 *
	 * @param Url_Repository_Interface $url_repository Repository implementation.
	 */
	public function __construct( Url_Repository_Interface $url_repository ) {
		$this->url_repository = $url_repository;
	}

	/**
	 * Import URLs from a newline-delimited paste list.
	 *
	 * Each non-empty trimmed line is treated as both the URL and the display name.
	 *
	 * @param string   $text       Raw paste-list text.
	 * @param string   $frequency  Frequency enum value applied to every imported row.
	 * @param int|null $project_id Owning project id, or null for unassigned.
	 *
	 * @return Bulk_Import_Result
	 */
	public function import_from_list( string $text, string $frequency, ?int $project_id ): Bulk_Import_Result {
		$lines = explode( "\n", $text );
		$rows  = [];

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( '' === $trimmed ) {
				continue;
			}
			$rows[] = [
				'url'       => $trimmed,
				'name'      => $trimmed,
				'frequency' => $frequency,
			];
		}

		return $this->process_rows( $rows, $frequency, $project_id );
	}

	/**
	 * Import URLs from a CSV string.
	 *
	 * The first non-empty line must be a header containing at least a `url` column.
	 * Optional `name` and `frequency` columns are honoured per-row when present.
	 *
	 * @param string   $csv_content       Raw CSV content.
	 * @param string   $default_frequency Frequency used when a row has no `frequency` column or value.
	 * @param int|null $project_id        Owning project id, or null for unassigned.
	 *
	 * @return Bulk_Import_Result
	 */
	public function import_from_csv( string $csv_content, string $default_frequency, ?int $project_id ): Bulk_Import_Result {
		$lines = explode( "\n", $csv_content );
		$lines = array_filter(
			$lines,
			static fn( string $line ): bool => '' !== trim( $line )
		);

		if ( [] === $lines ) {
			return new Bulk_Import_Result( 0, 0, [] );
		}

		$header_line = (string) array_shift( $lines );
		$headers_raw = str_getcsv( $header_line, ',', '"', '' );
		$headers     = array_map(
			static fn( $h ): string => strtolower( trim( (string) $h ) ),
			$headers_raw
		);

		$url_index = array_search( 'url', $headers, true );
		if ( false === $url_index ) {
			return new Bulk_Import_Result(
				0,
				0,
				[
					[
						'line'  => 1,
						'url'   => '',
						'error' => 'CSV must contain a "url" column header',
					],
				]
			);
		}

		$name_index      = array_search( 'name', $headers, true );
		$frequency_index = array_search( 'frequency', $headers, true );

		$rows = [];
		foreach ( $lines as $line ) {
			$fields = str_getcsv( $line, ',', '"', '' );

			$url_raw = isset( $fields[ $url_index ] ) ? trim( (string) $fields[ $url_index ] ) : '';
			if ( '' === $url_raw ) {
				continue;
			}

			$name_value = $url_raw;
			if ( false !== $name_index && isset( $fields[ $name_index ] ) && '' !== trim( (string) $fields[ $name_index ] ) ) {
				$name_value = trim( (string) $fields[ $name_index ] );
			}

			$frequency_value = $default_frequency;
			if ( false !== $frequency_index && isset( $fields[ $frequency_index ] ) && '' !== trim( (string) $fields[ $frequency_index ] ) ) {
				$frequency_value = trim( (string) $fields[ $frequency_index ] );
			}

			$rows[] = [
				'url'       => $url_raw,
				'name'      => $name_value,
				'frequency' => $frequency_value,
			];
		}

		return $this->process_rows( $rows, $default_frequency, $project_id );
	}

	/**
	 * Process the normalised row list, persisting valid new URLs and collecting errors.
	 *
	 * @param array<int, array{url: string, name: string, frequency: string}> $rows              Normalised rows.
	 * @param string                                                          $default_frequency Fallback frequency value.
	 * @param int|null                                                        $project_id        Owning project id, or null.
	 *
	 * @return Bulk_Import_Result
	 */
	private function process_rows( array $rows, string $default_frequency, ?int $project_id ): Bulk_Import_Result {
		$existing_urls = $this->url_repository->find_all();
		$existing_set  = [];
		foreach ( $existing_urls as $existing ) {
			$existing_set[ $existing->url()->value() ] = true;
		}

		$imported_set   = [];
		$imported_count = 0;
		$skipped_count  = 0;
		$errors         = [];

		foreach ( $rows as $line_index => $row ) {
			$line_number = $line_index + 1;

			try {
				$url_address = new Url_Address( $row['url'] );
			} catch ( Validation_Exception $e ) {
				$errors[] = [
					'line'  => $line_number,
					'url'   => $row['url'],
					'error' => $e->getMessage(),
				];
				continue;
			}

			$url_value = $url_address->value();

			if ( isset( $existing_set[ $url_value ] ) || isset( $imported_set[ $url_value ] ) ) {
				++$skipped_count;
				continue;
			}

			$frequency = Audit_Frequency::tryFrom( $row['frequency'] )
				?? Audit_Frequency::tryFrom( $default_frequency )
				?? Audit_Frequency::WEEKLY;
			$now       = new \DateTimeImmutable();

			$url_model = new Url(
				null,
				$project_id,
				$url_address,
				$row['name'],
				$frequency,
				Audit_Strategy::BOTH,
				true,
				false,
				null,
				null,
				null,
				$now,
				$now,
			);

			$this->url_repository->save( $url_model );
			$imported_set[ $url_value ] = true;
			++$imported_count;
		}

		return new Bulk_Import_Result( $imported_count, $skipped_count, $errors );
	}
}
