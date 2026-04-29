<?php
/**
 * Validated project-name value object.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;

/**
 * Immutable, length-bounded project name.
 *
 * Construction enforces non-empty after trimming and a maximum length matching
 * the `projects.name` column (`varchar(191)` — the WP-safe upper bound for a
 * MySQL utf8mb4 unique key). The 255-char ceiling preserves the source rule;
 * the column itself is the binding constraint.
 */
final class Project_Name implements \Stringable {

	private const MAX_LENGTH = 255;

	/**
	 * Trimmed name.
	 *
	 * @var string
	 */
	private readonly string $value;

	/**
	 * Construct from a raw name string.
	 *
	 * @param string $name Raw name.
	 *
	 * @throws Validation_Exception When the name is empty after trimming or exceeds the max length.
	 */
	public function __construct( string $name ) {
		$name = trim( $name );

		if ( '' === $name ) {
			throw new Validation_Exception( 'Project name cannot be empty' );
		}

		if ( strlen( $name ) > self::MAX_LENGTH ) {
			throw new Validation_Exception( 'Project name must not exceed 255 characters' );
		}

		$this->value = $name;
	}

	/**
	 * Get the trimmed name.
	 *
	 * @return string
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * Compare two instances by their values.
	 *
	 * @param self $other Other instance.
	 *
	 * @return bool
	 */
	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	/**
	 * Allow casting to string.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->value;
	}
}
