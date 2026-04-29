<?php
/**
 * Project domain model.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Domain\Models
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Domain\Models;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Project_Name;

/**
 * In-memory representation of a row in `{$wpdb->prefix}leastudios_siteaudit_projects`.
 *
 * `id` is `null` until the model is persisted by a repository, after which the
 * repository assigns the auto-increment value via `set_id()`.
 */
final class Project {

	/**
	 * Constructor.
	 *
	 * @param int|null           $id          Auto-increment id, null until persisted.
	 * @param Project_Name       $name        Validated name.
	 * @param string|null        $description Free-text description.
	 * @param \DateTimeImmutable $created_at  Insertion timestamp.
	 * @param \DateTimeImmutable $updated_at  Last-update timestamp.
	 */
	public function __construct(
		private ?int $id,
		private Project_Name $name,
		private ?string $description,
		private \DateTimeImmutable $created_at,
		private \DateTimeImmutable $updated_at,
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
	 * Get the validated name.
	 *
	 * @return Project_Name
	 */
	public function name(): Project_Name {
		return $this->name;
	}

	/**
	 * Replace the name.
	 *
	 * @param Project_Name $name Validated name.
	 *
	 * @return void
	 */
	public function set_name( Project_Name $name ): void {
		$this->name = $name;
	}

	/**
	 * Get the free-text description.
	 *
	 * @return string|null
	 */
	public function description(): ?string {
		return $this->description;
	}

	/**
	 * Replace the description. Pass `null` to clear it.
	 *
	 * @param string|null $description Free-text description.
	 *
	 * @return void
	 */
	public function set_description( ?string $description ): void {
		$this->description = $description;
	}

	/**
	 * Get the creation timestamp.
	 *
	 * @return \DateTimeImmutable
	 */
	public function created_at(): \DateTimeImmutable {
		return $this->created_at;
	}

	/**
	 * Get the last-updated timestamp.
	 *
	 * @return \DateTimeImmutable
	 */
	public function updated_at(): \DateTimeImmutable {
		return $this->updated_at;
	}

	/**
	 * Replace the last-updated timestamp.
	 *
	 * @param \DateTimeImmutable $updated_at New timestamp.
	 *
	 * @return void
	 */
	public function set_updated_at( \DateTimeImmutable $updated_at ): void {
		$this->updated_at = $updated_at;
	}
}
