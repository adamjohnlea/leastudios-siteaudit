<?php
/**
 * Audit_Frequency enum tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Url;

use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency;
use LEAStudios\Tests\TestCase;

final class Audit_Frequency_Test extends TestCase {

	public function test_each_case_serialises_to_expected_string(): void {
		$this->assertSame( 'daily', Audit_Frequency::DAILY->value );
		$this->assertSame( 'weekly', Audit_Frequency::WEEKLY->value );
		$this->assertSame( 'biweekly', Audit_Frequency::BIWEEKLY->value );
		$this->assertSame( 'monthly', Audit_Frequency::MONTHLY->value );
	}

	public function test_from_string_creates_valid_frequency(): void {
		$this->assertSame( Audit_Frequency::DAILY, Audit_Frequency::from( 'daily' ) );
	}

	public function test_try_from_returns_null_for_invalid_value(): void {
		$this->assertNull( Audit_Frequency::tryFrom( 'invalid' ) );
	}

	public function test_label_returns_human_readable_string(): void {
		$this->assertSame( 'Daily', Audit_Frequency::DAILY->label() );
		$this->assertSame( 'Weekly', Audit_Frequency::WEEKLY->label() );
		$this->assertSame( 'Biweekly', Audit_Frequency::BIWEEKLY->label() );
		$this->assertSame( 'Monthly', Audit_Frequency::MONTHLY->label() );
	}

	public function test_all_cases_are_available(): void {
		$this->assertCount( 4, Audit_Frequency::cases() );
	}
}
