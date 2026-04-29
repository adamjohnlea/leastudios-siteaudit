<?php
/**
 * Outcome of a bulk URL import.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable summary of a paste-list or CSV bulk import.
 *
 * Reported back to the admin UI to render a result page describing how many
 * rows imported cleanly, how many were skipped as duplicates, and any
 * per-row validation errors.
 */
final class Bulk_Import_Result {

	/**
	 * Number of URLs that were saved during this import.
	 *
	 * @var int
	 */
	public readonly int $imported_count;

	/**
	 * Number of valid URLs skipped because they were already present.
	 *
	 * @var int
	 */
	public readonly int $skipped_count;

	/**
	 * Per-row validation errors. Each entry has `line`, `url`, and `error` keys.
	 *
	 * @var array<int, array{line: int, url: string, error: string}>
	 */
	public readonly array $errors;

	/**
	 * Constructor.
	 *
	 * @param int                                                      $imported_count Number of URLs saved.
	 * @param int                                                      $skipped_count  Number of URLs skipped as duplicates.
	 * @param array<int, array{line: int, url: string, error: string}> $errors        Per-row validation errors.
	 */
	public function __construct( int $imported_count, int $skipped_count, array $errors ) {
		$this->imported_count = $imported_count;
		$this->skipped_count  = $skipped_count;
		$this->errors         = $errors;
	}

	/**
	 * Total rows considered (imported + skipped + errored).
	 *
	 * @return int
	 */
	public function total_processed(): int {
		return $this->imported_count + $this->skipped_count + count( $this->errors );
	}

	/**
	 * Whether any per-row validation errors occurred.
	 *
	 * @return bool
	 */
	public function has_errors(): bool {
		return count( $this->errors ) > 0;
	}
}
