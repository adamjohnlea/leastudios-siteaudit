<?php
/**
 * Audit comparison domain model.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Domain\Models
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Domain\Models;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Score_Delta;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Trend;

/**
 * In-memory representation of a row in `{$wpdb->prefix}leastudios_siteaudit_audit_comparisons`.
 *
 * Captures the relationship between a current audit and the previous audit for
 * the same URL: score delta, issue diff counts, and trend classification.
 */
final class Audit_Comparison {

	/**
	 * Constructor.
	 *
	 * @param int|null           $id                       Auto-increment id, null until persisted.
	 * @param int                $current_audit_id         Newer audit being summarised.
	 * @param int                $previous_audit_id        Older audit it is compared against.
	 * @param Score_Delta        $score_delta              Signed score delta VO.
	 * @param int                $new_issues_count         Issues present in current but not previous.
	 * @param int                $resolved_issues_count    Issues present in previous but not current.
	 * @param int                $persistent_issues_count  Issues present in both.
	 * @param Trend              $trend                    Trend classifier derived from delta.
	 * @param \DateTimeImmutable $created_at               Insertion timestamp.
	 */
	public function __construct(
		private ?int $id,
		private int $current_audit_id,
		private int $previous_audit_id,
		private Score_Delta $score_delta,
		private int $new_issues_count,
		private int $resolved_issues_count,
		private int $persistent_issues_count,
		private Trend $trend,
		private \DateTimeImmutable $created_at,
	) {
	}

	/**
	 * Get the row id, or `null` if not yet persisted.
	 *
	 * @return int|null
	 */
	public function id(): ?int {
		return $this->id;
	}

	/**
	 * Assign the row id after a successful insert.
	 *
	 * @param int $id Row id.
	 *
	 * @return void
	 */
	public function set_id( int $id ): void {
		$this->id = $id;
	}

	/**
	 * Newer audit being summarised.
	 *
	 * @return int
	 */
	public function current_audit_id(): int {
		return $this->current_audit_id;
	}

	/**
	 * Older audit it is compared against.
	 *
	 * @return int
	 */
	public function previous_audit_id(): int {
		return $this->previous_audit_id;
	}

	/**
	 * Signed score delta VO.
	 *
	 * @return Score_Delta
	 */
	public function score_delta(): Score_Delta {
		return $this->score_delta;
	}

	/**
	 * Issues present in current but not previous.
	 *
	 * @return int
	 */
	public function new_issues_count(): int {
		return $this->new_issues_count;
	}

	/**
	 * Issues present in previous but not current.
	 *
	 * @return int
	 */
	public function resolved_issues_count(): int {
		return $this->resolved_issues_count;
	}

	/**
	 * Issues present in both.
	 *
	 * @return int
	 */
	public function persistent_issues_count(): int {
		return $this->persistent_issues_count;
	}

	/**
	 * Trend classifier derived from delta.
	 *
	 * @return Trend
	 */
	public function trend(): Trend {
		return $this->trend;
	}

	/**
	 * Insertion timestamp.
	 *
	 * @return \DateTimeImmutable
	 */
	public function created_at(): \DateTimeImmutable {
		return $this->created_at;
	}
}
