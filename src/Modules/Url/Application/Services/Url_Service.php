<?php
/**
 * URL application service.
 *
 * @package LEAStudios\SiteAudit\Modules\Url\Application\Services
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Modules\Url\Application\Services;

defined( 'ABSPATH' ) || exit;

use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\Repositories\Url_Repository_Interface;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Url_Address;
use LEAStudios\SiteAudit\Shared\Datetime_Util;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;

/**
 * Application-layer orchestrator for URL lifecycle operations.
 *
 * Wraps the repository with uniqueness checks, enum coercion, and threshold
 * validation so controllers can pass raw form input.
 */
final class Url_Service {

	/**
	 * URL persistence boundary.
	 *
	 * @var Url_Repository_Interface
	 */
	private Url_Repository_Interface $url_repository;

	/**
	 * Constructor.
	 *
	 * @param Url_Repository_Interface $url_repository Repository implementation.
	 */
	public function __construct( Url_Repository_Interface $url_repository ) {
		$this->url_repository = $url_repository;
	}

	/**
	 * Create and persist a new URL.
	 *
	 * @param string   $url                     Raw URL.
	 * @param string   $name                    Display name.
	 * @param string   $frequency               Frequency enum value.
	 * @param int|null $project_id              Owning project id, or null.
	 * @param string   $audit_strategy          Strategy enum value.
	 * @param bool     $alerts_enabled          Whether to fire threshold alerts.
	 * @param int|null $alert_threshold_score   Absolute-score threshold (0–100), or null.
	 * @param int|null $alert_threshold_drop    Score-drop threshold (1–100), or null.
	 *
	 * @return Url
	 *
	 * @throws Validation_Exception When inputs fail validation or the URL already exists.
	 */
	public function create(
		string $url,
		string $name,
		string $frequency,
		?int $project_id = null,
		string $audit_strategy = 'both',
		bool $alerts_enabled = false,
		?int $alert_threshold_score = null,
		?int $alert_threshold_drop = null
	): Url {
		$url_address = new Url_Address( $url );

		$existing = $this->url_repository->find_by_url( $url_address->value() );
		if ( null !== $existing ) {
			throw new Validation_Exception( 'This URL has already been added.' );
		}

		$audit_frequency   = $this->resolve_frequency( $frequency );
		$resolved_strategy = $this->resolve_audit_strategy( $audit_strategy );
		$this->validate_alert_thresholds( $alert_threshold_score, $alert_threshold_drop );
		$now = Datetime_Util::now();

		$url_model = new Url(
			null,
			$project_id,
			$url_address,
			$name,
			$audit_frequency,
			$resolved_strategy,
			true,
			$alerts_enabled,
			$alert_threshold_score,
			$alert_threshold_drop,
			null,
			$now,
			$now,
		);

		return $this->url_repository->save( $url_model );
	}

	/**
	 * Update an existing URL. Pass `null` for fields that should remain unchanged.
	 *
	 * @param int         $id                            URL id.
	 * @param string|null $name                          New display name, or null to leave unchanged.
	 * @param string|null $frequency                     New frequency enum value, or null to leave unchanged.
	 * @param string|null $audit_strategy                New strategy enum value, or null to leave unchanged.
	 * @param bool|null   $enabled                       New enabled state, or null to leave unchanged.
	 * @param int|null    $project_id                    New owning project id, or null to leave unchanged.
	 * @param bool|null   $alerts_enabled                New alerts state, or null to leave unchanged.
	 * @param int|null    $alert_threshold_score         New absolute-score threshold, or null to leave unchanged.
	 * @param bool        $clear_alert_threshold_score   When true, sets score threshold to null regardless of `$alert_threshold_score`.
	 * @param int|null    $alert_threshold_drop          New score-drop threshold, or null to leave unchanged.
	 * @param bool        $clear_alert_threshold_drop    When true, sets drop threshold to null regardless of `$alert_threshold_drop`.
	 *
	 * @return Url
	 *
	 * @throws Validation_Exception When the URL is missing or inputs fail validation.
	 */
	public function update(
		int $id,
		?string $name = null,
		?string $frequency = null,
		?string $audit_strategy = null,
		?bool $enabled = null,
		?int $project_id = null,
		?bool $alerts_enabled = null,
		?int $alert_threshold_score = null,
		bool $clear_alert_threshold_score = false,
		?int $alert_threshold_drop = null,
		bool $clear_alert_threshold_drop = false
	): Url {
		$url = $this->url_repository->find_by_id( $id );

		if ( null === $url ) {
			throw new Validation_Exception( 'URL not found' );
		}

		if ( null !== $name ) {
			$url->set_name( $name );
		}

		if ( null !== $frequency ) {
			$url->set_audit_frequency( $this->resolve_frequency( $frequency ) );
		}

		if ( null !== $audit_strategy ) {
			$url->set_audit_strategy( $this->resolve_audit_strategy( $audit_strategy ) );
		}

		if ( null !== $enabled ) {
			$url->set_enabled( $enabled );
		}

		if ( null !== $project_id ) {
			$url->set_project_id( $project_id );
		}

		if ( null !== $alerts_enabled ) {
			$url->set_alerts_enabled( $alerts_enabled );
		}

		$resolved_threshold_score = $clear_alert_threshold_score
			? null
			: ( $alert_threshold_score ?? $url->alert_threshold_score() );
		$resolved_threshold_drop  = $clear_alert_threshold_drop
			? null
			: ( $alert_threshold_drop ?? $url->alert_threshold_drop() );
		$this->validate_alert_thresholds( $resolved_threshold_score, $resolved_threshold_drop );
		$url->set_alert_threshold_score( $resolved_threshold_score );
		$url->set_alert_threshold_drop( $resolved_threshold_drop );

		$url->set_updated_at( Datetime_Util::now() );

		return $this->url_repository->update( $url );
	}

