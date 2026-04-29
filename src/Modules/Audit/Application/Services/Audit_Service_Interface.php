<?php
/**
 * Audit application-service contract.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit;

/**
 * Triggers a fresh audit run for a given URL.
 *
 * Implementations are expected to invoke the PageSpeed API once per applicable
 * strategy (desktop / mobile / both) and persist the result rows. Returns
 * one Audit per strategy executed.
 */
interface Audit_Service_Interface {

	/**
	 * Run audits for the given URL.
	 *
	 * @param int $url_id URL id.
	 *
	 * @return array<int, Audit>
	 *
	 * @throws \LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception When the URL does not exist.
	 */
	public function run_audit( int $url_id ): array;
}
