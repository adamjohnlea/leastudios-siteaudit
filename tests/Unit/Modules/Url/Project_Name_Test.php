<?php
/**
 * Project_Name value object tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Url;

use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Project_Name;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;
use LEAStudios\Tests\TestCase;

final class Project_Name_Test extends TestCase {

	public function test_can_be_created_with_valid_name(): void {
		$name = new Project_Name( 'My Project' );
		$this->assertSame( 'My Project', $name->value() );
	}

	public function test_trims_whitespace(): void {
		$name = new Project_Name( '  My Project  ' );
		$this->assertSame( 'My Project', $name->value() );
	}

	public function test_throws_exception_for_empty_string(): void {
		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'Project name cannot be empty' );
		new Project_Name( '' );
	}

	public function test_throws_exception_for_whitespace_only(): void {
		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'Project name cannot be empty' );
		new Project_Name( '   ' );
	}

	public function test_throws_exception_when_name_exceeds_max_length(): void {
		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'Project name must not exceed 255 characters' );
		new Project_Name( str_repeat( 'a', 256 ) );
	}

	public function test_allows_name_at_max_length(): void {
		$name = new Project_Name( str_repeat( 'a', 255 ) );
		$this->assertSame( 255, strlen( $name->value() ) );
	}

	public function test_equals_returns_true_for_same_name(): void {
		$this->assertTrue( ( new Project_Name( 'A' ) )->equals( new Project_Name( 'A' ) ) );
	}

	public function test_equals_returns_false_for_different_name(): void {
		$this->assertFalse( ( new Project_Name( 'A' ) )->equals( new Project_Name( 'B' ) ) );
	}

	public function test_to_string_returns_value(): void {
		$name = new Project_Name( 'My Project' );
		$this->assertSame( 'My Project', (string) $name );
	}
}
