<?php
/**
 * Audit_Status unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Audit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Audit_Status;
use LEAStudios\Tests\TestCase;

final class Audit_Status_Test extends TestCase {

	public function test_pending_case_exists(): void {
		$this->assertSame( 'pending', Audit_Status::PENDING->value );
	}

	public function test_in_progress_case_exists(): void {
		$this->assertSame( 'in_progress', Audit_Status::IN_PROGRESS->value );
	}

	public function test_completed_case_exists(): void {
		$this->assertSame( 'completed', Audit_Status::COMPLETED->value );
	}

	public function test_failed_case_exists(): void {
		$this->assertSame( 'failed', Audit_Status::FAILED->value );
	}

	public function test_label_returns_human_readable_string(): void {
		$this->assertSame( 'Pending', Audit_Status::PENDING->label() );
		$this->assertSame( 'In Progress', Audit_Status::IN_PROGRESS->label() );
		$this->assertSame( 'Completed', Audit_Status::COMPLETED->label() );
		$this->assertSame( 'Failed', Audit_Status::FAILED->label() );
	}

	public function test_is_terminal_returns_correct_values(): void {
		$this->assertFalse( Audit_Status::PENDING->is_terminal() );
		$this->assertFalse( Audit_Status::IN_PROGRESS->is_terminal() );
		$this->assertTrue( Audit_Status::COMPLETED->is_terminal() );
		$this->assertTrue( Audit_Status::FAILED->is_terminal() );
	}

	public function test_all_cases_are_available(): void {
		$this->assertCount( 4, Audit_Status::cases() );
	}
}
