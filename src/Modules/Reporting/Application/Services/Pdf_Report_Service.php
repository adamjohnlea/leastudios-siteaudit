<?php
/**
 * Renders a project report VO into a PDF byte string via Dompdf.
 *
 * @package LEAStudios\SiteAudit\Modules\Reporting\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Reporting\Application\Services;

defined( 'ABSPATH' ) || exit;

use Dompdf\Dompdf;
use Dompdf\Options;
use LEAStudios\SiteAudit\Modules\Reporting\Domain\ValueObjects\Project_Report_Data;

/**
 * Thin wrapper over Dompdf. Loads the project-PDF PHP partial into an output
 * buffer (the partial is real WordPress-style PHP, not a Twig template),
 * hands the resulting HTML string to Dompdf, and returns the rendered PDF
 * as a binary string.
 *
 * Configuration mirrors the source app:
 *   - `setIsRemoteEnabled(false)` — never fetch external URLs (XSS / SSRF guard).
 *   - `setDefaultFont('Helvetica')` — bundled with Dompdf, always available.
 *   - A4 portrait — matches the source app's stationery.
 */
final class Pdf_Report_Service implements Pdf_Report_Service_Interface {

	/**
	 * Generate a PDF from a project report VO.
	 *
	 * @param Project_Report_Data $report Project report data.
	 *
	 * @return string Raw PDF byte string.
	 */
	public function generate( Project_Report_Data $report ): string {
		$html = $this->render_template( $report );

		$options = new Options();
		$options->setIsRemoteEnabled( false );
		$options->setDefaultFont( 'Helvetica' );

		$dompdf = new Dompdf( $options );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		return (string) $dompdf->output();
	}

	/**
	 * Capture the project-PDF template's output as an HTML string.
	 *
	 * @param Project_Report_Data $leastudios_siteaudit_report Project report data.
	 *
	 * @return string
	 */
	private function render_template( Project_Report_Data $leastudios_siteaudit_report ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $leastudios_siteaudit_report is consumed by the included template via its lexical scope.
		$file = LEASTUDIOS_SITEAUDIT_DIR . 'templates/reports/project-pdf.php';

		ob_start();
		include $file;
		$html = ob_get_clean();

		return false === $html ? '' : (string) $html;
	}
}
