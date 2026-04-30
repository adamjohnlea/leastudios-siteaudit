<?php
/**
 * CSV export composition for audit history and URL summaries.
 *
 * @package LEAStudios\SiteAudit\Modules\Reporting\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Reporting\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;
use LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Url_Summary;
use LEAStudios\SiteAudit\Shared\Datetime_Util;

/**
 * Pure-function CSV builder for two variants:
 *
 *   - `export_audits()` — per-URL audit history (date / score / status / grade)
 *   - `export_url_summaries()` — project-scope URL list (name / address / score / audits / frequency)
 *
 * Both methods return the full CSV as a string. Callers that need to stream
 * directly to the browser write that string to `php://output`; callers that
 * test the output (or pipe through filters) consume it as a string.
 *
 * Direct port of `CsvExportService` from the source app — same column shape,
 * same null handling (`'N/A'`), same RFC 4180-style escape rules.
 */
final class Csv_Export_Service {

	/**
	 * CSV header row for audit history.
	 */
	private const AUDIT_HEADER = 'Date,Score,Status,Grade';

	/**
	 * CSV header row for URL summaries.
	 */
	private const SUMMARY_HEADER = 'Name,URL,Latest Score,Total Audits,Frequency';

	/**
	 * Compose a CSV of an audit history (one row per audit, newest first).
	 *
	 * @param array<int, Audit> $audits Audits in DESC order from the repository.
	 *
	 * @return string CSV body, terminated by a trailing newline.
	 */
	public function export_audits( array $audits ): string {
		$lines = [ self::AUDIT_HEADER ];

		foreach ( $audits as $audit ) {
			$score   = $audit->score()->value();
			$lines[] = implode(
				',',
				[
					$this->escape_csv( Datetime_Util::format_immutable_for_display( $audit->audit_date(), 'Y-m-d H:i:s' ) ),
					$this->escape_csv( (string) $score ),
					$this->escape_csv( $audit->status()->label() ),
					$this->escape_csv( $this->score_to_grade( $score ) ),
				]
			);
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Compose a CSV of per-URL summaries (one row per URL).
	 *
	 * @param array<int, Url_Summary> $url_summaries URL summaries.
	 *
	 * @return string CSV body, terminated by a trailing newline.
	 */
	public function export_url_summaries( array $url_summaries ): string {
		$lines = [ self::SUMMARY_HEADER ];

		foreach ( $url_summaries as $url_summary ) {
			$score   = $url_summary->latest_score();
			$lines[] = implode(
				',',
				[
					$this->escape_csv( $url_summary->name() ),
					$this->escape_csv( $url_summary->address() ),
					null !== $score ? (string) $score : 'N/A',
					(string) $url_summary->total_audits(),
					$this->escape_csv( $url_summary->frequency() ),
				]
			);
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Letter-grade mapping matching the dashboard's score-to-grade rule.
	 *
	 * @param int $score 0-100.
	 *
	 * @return string A / B / C / F.
	 */
	private function score_to_grade( int $score ): string {
		return match ( true ) {
			$score >= 90 => 'A',
			$score >= 70 => 'B',
			$score >= 50 => 'C',
			default      => 'F',
		};
	}

	/**
	 * Escape a single CSV cell against two failure modes:
	 *
	 *  1. RFC 4180 quoting — wrap in `"..."` and double-up embedded quotes
	 *     when the value contains a comma, quote, CR, or LF.
	 *  2. Formula injection — Excel and Google Sheets interpret cells whose
	 *     first character is `=`, `+`, `-`, `@`, TAB, or CR as a formula
	 *     and execute attacker-controlled functions when the user opens the
	 *     file. Prefix any such cell with a single quote before quoting.
	 *
	 * @param string $value Raw cell value.
	 *
	 * @return string Escaped cell value.
	 */
	private function escape_csv( string $value ): string {
		if ( '' !== $value && false !== strpbrk( $value[0], "=+-@\t\r" ) ) {
			$value = "'" . $value;
		}

		if ( str_contains( $value, ',' ) || str_contains( $value, '"' ) || str_contains( $value, "\n" ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}

		return $value;
	}
}
