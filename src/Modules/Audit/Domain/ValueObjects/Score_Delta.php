<?php
/**
 * Signed score delta between two audits.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps a signed integer change in score (current minus previous).
 *
 * Positive values indicate improvement, negative values indicate degradation,
 * zero indicates a stable score. Persisted in `audit_comparisons.score_delta`.
 */
final class Score_Delta {

	/**
	 * Signed integer delta.
	 *
	 * @var int
	 */
	private readonly int $value;

	/**
	 * Constructor.
	 *
	 * @param int $value Signed delta.
	 */
	public function __construct( int $value ) {
		$this->value = $value;
	}

	/**
	 * Get the raw signed value.
	 *
	 * @return int
	 */
	public function value(): int {
		return $this->value;
	}

	/**
	 * Whether the delta indicates an improvement (`> 0`).
	 *
	 * @return bool
	 */
	public function is_improvement(): bool {
		return $this->value > 0;
	}

	/**
	 * Whether the delta indicates a degradation (`< 0`).
	 *
	 * @return bool
	 */
	public function is_degradation(): bool {
		return $this->value < 0;
	}

	/**
	 * Whether the delta is exactly zero.
	 *
	 * @return bool
	 */
	public function is_stable(): bool {
		return 0 === $this->value;
	}

	/**
	 * Absolute magnitude of the delta.
	 *
	 * @return int
	 */
	public function absolute_value(): int {
		return abs( $this->value );
	}

	/**
	 * Human-readable signed label (e.g., "+10", "-5", "0").
	 *
	 * @return string
	 */
	public function direction_label(): string {
		if ( $this->value > 0 ) {
			return '+' . $this->value;
		}

		return (string) $this->value;
	}
}
