<?php
/**
 * Tests for Sanitizer.
 *
 * @package IlloDev\MarkdownNegotiation\Tests\Unit\Security
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Tests\Unit\Security;

use IlloDev\MarkdownNegotiation\Security\Sanitizer;
use IlloDev\MarkdownNegotiation\Tests\TestCase;

/**
 * Class SanitizerTest
 */
final class SanitizerTest extends TestCase {

	private Sanitizer $sanitizer;

	protected function setUp(): void {
		parent::setUp();

		\Brain\Monkey\Functions\stubs( array(
			'apply_filters' => function ( string $tag, ...$args ) {
				return $args[0];
			},
		) );

		$this->sanitizer = new Sanitizer();
	}

	/**
	 * @test
	 */
	public function it_removes_script_tags(): void {
		$html = '<p>Safe content</p><script>alert("xss")</script>';

		$result = $this->sanitizer->sanitize_html( $html );

		$this->assertStringNotContainsString( '<script', $result );
		$this->assertStringNotContainsString( 'alert', $result );
		$this->assertStringContainsString( 'Safe content', $result );
	}

	/**
	 * @test
	 */
	public function it_removes_style_tags(): void {
		$html = '<style>.body{color:red}</style><p>Content</p>';

		$result = $this->sanitizer->sanitize_html( $html );

		$this->assertStringNotContainsString( '<style', $result );
		$this->assertStringContainsString( 'Content', $result );
	}

	/**
	 * @test
	 */
	public function it_removes_nav_elements(): void {
		$html = '<nav><ul><li>Link</li></ul></nav><p>Content</p>';

		$result = $this->sanitizer->sanitize_html( $html );

		$this->assertStringNotContainsString( '<nav', $result );
		$this->assertStringContainsString( 'Content', $result );
	}

	/**
	 * @test
	 */
	public function it_removes_inline_styles(): void {
		$html = '<p style="color:red; font-size:20px;">Content</p>';

		$result = $this->sanitizer->sanitize_html( $html );

		$this->assertStringNotContainsString( 'style=', $result );
		$this->assertStringContainsString( 'Content', $result );
	}

	/**
	 * @test
	 */
	public function it_removes_event_handlers(): void {
		$html = '<p onclick="alert(1)">Content</p>';

		$result = $this->sanitizer->sanitize_html( $html );

		$this->assertStringNotContainsString( 'onclick', $result );
	}

	/**
	 * @test
	 */
	public function it_removes_html_comments(): void {
		$html = '<!-- secret comment --><p>Content</p>';

		$result = $this->sanitizer->sanitize_html( $html );

		$this->assertStringNotContainsString( '<!--', $result );
		$this->assertStringContainsString( 'Content', $result );
	}

	/**
	 * @test
	 */
	public function it_removes_iframes(): void {
		$html = '<p>Before</p><iframe src="https://evil.com"></iframe><p>After</p>';

		$result = $this->sanitizer->sanitize_html( $html );

		$this->assertStringNotContainsString( '<iframe', $result );
	}

	/**
	 * @test
	 */
	public function it_handles_empty_input(): void {
		$this->assertSame( '', $this->sanitizer->sanitize_html( '' ) );
	}

	/**
	 * @test
	 */
	public function it_sanitizes_markdown_javascript_urls(): void {
		$markdown = '[Click me](javascript:alert(1))';

		$result = $this->sanitizer->sanitize_markdown( $markdown );

		$this->assertStringNotContainsString( 'javascript:', $result );
	}

	/**
	 * @test
	 */
	public function it_sanitizes_markdown_data_urls(): void {
		$markdown = '[Download](data:text/html,<script>alert(1)</script>)';

		$result = $this->sanitizer->sanitize_markdown( $markdown );

		$this->assertStringNotContainsString( 'data:text', $result );
	}

	/**
	 * @test
	 */
	public function it_removes_null_bytes_from_markdown(): void {
		$markdown = "# Title\0Hidden";

		$result = $this->sanitizer->sanitize_markdown( $markdown );

		$this->assertStringNotContainsString( "\0", $result );
	}
}
