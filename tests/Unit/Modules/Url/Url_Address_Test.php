<?php
/**
 * Url_Address value object tests.
 *
 * @package LEAStudios\SiteAudit\Tests
 */

declare(strict_types=1);

namespace LEAStudios\SiteAudit\Tests\Unit\Modules\Url;

use LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Url_Address;
use LEAStudios\SiteAudit\Shared\Exceptions\Validation_Exception;
use LEAStudios\Tests\TestCase;

final class Url_Address_Test extends TestCase {

	public function test_can_be_created_with_valid_https_url(): void {
		$url = new Url_Address( 'https://example.com' );
		$this->assertSame( 'https://example.com', $url->value() );
	}

	public function test_can_be_created_with_valid_http_url(): void {
		$url = new Url_Address( 'http://example.com' );
		$this->assertSame( 'http://example.com', $url->value() );
	}

	public function test_can_be_created_with_url_containing_path(): void {
		$url = new Url_Address( 'https://example.com/path/to/page' );
		$this->assertSame( 'https://example.com/path/to/page', $url->value() );
	}

	public function test_can_be_created_with_url_containing_query_string(): void {
		$url = new Url_Address( 'https://example.com?foo=bar&baz=qux' );
		$this->assertSame( 'https://example.com?foo=bar&baz=qux', $url->value() );
	}

	public function test_trims_whitespace(): void {
		$url = new Url_Address( '  https://example.com  ' );
		$this->assertSame( 'https://example.com', $url->value() );
	}

	public function test_removes_trailing_slash(): void {
		$url = new Url_Address( 'https://example.com/' );
		$this->assertSame( 'https://example.com', $url->value() );
	}

	public function test_throws_exception_for_empty_string(): void {
		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'URL cannot be empty' );
		new Url_Address( '' );
	}

	public function test_throws_exception_for_whitespace_only(): void {
		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'URL cannot be empty' );
		new Url_Address( '   ' );
	}

	public function test_throws_exception_for_invalid_url(): void {
		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'Invalid URL format' );
		new Url_Address( 'not-a-url' );
	}

	public function test_throws_exception_for_url_without_scheme(): void {
		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'URL must use http or https scheme' );
		new Url_Address( 'ftp://example.com' );
	}

	public function test_throws_exception_for_url_without_host(): void {
		$this->expectException( Validation_Exception::class );
		$this->expectExceptionMessage( 'Invalid URL format' );
		new Url_Address( 'https://' );
	}

	public function test_equals_returns_true_for_same_url(): void {
		$url1 = new Url_Address( 'https://example.com' );
		$url2 = new Url_Address( 'https://example.com' );
		$this->assertTrue( $url1->equals( $url2 ) );
	}

	public function test_equals_returns_false_for_different_url(): void {
		$url1 = new Url_Address( 'https://example.com' );
		$url2 = new Url_Address( 'https://other.com' );
		$this->assertFalse( $url1->equals( $url2 ) );
	}

	public function test_to_string_returns_value(): void {
		$url = new Url_Address( 'https://example.com' );
		$this->assertSame( 'https://example.com', (string) $url );
	}
}
