<?php
/**
 * Wpdb_Url_Repository integration tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Repositories;

use LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Url_Address;
use LEAStudios\SiteAudit\Modules\Url\Infrastructure\Repositories\Wpdb_Url_Repository;
use LEAStudios\Tests\TestCase;

final class Wpdb_Url_Repository_Test extends TestCase {

	private Wpdb_Url_Repository $repository;

	public function set_up(): void {
		parent::set_up();
		$this->repository = new Wpdb_Url_Repository();
	}

	public function test_save_round_trips_all_fields(): void {
		$now = new \DateTimeImmutable( '2026-01-15 12:00:00' );

		$saved = $this->repository->save(
			new Url(
				null,
				42,
				new Url_Address( 'https://example.com/path' ),
				'Homepage',
				Audit_Frequency::DAILY,
				Audit_Strategy::MOBILE,
				true,
				true,
				90,
				10,
				null,
				$now,
				$now,
			)
		);

		$this->assertNotNull( $saved->id() );

		$found = $this->repository->find_by_id( (int) $saved->id() );
		$this->assertNotNull( $found );
		$this->assertSame( 42, $found->project_id() );
		$this->assertSame( 'https://example.com/path', $found->url()->value() );
		$this->assertSame( 'Homepage', $found->name() );
		$this->assertSame( Audit_Frequency::DAILY, $found->audit_frequency() );
		$this->assertSame( Audit_Strategy::MOBILE, $found->audit_strategy() );
		$this->assertTrue( $found->is_enabled() );
		$this->assertTrue( $found->alerts_enabled() );
		$this->assertSame( 90, $found->alert_threshold_score() );
		$this->assertSame( 10, $found->alert_threshold_drop() );
		$this->assertNull( $found->last_audited_at() );
	}

	public function test_find_by_url_returns_match_or_null(): void {
		$this->repository->save( $this->new_url( 'https://example.com', 'A' ) );

		$this->assertNotNull( $this->repository->find_by_url( 'https://example.com' ) );
		$this->assertNull( $this->repository->find_by_url( 'https://missing.com' ) );
	}

	public function test_find_by_project_id_filters_correctly(): void {
		$this->repository->save( $this->new_url( 'https://a.example.com', 'A', 1 ) );
		$this->repository->save( $this->new_url( 'https://b.example.com', 'B', 1 ) );
		$this->repository->save( $this->new_url( 'https://c.example.com', 'C', 2 ) );

		$this->assertCount( 2, $this->repository->find_by_project_id( 1 ) );
		$this->assertCount( 1, $this->repository->find_by_project_id( 2 ) );
		$this->assertCount( 0, $this->repository->find_by_project_id( 99 ) );
	}

	public function test_find_unassigned_returns_only_null_project_ids(): void {
		$this->repository->save( $this->new_url( 'https://assigned.example.com', 'A', 1 ) );
		$this->repository->save( $this->new_url( 'https://unassigned.example.com', 'U', null ) );

		$unassigned = $this->repository->find_unassigned();

		$this->assertCount( 1, $unassigned );
		$this->assertSame( 'U', $unassigned[0]->name() );
	}

	public function test_find_enabled_excludes_disabled_urls(): void {
		$enabled  = $this->new_url( 'https://on.example.com', 'On' );
		$disabled = $this->new_url( 'https://off.example.com', 'Off' );
		$disabled->set_enabled( false );

		$this->repository->save( $enabled );
		$this->repository->save( $disabled );

		$results = $this->repository->find_enabled();
		$this->assertCount( 1, $results );
		$this->assertSame( 'On', $results[0]->name() );
	}

	public function test_update_persists_changes(): void {
		$url = $this->repository->save( $this->new_url( 'https://example.com', 'Original' ) );

		$url->set_name( 'Renamed' );
		$url->set_audit_frequency( Audit_Frequency::DAILY );
		$url->set_enabled( false );
		$url->set_last_audited_at( new \DateTimeImmutable( '2026-02-01 09:00:00' ) );
		$url->set_updated_at( new \DateTimeImmutable() );

		$this->repository->update( $url );

		$reloaded = $this->repository->find_by_id( (int) $url->id() );
		$this->assertNotNull( $reloaded );
		$this->assertSame( 'Renamed', $reloaded->name() );
		$this->assertSame( Audit_Frequency::DAILY, $reloaded->audit_frequency() );
		$this->assertFalse( $reloaded->is_enabled() );
		$this->assertNotNull( $reloaded->last_audited_at() );
		$this->assertSame( '2026-02-01 09:00:00', $reloaded->last_audited_at()->format( 'Y-m-d H:i:s' ) );
	}

	public function test_delete_removes_the_row(): void {
		$url = $this->repository->save( $this->new_url( 'https://doomed.example.com', 'Doomed' ) );
		$id  = (int) $url->id();

		$this->repository->delete( $id );

		$this->assertNull( $this->repository->find_by_id( $id ) );
	}

	public function test_pagination_and_search(): void {
		foreach ( [ 'Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo' ] as $i => $name ) {
			$this->repository->save( $this->new_url( "https://{$i}.example.com", $name ) );
		}

		$page1 = $this->repository->find_paginated( 1, 2 );
		$page2 = $this->repository->find_paginated( 2, 2 );
		$this->assertCount( 2, $page1 );
		$this->assertCount( 2, $page2 );
		$this->assertNotSame( $page1[0]->name(), $page2[0]->name() );

		$this->assertSame( 5, $this->repository->count_for_search() );
		$this->assertSame( 1, $this->repository->count_for_search( 'Alpha' ) );

		// LIKE wildcards in user input must be escaped — searching for "%" must not match every row.
		$this->repository->save( $this->new_url( 'https://percent.example.com', '100% complete' ) );
		$this->assertSame( 1, $this->repository->count_for_search( '100%' ) );
		$this->assertSame( 1, $this->repository->count_for_search( '%' ) );
	}

	private function new_url( string $address, string $name, ?int $project_id = null ): Url {
		$now = new \DateTimeImmutable();

		return new Url(
			null,
			$project_id,
			new Url_Address( $address ),
			$name,
			Audit_Frequency::WEEKLY,
			Audit_Strategy::BOTH,
			true,
			false,
			null,
			null,
			null,
			$now,
			$now,
		);
	}
}
