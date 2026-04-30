<?php
/**
 * PDF report data collector contract.
 *
 * @package LEAStudios\SiteAudit\Modules\Reporting\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Reporting\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Reporting\Domain\ValueObjects\Project_Report_Data;
use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project;

/**
 * Builds the structured `Project_Report_Data` consumed by the PDF template
 * and the report email body. Extracted as an interface so notifiers can be
 * unit-tested against a mock without hauling in the dashboard / repos.
 */
interface Pdf_Report_Data_Collector_Interface {

	/**
	 * Collect everything needed to render a project's PDF.
	 *
	 * @param Project $project Project being reported on.
	 *
	 * @return Project_Report_Data
	 */
	public function collect( Project $project ): Project_Report_Data;
}
