<?php
/**
 * Exponential-backoff retry policy for rate-limited API calls.
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Infrastructure\RateLimiting
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Infrastructure\RateLimiting;

defined( 'ABSPATH' ) || exit;

/**
 * Pure-function retry policy: how many attempts and how long to wait between.
 *
 * Delays double each attempt (exponential backoff) starting from the configured
 * base delay, capped at the configured maximum. Stateless — the attempt counter
 * is owned by the caller.
 */
final class Retry_Strategy {

	/**
	 * Maximum number of retries (in addition to the initial attempt).
	 *
	 * @var int
	 */
	private readonly int $max_retries;

	/**
	 * Base delay in milliseconds (used for attempt 0).
	 *
	 * @var int
	 */
	private readonly int $base_delay_ms;

	/**
	 * Maximum delay in milliseconds (cap for the doubled value).
	 *
	 * @var int
	 */
	private readonly int $max_delay_ms;

	/**
	 * Constructor.
	 *
	 * @param int $max_retries   Maximum retries.
	 * @param int $base_delay_ms Base delay in ms.
	 * @param int $max_delay_ms  Max delay in ms.
	 */
	public function __construct(
		int $max_retries = 3,
		int $base_delay_ms = 1000,
		// Cap at 5s rather than 30s so a backoff sleep does not block an
		// Action Scheduler worker for tens of seconds. AS workers are
		// loopback HTTP requests and a stalled worker stalls the queue;
		// 5s × max_retries is the most one URL can hold a worker.
		int $max_delay_ms = 5000
	) {
		$this->max_retries   = $max_retries;
		$this->base_delay_ms = $base_delay_ms;
		$this->max_delay_ms  = $max_delay_ms;
	}

	/**
	 * Whether the caller should retry given the current attempt number (0-based).
	 *
	 * @param int $attempt Attempt number, where 0 means "no retries yet".
	 *
	 * @return bool
	 */
	public function should_retry( int $attempt ): bool {
		return $attempt < $this->max_retries;
	}

	/**
	 * Delay in milliseconds before attempt `$attempt`, with exponential backoff capped at `max_delay_ms`.
	 *
	 * @param int $attempt Attempt number (0-based).
	 *
	 * @return int
	 */
	public function delay_ms( int $attempt ): int {
		$delay = $this->base_delay_ms * ( 2 ** $attempt );

		return min( $delay, $this->max_delay_ms );
	}

	/**
	 * Configured maximum number of retries.
	 *
	 * @return int
	 */
	public function max_retries(): int {
		return $this->max_retries;
	}
}
