<?php
/**
 * Aggregate dashboard metrics for one project (or unassigned URLs).
 *
 * @package LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

/**
 * Score-distribution buckets and totals computed across a set of URLs.
 *
 * The four distribution keys (`excellent` / `good` / `needs_work` / `poor`)
 * are the same brackets the source app uses; `urls_needing_attention` counts
 * URLs whose latest score is below 70.
 */
final class Dashboard_Summary {

	/**
	 * Total URLs in the set.
	 *
	 * @var int
	 */
	private int $total_urls;

	/**
	 * Total audits run across the set.
	 *
	 * @var int
	 */
	private int $total_audits;

	/**
	 * Mean of latest scores across URLs that have been audited.
	 *
	 * @var int
	 */
	private int $average_score;

	/**
	 * Count of URLs whose latest score is below 70.
	 *
	 * @var int
	 */
	private int $urls_needing_attention;

	/**
	 * Distribution of latest scores into four brackets.
	 *
	 * @var array{excellent: int, good: int, needs_work: int, poor: int}
	 */
	private array $score_distribution;

	/**
	 * Constructor.
	 *
	 * @param int                                                          $total_urls             Total URL count.
	 * @param int                                                          $total_audits           Total audit count.
	 * @param int                                                          $average_score          Mean latest score (0-100).
	 * @param int                                                          $urls_needing_attention URLs with latest score < 70.
	 * @param array{excellent: int, good: int, needs_work: int, poor: int} $score_distribution     Bracket counts.
	 */
	public function __construct(
		int $total_urls,
		int $total_audits,
		int $average_score,
		int $urls_needing_attention,
		array $score_distribution
	) {
		$this->total_urls             = $total_urls;
		$this->total_audits           = $total_audits;
		$this->average_score          = $average_score;
		$this->urls_needing_attention = $urls_needing_attention;
		$this->score_distribution     = $score_distribution;
	}

	/**
	 * Total URLs.
	 *
	 * @return int
	 */
	public function total_urls(): int {
		return $this->total_urls;
	}

	/**
	 * Total audits.
	 *
	 * @return int
	 */
	public function total_audits(): int {
		return $this->total_audits;
	}

	/**
	 * Mean latest score across audited URLs.
	 *
	 * @return int
	 */
	public function average_score(): int {
		return $this->average_score;
	}

	/**
	 * URLs whose latest score is below 70.
	 *
	 * @return int
	 */
	public function urls_needing_attention(): int {
		return $this->urls_needing_attention;
	}

	/**
	 * Score-bracket counts.
	 *
	 * @return array{excellent: int, good: int, needs_work: int, poor: int}
	 */
	public function score_distribution(): array {
		return $this->score_distribution;
	}
}
