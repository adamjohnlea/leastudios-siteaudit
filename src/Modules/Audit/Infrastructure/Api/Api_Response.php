<?php
/**
 * Parsed PageSpeed Insights response (score + failing audits).
 *
 * @package LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable view over a parsed PageSpeed Insights JSON response.
 *
 * Reads the Lighthouse accessibility category score and rescales it from the
 * API's `[0, 1]` range to `[0, 100]`. Filters Lighthouse audits down to the
 * binary failing ones (`scoreDisplayMode === 'binary' && score < 1`), which
 * is what the application surfaces as "issues".
 */
final class Api_Response {

	/**
	 * Accessibility score in 0–100 (already rescaled from the API's 0–1).
	 *
	 * @var int
	 */
	private readonly int $score;

	/**
	 * Failing audits in a normalised array shape.
	 *
	 * @var array<int, array{id: string, title: string, description: string, score: float|null, scoreDisplayMode: string, helpUrl: string|null, details: array<int, array{selector?: string}>|null}>
	 */
	private readonly array $audits;

	/**
	 * Raw JSON string the response was parsed from (for archival).
	 *
	 * @var string
	 */
	private readonly string $raw_json;

	/**
	 * Constructor.
	 *
	 * @param int                                                                                                                                                                                      $score    Score in 0–100.
	 * @param array<int, array{id: string, title: string, description: string, score: float|null, scoreDisplayMode: string, helpUrl: string|null, details: array<int, array{selector?: string}>|null}> $audits   Failing audits.
	 * @param string                                                                                                                                                                                   $raw_json Raw JSON string.
	 */
	public function __construct( int $score, array $audits, string $raw_json ) {
		$this->score    = $score;
		$this->audits   = $audits;
		$this->raw_json = $raw_json;
	}

	/**
	 * Parse a raw JSON string from the PageSpeed Insights API.
	 *
	 * @param string $json Raw JSON.
	 *
	 * @return self
	 *
	 * @throws \JsonException When the JSON is malformed.
	 */
	public static function from_json( string $json ): self {
		/**
		 * Decoded API payload.
		 *
		 * @var array{lighthouseResult?: array{categories?: array{accessibility?: array{score?: float|int|null}}, audits?: array<string, array{id: string, title: string, description: string, score: float|int|null, scoreDisplayMode: string, helpUrl?: string, details?: array{items?: array<int, array{node?: array{selector?: string}}>}}>}} $data
		 */
		$data = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );

		$score = 0;
		if ( isset( $data['lighthouseResult']['categories']['accessibility']['score'] ) ) {
			$raw_score = $data['lighthouseResult']['categories']['accessibility']['score'];
			$score     = (int) round( $raw_score * 100 );
		}

		$audits     = [];
		$raw_audits = $data['lighthouseResult']['audits'] ?? [];

		foreach ( $raw_audits as $audit ) {
			if ( 'binary' === $audit['scoreDisplayMode'] && null !== $audit['score'] && $audit['score'] < 1 ) {
				$detail_items = [];
				if ( isset( $audit['details']['items'] ) ) {
					foreach ( $audit['details']['items'] as $item ) {
						if ( isset( $item['node']['selector'] ) ) {
							$detail_items[] = [ 'selector' => $item['node']['selector'] ];
						}
					}
				}

				$audits[] = [
					'id'               => $audit['id'],
					'title'            => $audit['title'],
					'description'      => $audit['description'],
					'score'            => (float) $audit['score'],
					'scoreDisplayMode' => $audit['scoreDisplayMode'],
					'helpUrl'          => $audit['helpUrl'] ?? null,
					'details'          => [] !== $detail_items ? $detail_items : null,
				];
			}
		}

		return new self( $score, $audits, $json );
	}

	/**
	 * Accessibility score in 0–100.
	 *
	 * @return int
	 */
	public function score(): int {
		return $this->score;
	}

	/**
	 * Failing audits in a normalised array shape.
	 *
	 * @return array<int, array{id: string, title: string, description: string, score: float|null, scoreDisplayMode: string, helpUrl: string|null, details: array<int, array{selector?: string}>|null}>
	 */
	public function failing_audits(): array {
		return $this->audits;
	}

	/**
	 * Raw JSON string.
	 *
	 * @return string
	 */
	public function raw_json(): string {
		return $this->raw_json;
	}
}
