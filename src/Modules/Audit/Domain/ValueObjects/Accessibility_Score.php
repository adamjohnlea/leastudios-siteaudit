<?php
/**
 * Bounded accessibility score (0–100).
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;

/**
 * Immutable accessibility score, constrained to the inclusive range 0–100.
 *
 * The PageSpeed Insights API returns a fractional score in [0, 1] which the
 * plugin scales to a percentage on ingest; this VO models that scaled value.
 */
final class Accessibility_Score {

	/**
	 * Score value in 0–100.
	 *
	 * @var int
	 */
	private readonly int $value;

	/**
	 * Construct from a raw int.
	 *
	 * @param int $value Score in 0–100.
	 *
	 * @throws Validation_Exception When the value is out of range.
	 */
	public function __construct( int $value ) {
		if ( $value < 0 || $value > 100 ) {
			throw new Validation_Exception( 'Score must be between 0 and 100' );
		}

		$this->value = $value;
	}

	/**
	 * Get the raw score value.
	 *
	 * @return int
	 */
	public function value(): int {
		return $this->value;
	}

	/**
	 * Strict greater-than comparison.
	 *
	 * @param self $other Other instance.
	 *
	 * @return bool
	 */
	public function is_greater_than( self $other ): bool {
		return $this->value > $other->value;
	}

	/**
	 * Equality comparison.
	 *
	 * @param self $other Other instance.
	 *
	 * @return bool
	 */
	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	/**
	 * Signed delta vs. a previous score (`$this - $previous`).
	 *
	 * @param self $previous Previous instance.
	 *
	 * @return int
	 */
	public function delta( self $previous ): int {
		return $this->value - $previous->value;
	}

	/**
	 * Human-readable grade band (Excellent / Good / Needs Improvement / Poor).
	 *
	 * @return string
	 */
	public function grade(): string {
		return match ( true ) {
			$this->value >= 90 => 'Excellent',
			$this->value >= 70 => 'Good',
			$this->value >= 50 => 'Needs Improvement',
			default            => 'Poor',
		};
	}
}
