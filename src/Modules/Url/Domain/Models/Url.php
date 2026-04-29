<?php
/**
 * URL domain model.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Domain\Models
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Domain\Models;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Url_Address;

/**
 * In-memory representation of a row in `{$wpdb->prefix}leastudios_siteaudit_urls`.
 *
 * `id` is `null` until the model is persisted by a repository, after which the
 * repository assigns the auto-increment value via `set_id()`.
 */
final class Url {

	/**
	 * Constructor.
	 *
	 * @param int|null                $id                     Auto-increment id, null until persisted.
	 * @param int|null                $project_id             Owning project, or null if unassigned.
	 * @param Url_Address             $url                    Validated URL.
	 * @param string|null             $name                   Optional display name.
	 * @param Audit_Frequency         $audit_frequency        Re-audit cadence.
	 * @param Audit_Strategy          $audit_strategy         Desktop/mobile/both selector.
	 * @param bool                    $enabled                Whether the URL participates in scheduled runs.
	 * @param bool                    $alerts_enabled         Whether to fire threshold alert emails.
	 * @param int|null                $alert_threshold_score  Trigger an alert when score drops below this absolute value.
	 * @param int|null                $alert_threshold_drop   Trigger an alert when score drops by this many points.
	 * @param \DateTimeImmutable|null $last_audited_at        Last successful audit, or null if never.
	 * @param \DateTimeImmutable      $created_at             Insertion timestamp.
	 * @param \DateTimeImmutable      $updated_at             Last-update timestamp.
	 */
	public function __construct(
		private ?int $id,
		private ?int $project_id,
		private Url_Address $url,
		private ?string $name,
		private Audit_Frequency $audit_frequency,
		private Audit_Strategy $audit_strategy,
		private bool $enabled,
		private bool $alerts_enabled,
		private ?int $alert_threshold_score,
		private ?int $alert_threshold_drop,
		private ?\DateTimeImmutable $last_audited_at,
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
	 * Get the owning project id, or null if unassigned.
	 *
	 * @return int|null
	 */
	public function project_id(): ?int {
		return $this->project_id;
	}

	/**
	 * Assign or clear the owning project.
	 *
	 * @param int|null $project_id Project id or null.
	 *
	 * @return void
	 */
	public function set_project_id( ?int $project_id ): void {
		$this->project_id = $project_id;
	}

	/**
	 * Get the validated URL.
	 *
	 * @return Url_Address
	 */
	public function url(): Url_Address {
		return $this->url;
	}

	/**
	 * Replace the URL.
	 *
	 * @param Url_Address $url Validated URL.
	 *
	 * @return void
	 */
	public function set_url( Url_Address $url ): void {
		$this->url = $url;
	}

	/**
	 * Get the optional display name.
	 *
	 * @return string|null
	 */
	public function name(): ?string {
		return $this->name;
	}

	/**
	 * Replace the display name. Pass `null` to clear it.
	 *
	 * @param string|null $name Display name.
	 *
	 * @return void
	 */
	public function set_name( ?string $name ): void {
		$this->name = $name;
	}

	/**
	 * Get the re-audit cadence.
	 *
	 * @return Audit_Frequency
	 */
	public function audit_frequency(): Audit_Frequency {
		return $this->audit_frequency;
	}

	/**
	 * Replace the cadence.
	 *
	 * @param Audit_Frequency $audit_frequency Cadence.
	 *
	 * @return void
	 */
	public function set_audit_frequency( Audit_Frequency $audit_frequency ): void {
		$this->audit_frequency = $audit_frequency;
	}

	/**
	 * Get the audit strategy.
	 *
	 * @return Audit_Strategy
	 */
	public function audit_strategy(): Audit_Strategy {
		return $this->audit_strategy;
	}

	/**
	 * Replace the audit strategy.
	 *
	 * @param Audit_Strategy $audit_strategy Strategy.
	 *
	 * @return void
	 */
	public function set_audit_strategy( Audit_Strategy $audit_strategy ): void {
		$this->audit_strategy = $audit_strategy;
	}

	/**
	 * Whether the URL participates in scheduled runs.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Toggle the enabled flag.
	 *
	 * @param bool $enabled Enabled state.
	 *
	 * @return void
	 */
	public function set_enabled( bool $enabled ): void {
		$this->enabled = $enabled;
	}

	/**
	 * Whether threshold alerts are enabled.
	 *
	 * @return bool
	 */
	public function alerts_enabled(): bool {
		return $this->alerts_enabled;
	}

	/**
	 * Toggle the alerts flag.
	 *
	 * @param bool $alerts_enabled Alerts state.
	 *
	 * @return void
	 */
	public function set_alerts_enabled( bool $alerts_enabled ): void {
		$this->alerts_enabled = $alerts_enabled;
	}

	/**
	 * Absolute-score alert threshold (0–100), or null to disable.
	 *
	 * @return int|null
	 */
	public function alert_threshold_score(): ?int {
		return $this->alert_threshold_score;
	}

	/**
	 * Replace the absolute-score alert threshold. Pass `null` to disable it.
	 *
	 * @param int|null $alert_threshold_score Threshold value (0–100) or null.
	 *
	 * @return void
	 */
	public function set_alert_threshold_score( ?int $alert_threshold_score ): void {
		$this->alert_threshold_score = $alert_threshold_score;
	}

	/**
	 * Score-drop alert threshold (1–100), or null to disable.
	 *
	 * @return int|null
	 */
	public function alert_threshold_drop(): ?int {
		return $this->alert_threshold_drop;
	}

	/**
	 * Replace the score-drop alert threshold. Pass `null` to disable it.
	 *
	 * @param int|null $alert_threshold_drop Threshold value (1–100) or null.
	 *
	 * @return void
	 */
	public function set_alert_threshold_drop( ?int $alert_threshold_drop ): void {
		$this->alert_threshold_drop = $alert_threshold_drop;
	}

	/**
	 * Timestamp of the most recent successful audit, or null if never audited.
	 *
	 * @return \DateTimeImmutable|null
	 */
	public function last_audited_at(): ?\DateTimeImmutable {
		return $this->last_audited_at;
	}

	/**
	 * Update the last-audited timestamp.
	 *
	 * @param \DateTimeImmutable|null $last_audited_at Timestamp or null to clear.
	 *
	 * @return void
	 */
	public function set_last_audited_at( ?\DateTimeImmutable $last_audited_at ): void {
		$this->last_audited_at = $last_audited_at;
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
	 * Last-updated timestamp.
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
