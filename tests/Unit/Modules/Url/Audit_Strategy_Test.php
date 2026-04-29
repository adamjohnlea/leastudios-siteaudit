<?php
/**
 * Audit_Strategy enum tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Url;

use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Strategy;
use LEAStudios\Tests\TestCase;

final class Audit_Strategy_Test extends TestCase {

	public function test_each_case_serialises_to_expected_string(): void {
		$this->assertSame( 'desktop', Audit_Strategy::DESKTOP->value );
		$this->assertSame( 'mobile', Audit_Strategy::MOBILE->value );
		$this->assertSame( 'both', Audit_Strategy::BOTH->value );
	}

	public function test_try_from_returns_null_for_invalid_value(): void {
		$this->assertNull( Audit_Strategy::tryFrom( 'tablet' ) );
	}

	public function test_label_returns_human_readable_string(): void {
		$this->assertSame( 'Desktop only', Audit_Strategy::DESKTOP->label() );
		$this->assertSame( 'Mobile only', Audit_Strategy::MOBILE->label() );
		$this->assertSame( 'Desktop & Mobile', Audit_Strategy::BOTH->label() );
	}
}
