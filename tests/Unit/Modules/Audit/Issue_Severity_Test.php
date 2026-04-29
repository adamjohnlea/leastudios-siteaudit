<?php
/**
 * Issue_Severity unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Audit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Severity;
use LEAStudios\Tests\TestCase;

final class Issue_Severity_Test extends TestCase {

	public function test_all_cases_exist(): void {
		$this->assertSame( 'critical', Issue_Severity::CRITICAL->value );
		$this->assertSame( 'serious', Issue_Severity::SERIOUS->value );
		$this->assertSame( 'moderate', Issue_Severity::MODERATE->value );
		$this->assertSame( 'minor', Issue_Severity::MINOR->value );
	}

	public function test_label_returns_human_readable_string(): void {
		$this->assertSame( 'Critical', Issue_Severity::CRITICAL->label() );
		$this->assertSame( 'Serious', Issue_Severity::SERIOUS->label() );
		$this->assertSame( 'Moderate', Issue_Severity::MODERATE->label() );
		$this->assertSame( 'Minor', Issue_Severity::MINOR->label() );
	}

	public function test_weight_returns_severity_order(): void {
		$this->assertGreaterThan( Issue_Severity::SERIOUS->weight(), Issue_Severity::CRITICAL->weight() );
		$this->assertGreaterThan( Issue_Severity::MODERATE->weight(), Issue_Severity::SERIOUS->weight() );
		$this->assertGreaterThan( Issue_Severity::MINOR->weight(), Issue_Severity::MODERATE->weight() );
	}

	public function test_all_cases_count(): void {
		$this->assertCount( 4, Issue_Severity::cases() );
	}
}
