<?php
/**
 * Issue_Category unit tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Audit;

use LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Issue_Category;
use LEAStudios\Tests\TestCase;

final class Issue_Category_Test extends TestCase {

	public function test_key_cases_exist(): void {
		$this->assertSame( 'color_contrast', Issue_Category::COLOR_CONTRAST->value );
		$this->assertSame( 'aria', Issue_Category::ARIA->value );
		$this->assertSame( 'forms', Issue_Category::FORMS->value );
		$this->assertSame( 'images', Issue_Category::IMAGES->value );
		$this->assertSame( 'navigation', Issue_Category::NAVIGATION->value );
		$this->assertSame( 'tables', Issue_Category::TABLES->value );
		$this->assertSame( 'other', Issue_Category::OTHER->value );
	}

	public function test_label_returns_human_readable_string(): void {
		$this->assertSame( 'Color Contrast', Issue_Category::COLOR_CONTRAST->label() );
		$this->assertSame( 'ARIA', Issue_Category::ARIA->label() );
		$this->assertSame( 'Forms', Issue_Category::FORMS->label() );
		$this->assertSame( 'Images', Issue_Category::IMAGES->label() );
	}

	public function test_from_string_creates_valid_category(): void {
		$this->assertSame( Issue_Category::ARIA, Issue_Category::from( 'aria' ) );
	}

	public function test_try_from_returns_null_for_invalid_value(): void {
		$this->assertNull( Issue_Category::tryFrom( 'nonexistent' ) );
	}
}
