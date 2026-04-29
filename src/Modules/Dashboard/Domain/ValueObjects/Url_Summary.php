<?php
/**
 * Per-URL display summary for project / dashboard views.
 *
 * @package LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only display projection of a URL plus the latest audit numbers.
 *
 * `latest_score` is the most recent score across any strategy; the per-strategy
 * fields (`latest_desktop_score` / `latest_mobile_score`) are pulled from the
 * latest audit for each strategy independently. All score fields are nullable
 * because a URL may not have been audited yet (or only one of the two
 * strategies has run).
 */
final class Url_Summary {

	/**
	 * URL row id.
	 *
	 * @var int
	 */
	private int $url_id;

	/**
	 * Display name (falls back to address).
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * URL address.
	 *
	 * @var string
	 */
	private string $address;

	/**
	 * Latest overall score (0-100), or null when no audits exist.
	 *
	 * @var int|null
	 */
	private ?int $latest_score;

	/**
	 * Total audits recorded for this URL.
	 *
	 * @var int
	 */
	private int $total_audits;

	/**
	 * Audit-frequency label.
	 *
	 * @var string
	 */
	private string $frequency;

	/**
	 * Whether the URL is enabled for scheduled audits.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Latest desktop-strategy score (0-100), or null when not yet run.
	 *
	 * @var int|null
	 */
	private ?int $latest_desktop_score;

	/**
	 * Latest mobile-strategy score (0-100), or null when not yet run.
	 *
	 * @var int|null
	 */
	private ?int $latest_mobile_score;

	/**
	 * Constructor.
	 *
	 * @param int      $url_id               URL row id.
	 * @param string   $name                 Display name.
	 * @param string   $address              URL address.
	 * @param int|null $latest_score         Latest overall score, or null.
	 * @param int      $total_audits         Audit count.
	 * @param string   $frequency            Frequency label.
	 * @param bool     $enabled              Whether the URL is enabled.
	 * @param int|null $latest_desktop_score Latest desktop score, or null.
	 * @param int|null $latest_mobile_score  Latest mobile score, or null.
	 */
	public function __construct(
		int $url_id,
		string $name,
		string $address,
		?int $latest_score,
		int $total_audits,
		string $frequency,
		bool $enabled,
		?int $latest_desktop_score = null,
		?int $latest_mobile_score = null
	) {
		$this->url_id               = $url_id;
		$this->name                 = $name;
		$this->address              = $address;
		$this->latest_score         = $latest_score;
		$this->total_audits         = $total_audits;
		$this->frequency            = $frequency;
		$this->enabled              = $enabled;
		$this->latest_desktop_score = $latest_desktop_score;
		$this->latest_mobile_score  = $latest_mobile_score;
	}

	/**
	 * URL row id.
	 *
	 * @return int
	 */
	public function url_id(): int {
		return $this->url_id;
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * URL address.
	 *
	 * @return string
	 */
	public function address(): string {
		return $this->address;
	}

	/**
	 * Latest overall score, or null.
	 *
	 * @return int|null
	 */
	public function latest_score(): ?int {
		return $this->latest_score;
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
	 * Frequency label.
	 *
	 * @return string
	 */
	public function frequency(): string {
		return $this->frequency;
	}

	/**
	 * Whether the URL is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Latest desktop score, or null.
	 *
	 * @return int|null
	 */
	public function latest_desktop_score(): ?int {
		return $this->latest_desktop_score;
	}

	/**
	 * Latest mobile score, or null.
	 *
	 * @return int|null
	 */
	public function latest_mobile_score(): ?int {
		return $this->latest_mobile_score;
	}

	/**
	 * Letter grade derived from the latest overall score, or "N/A" when null.
	 *
	 * @return string
	 */
	public function score_grade(): string {
		if ( null === $this->latest_score ) {
			return 'N/A';
		}

		return match ( true ) {
			$this->latest_score >= 90 => 'A',
			$this->latest_score >= 70 => 'B',
			$this->latest_score >= 50 => 'C',
			default                   => 'F',
		};
	}
}
