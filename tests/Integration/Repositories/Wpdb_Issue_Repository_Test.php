<?php
/**
 * Wpdb_Issue_Repository integration tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Integration\Repositories;

use LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Issue;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Category;
use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Severity;
use LEAStudios\SiteAudit\Modules\Audit\Infrastructure\Repositories\Wpdb_Issue_Repository;
use LEAStudios\Tests\TestCase;

final class Wpdb_Issue_Repository_Test extends TestCase {

	private Wpdb_Issue_Repository $repository;

	public function set_up(): void {
		parent::set_up();
		$this->repository = new Wpdb_Issue_Repository();
	}

	public function test_save_assigns_an_id(): void {
		$issue = $this->repository->save(
			$this->new_issue(
				audit_id: 1,
				severity: Issue_Severity::CRITICAL,
				category: Issue_Category::COLOR_CONTRAST,
				description: 'Background and foreground colors do not have a sufficient contrast ratio',
				element_selector: 'h1.hero',
				help_url: 'https://example.com/help',
				title: 'Color contrast',
			)
		);

		$this->assertNotNull( $issue->id() );

		$rows = $this->repository->find_by_audit_id( 1 );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'h1.hero', $rows[0]->element_selector() );
		$this->assertSame( 'https://example.com/help', $rows[0]->help_url() );
		$this->assertSame( 'Color contrast', $rows[0]->title() );
	}

	public function test_save_many_persists_all_issues_in_one_call(): void {
		$issues = [
			$this->new_issue( audit_id: 9, severity: Issue_Severity::CRITICAL, description: 'a' ),
			$this->new_issue( audit_id: 9, severity: Issue_Severity::SERIOUS, description: 'b' ),
			$this->new_issue( audit_id: 9, severity: Issue_Severity::MINOR, description: 'c' ),
		];

		$saved = $this->repository->save_many( $issues );

		$this->assertCount( 3, $saved );
		foreach ( $saved as $issue ) {
			$this->assertNotNull( $issue->id() );
		}

		$reloaded = $this->repository->find_by_audit_id( 9 );
		$this->assertCount( 3, $reloaded );
	}

	public function test_find_by_audit_id_orders_by_severity_ascending(): void {
		$this->repository->save( $this->new_issue( audit_id: 42, severity: Issue_Severity::SERIOUS, description: 'b' ) );
		$this->repository->save( $this->new_issue( audit_id: 42, severity: Issue_Severity::CRITICAL, description: 'a' ) );
		$this->repository->save( $this->new_issue( audit_id: 42, severity: Issue_Severity::MINOR, description: 'c' ) );

		$rows = $this->repository->find_by_audit_id( 42 );

		// 'critical' < 'minor' < 'serious' alphabetically — matches the source ASC ordering.
		$this->assertCount( 3, $rows );
		$this->assertSame( Issue_Severity::CRITICAL, $rows[0]->severity() );
		$this->assertSame( Issue_Severity::MINOR, $rows[1]->severity() );
		$this->assertSame( Issue_Severity::SERIOUS, $rows[2]->severity() );
	}

	public function test_find_by_audit_id_returns_empty_when_no_match(): void {
		$this->assertSame( [], $this->repository->find_by_audit_id( 999 ) );
	}

	private function new_issue(
		int $audit_id = 1,
		Issue_Severity $severity = Issue_Severity::CRITICAL,
		Issue_Category $category = Issue_Category::OTHER,
		string $description = 'Some issue',
		?string $element_selector = null,
		?string $help_url = null,
		?string $title = null
	): Issue {
		return new Issue(
			null,
			$audit_id,
			$severity,
			$category,
			$description,
			$element_selector,
			$help_url,
			new \DateTimeImmutable(),
			$title,
		);
	}
}
