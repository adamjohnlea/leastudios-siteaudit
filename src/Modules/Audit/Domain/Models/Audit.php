<?php
/**
 * Audit domain model.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Domain\Models
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Domain\Models;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Accessibility_Score;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Run_Strategy;

/**
 * In-memory representation of a row in `{$wpdb->prefix}leastudios_siteaudit_audits`.
 *
 * `id` is `null` until the model is persisted by a repository, after which the
 * repository assigns the auto-increment value via `set_id()`. Issues attached
 * to an audit are loaded eagerly via `set_issues()` after a separate fetch.
 */
final class Audit {

	/**
	 * Issues attached to this audit.
	 *
	 * @var array<int, Issue>
	 */
	private array $issues = [];

	/**
	 * Constructor.
	 *
	 * @param int|null            $id             Auto-increment id, null until persisted.
	 * @param int                 $url_id         Owning URL row id.
	 * @param Accessibility_Score $score          Score VO.
	 * @param Audit_Status        $status         Lifecycle status.
	 * @param Run_Strategy        $strategy       Device profile this audit was run under.
	 * @param \DateTimeImmutable  $audit_date     When the audit was performed.
	 * @param string|null         $raw_response   Raw JSON response (or null if not stored).
	 * @param string|null         $error_message  Error message when status is FAILED.
	 * @param int                 $retry_count    Number of retries that preceded this row.
	 * @param \DateTimeImmutable  $created_at     Insertion timestamp.
	 */
	public function __construct(
		private ?int $id,
		private int $url_id,
		private Accessibility_Score $score,
		private Audit_Status $status,
		private Run_Strategy $strategy,
		private \DateTimeImmutable $audit_date,
		private ?string $raw_response,
		private ?string $error_message,
		private int $retry_count,
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
	 * Owning URL row id.
	 *
	 * @return int
	 */
	public function url_id(): int {
		return $this->url_id;
	}

	/**
	 * Get the score VO.
	 *
	 * @return Accessibility_Score
	 */
	public function score(): Accessibility_Score {
		return $this->score;
	}

	/**
	 * Replace the score.
	 *
	 * @param Accessibility_Score $score Score VO.
	 *
	 * @return void
	 */
	public function set_score( Accessibility_Score $score ): void {
		$this->score = $score;
	}

	/**
	 * Get the lifecycle status.
	 *
	 * @return Audit_Status
	 */
	public function status(): Audit_Status {
		return $this->status;
	}

	/**
	 * Replace the lifecycle status.
	 *
	 * @param Audit_Status $status New status.
	 *
	 * @return void
	 */
	public function set_status( Audit_Status $status ): void {
		$this->status = $status;
	}

	/**
	 * Device profile this audit was run under.
	 *
	 * @return Run_Strategy
	 */
	public function strategy(): Run_Strategy {
		return $this->strategy;
	}

	/**
	 * When the audit was performed.
	 *
	 * @return \DateTimeImmutable
	 */
	public function audit_date(): \DateTimeImmutable {
		return $this->audit_date;
	}

	/**
	 * Raw JSON response (or null when not stored).
	 *
	 * @return string|null
	 */
	public function raw_response(): ?string {
		return $this->raw_response;
	}

	/**
	 * Replace the raw JSON response. Pass null to clear.
	 *
	 * @param string|null $raw_response Raw JSON or null.
	 *
	 * @return void
	 */
	public function set_raw_response( ?string $raw_response ): void {
		$this->raw_response = $raw_response;
	}

	/**
	 * Error message when status is FAILED.
	 *
	 * @return string|null
	 */
	public function error_message(): ?string {
		return $this->error_message;
	}

	/**
	 * Replace the error message. Pass null to clear.
	 *
	 * @param string|null $error_message Error message or null.
	 *
	 * @return void
	 */
	public function set_error_message( ?string $error_message ): void {
		$this->error_message = $error_message;
	}

	/**
	 * Number of retries that preceded this row.
	 *
	 * @return int
	 */
	public function retry_count(): int {
		return $this->retry_count;
	}

	/**
	 * Increment the retry counter by one.
	 *
	 * @return void
	 */
	public function increment_retry_count(): void {
		++$this->retry_count;
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
	 * Issues attached to this audit.
	 *
	 * @return array<int, Issue>
	 */
	public function issues(): array {
		return $this->issues;
	}

	/**
	 * Replace the attached issue list (used after a separate repository fetch).
	 *
	 * @param array<int, Issue> $issues Issues.
	 *
	 * @return void
	 */
	public function set_issues( array $issues ): void {
		$this->issues = $issues;
	}

	/**
	 * Append a single issue.
	 *
	 * @param Issue $issue Issue to append.
	 *
	 * @return void
	 */
	public function add_issue( Issue $issue ): void {
		$this->issues[] = $issue;
	}
}
