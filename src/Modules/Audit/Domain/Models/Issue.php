<?php
/**
 * Issue domain model.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Domain\Models
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Domain\Models;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Category;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Severity;

/**
 * In-memory representation of a row in `{$wpdb->prefix}leastudios_siteaudit_issues`.
 *
 * Each row belongs to exactly one audit and represents a single failing
 * Lighthouse audit, optionally pinned to a specific DOM selector.
 */
final class Issue {

	/**
	 * Constructor.
	 *
	 * @param int|null           $id               Auto-increment id, null until persisted.
	 * @param int                $audit_id         Owning audit row id.
	 * @param Issue_Severity     $severity         Severity classification.
	 * @param Issue_Category     $category         Category bucket.
	 * @param string             $description      Description text (also used for diff identity in comparisons).
	 * @param string|null        $element_selector CSS selector for the offending DOM node, if known.
	 * @param string|null        $help_url         Lighthouse documentation URL, if any.
	 * @param \DateTimeImmutable $created_at       Insertion timestamp.
	 * @param string|null        $title            Optional human-readable title.
	 */
	public function __construct(
		private ?int $id,
		private int $audit_id,
		private Issue_Severity $severity,
		private Issue_Category $category,
		private string $description,
		private ?string $element_selector,
		private ?string $help_url,
		private \DateTimeImmutable $created_at,
		private ?string $title = null,
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
	 * Owning audit row id.
	 *
	 * @return int
	 */
	public function audit_id(): int {
		return $this->audit_id;
	}

	/**
	 * Severity classification.
	 *
	 * @return Issue_Severity
	 */
	public function severity(): Issue_Severity {
		return $this->severity;
	}

	/**
	 * Category bucket.
	 *
	 * @return Issue_Category
	 */
	public function category(): Issue_Category {
		return $this->category;
	}

	/**
	 * Description text (used for diff identity in comparisons).
	 *
	 * @return string
	 */
	public function description(): string {
		return $this->description;
	}

	/**
	 * CSS selector for the offending DOM node, if known.
	 *
	 * @return string|null
	 */
	public function element_selector(): ?string {
		return $this->element_selector;
	}

	/**
	 * Lighthouse documentation URL, if any.
	 *
	 * @return string|null
	 */
	public function help_url(): ?string {
		return $this->help_url;
	}

	/**
	 * Insertion timestamp.
	 *
	 * @return \DateTimeImmutable
	 */
	public function created_at(): \DateTimeImmutable {
		return $this->created_at;
	}

	/**
	 * Optional human-readable title.
	 *
	 * @return string|null
	 */
	public function title(): ?string {
		return $this->title;
	}
}
