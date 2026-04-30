<?php
/**
 * PDF rendering service contract.
 *
 * @package LEAStudios\SiteAudit\Modules\Reporting\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Reporting\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Reporting\Domain\ValueObjects\Project_Report_Data;

/**
 * Renders a `Project_Report_Data` VO into raw PDF bytes. Extracted as an
 * interface so callers (the report notifier, the controller) can be mocked
 * without instantiating Dompdf.
 */
interface Pdf_Report_Service_Interface {

	/**
	 * Generate a PDF document from a project report VO.
	 *
	 * @param Project_Report_Data $report Project report data.
	 *
	 * @return string Raw PDF bytes.
	 */
	public function generate( Project_Report_Data $report ): string;
}
