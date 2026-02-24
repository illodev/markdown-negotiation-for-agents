<?php
/**
 * Tests for HeaderValidator.
 *
 * @package IlloDev\MarkdownNegotiation\Tests\Unit\Http
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Tests\Unit\Http;

use IlloDev\MarkdownNegotiation\Http\HeaderValidator;
use PHPUnit\Framework\TestCase;

/**
 * Class HeaderValidatorTest
 */
final class HeaderValidatorTest extends TestCase {

	private HeaderValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new HeaderValidator();
	}

	/**
	 * @test
	 */
	public function it_validates_normal_accept_header(): void {
		$this->assertTrue(
			$this->validator->validate_accept_header( 'text/markdown' )
		);
	}

	/**
	 * @test
	 */
	public function it_validates_complex_accept_header(): void {
		$this->assertTrue(
			$this->validator->validate_accept_header(
				'text/html, text/markdown;q=0.9, application/json;q=0.8, */*;q=0.1'
			)
		);
	}

	/**
	 * @test
	 */
	public function it_rejects_too_long_header(): void {
		$long_header = str_repeat( 'text/markdown, ', 100 );

		$this->assertFalse(
			$this->validator->validate_accept_header( $long_header )
		);
	}

	/**
	 * @test
	 */
	public function it_rejects_null_bytes(): void {
		$this->assertFalse(
			$this->validator->validate_accept_header( "text/markdown\0" )
		);
	}

	/**
	 * @test
	 */
	public function it_rejects_non_ascii(): void {
		$this->assertFalse(
			$this->validator->validate_accept_header( "text/markdown\x80" )
		);
	}

	/**
	 * @test
	 */
	public function it_validates_empty_header(): void {
		$this->assertTrue(
			$this->validator->validate_accept_header( '' )
		);
	}
}