	/**
	 * Delete a URL.
	 *
	 * @param int $id URL id.
	 *
	 * @return void
	 *
	 * @throws Validation_Exception When the URL is missing.
	 */
	public function delete( int $id ): void {
		$url = $this->url_repository->find_by_id( $id );

		if ( null === $url ) {
			throw new Validation_Exception( 'URL not found' );
		}

		$this->url_repository->delete( $id );
	}

	/**
	 * Find a URL by id.
	 *
	 * @param int $id URL id.
	 *
	 * @return Url|null
	 */
	public function find_by_id( int $id ): ?Url {
		return $this->url_repository->find_by_id( $id );
	}

	/**
	 * List all URLs, ordered by name ascending.
	 *
	 * @return array<int, Url>
	 */
	public function find_all(): array {
		return $this->url_repository->find_all();
	}

	/**
	 * Paginated list-with-search.
	 *
	 * @param int    $page     1-indexed page number.
	 * @param int    $per_page Page size.
	 * @param string $search   Substring matched against url and name.
	 *
	 * @return array<int, Url>
	 */
	public function find_paginated( int $page, int $per_page, string $search = '' ): array {
		return $this->url_repository->find_paginated( $page, $per_page, $search );
	}

	/**
	 * Count rows matching an optional search.
	 *
	 * @param string $search Substring matched against url and name.
	 *
	 * @return int
	 */
	public function count_for_search( string $search = '' ): int {
		return $this->url_repository->count_for_search( $search );
	}

	/**
	 * Coerce a string value to an `Audit_Strategy`.
	 *
	 * @param string $strategy Strategy enum value.
	 *
	 * @return Audit_Strategy
	 *
	 * @throws Validation_Exception When the value does not map to a case.
	 */
	private function resolve_audit_strategy( string $strategy ): Audit_Strategy {
		$resolved = Audit_Strategy::tryFrom( $strategy );

		if ( null === $resolved ) {
			throw new Validation_Exception(
				sprintf( 'Invalid audit strategy: %s', esc_html( $strategy ) )
			);
		}

		return $resolved;
	}

	/**
	 * Validate alert threshold ranges.
	 *
	 * @param int|null $alert_threshold_score Score threshold (0–100), or null.
	 * @param int|null $alert_threshold_drop  Drop threshold (1–100), or null.
	 *
	 * @return void
	 *
	 * @throws Validation_Exception When either threshold is out of range.
	 */
	private function validate_alert_thresholds( ?int $alert_threshold_score, ?int $alert_threshold_drop ): void {
		if ( null !== $alert_threshold_score && ( $alert_threshold_score < 0 || $alert_threshold_score > 100 ) ) {
			throw new Validation_Exception( 'Alert threshold score must be between 0 and 100.' );
		}

		if ( null !== $alert_threshold_drop && ( $alert_threshold_drop < 1 || $alert_threshold_drop > 100 ) ) {
			throw new Validation_Exception( 'Alert threshold drop must be between 1 and 100.' );
		}
	}

	/**
	 * Coerce a string value to an `Audit_Frequency`.
	 *
	 * @param string $frequency Frequency enum value.
	 *
	 * @return Audit_Frequency
	 *
	 * @throws Validation_Exception When the value does not map to a case.
	 */
	private function resolve_frequency( string $frequency ): Audit_Frequency {
		$audit_frequency = Audit_Frequency::tryFrom( $frequency );

		if ( null === $audit_frequency ) {
			throw new Validation_Exception(
				sprintf( 'Invalid audit frequency: %s', esc_html( $frequency ) )
			);
		}

		return $audit_frequency;
	}
}
