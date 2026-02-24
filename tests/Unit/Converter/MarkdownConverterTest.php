<?php
/**
 * Tests for MarkdownConverter.
 *
 * @package IlloDev\MarkdownNegotiation\Tests\Unit\Converter
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Tests\Unit\Converter;

use IlloDev\MarkdownNegotiation\Converter\ContentExtractor;
use IlloDev\MarkdownNegotiation\Converter\MarkdownConverter;
use IlloDev\MarkdownNegotiation\Tests\TestCase;
use Mockery;

/**
 * Class MarkdownConverterTest
 *
 * Tests for HTML-to-Markdown conversion.
 */
final class MarkdownConverterTest extends TestCase {

	private MarkdownConverter $converter;

	protected function setUp(): void {
		parent::setUp();

		$extractor = Mockery::mock( ContentExtractor::class );

		// Mock WordPress functions used in the converter.
		\Brain\Monkey\Functions\stubs( array(
			'do_action'     => null,
			'apply_filters' => function ( string $tag, ...$args ) {
				return $args[0];
			},
		) );

		$this->converter = new MarkdownConverter( $extractor );
	}

	/**
	 * @test
	 */
	public function it_is_available(): void {
		$this->assertTrue( $this->converter->is_available() );
	}

	/**
	 * @test
	 */
	public function it_reports_correct_name(): void {
		$this->assertSame( 'league/html-to-markdown', $this->converter->get_name() );
	}

	/**
	 * @test
	 */
	public function it_converts_headings(): void {
		$html = '<h1>Title</h1><h2>Subtitle</h2><h3>Section</h3>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringContainsString( '# Title', $markdown );
		$this->assertStringContainsString( '## Subtitle', $markdown );
		$this->assertStringContainsString( '### Section', $markdown );
	}

	/**
	 * @test
	 */
	public function it_converts_paragraphs(): void {
		$html = '<p>This is a paragraph.</p><p>This is another paragraph.</p>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringContainsString( 'This is a paragraph.', $markdown );
		$this->assertStringContainsString( 'This is another paragraph.', $markdown );
	}

	/**
	 * @test
	 */
	public function it_converts_bold_and_italic(): void {
		$html = '<p><strong>bold</strong> and <em>italic</em></p>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringContainsString( '**bold**', $markdown );
		$this->assertStringContainsString( '*italic*', $markdown );
	}

	/**
	 * @test
	 */
	public function it_converts_links(): void {
		$html = '<p><a href="https://example.com">Click here</a></p>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringContainsString( '[Click here](https://example.com)', $markdown );
	}

	/**
	 * @test
	 */
	public function it_converts_images_with_alt_text(): void {
		$html = '<img src="https://example.com/image.jpg" alt="A beautiful sunset" />';

		$markdown = $this->converter->convert( $html );

		$this->assertStringContainsString( '![A beautiful sunset](https://example.com/image.jpg)', $markdown );
	}

	/**
	 * @test
	 */
	public function it_converts_unordered_lists(): void {
		$html = '<ul><li>Item 1</li><li>Item 2</li><li>Item 3</li></ul>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringContainsString( '- Item 1', $markdown );
		$this->assertStringContainsString( '- Item 2', $markdown );
		$this->assertStringContainsString( '- Item 3', $markdown );
	}

	/**
	 * @test
	 */
	public function it_converts_ordered_lists(): void {
		$html = '<ol><li>First</li><li>Second</li><li>Third</li></ol>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringContainsString( '1. First', $markdown );
		$this->assertStringContainsString( '2. Second', $markdown );
		$this->assertStringContainsString( '3. Third', $markdown );
	}

	/**
	 * @test
	 */
	public function it_converts_blockquotes(): void {
		$html = '<blockquote><p>This is a quote.</p></blockquote>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringContainsString( '> This is a quote.', $markdown );
	}

	/**
	 * @test
	 */
	public function it_converts_code_blocks(): void {
		$html = '<pre><code>console.log("hello");</code></pre>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringContainsString( 'console.log("hello");', $markdown );
	}

	/**
	 * @test
	 */
	public function it_converts_inline_code(): void {
		$html = '<p>Use the <code>wp_query</code> function.</p>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringContainsString( '`wp_query`', $markdown );
	}

	/**
	 * @test
	 */
	public function it_converts_horizontal_rules(): void {
		$html = '<p>Before</p><hr><p>After</p>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringContainsString( '---', $markdown );
	}

	/**
	 * @test
	 */
	public function it_strips_scripts(): void {
		$html = '<p>Content</p><script>alert("xss")</script>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringNotContainsString( 'script', $markdown );
		$this->assertStringNotContainsString( 'alert', $markdown );
		$this->assertStringContainsString( 'Content', $markdown );
	}

	/**
	 * @test
	 */
	public function it_strips_styles(): void {
		$html = '<style>.body{color:red}</style><p>Content</p>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringNotContainsString( 'color', $markdown );
		$this->assertStringContainsString( 'Content', $markdown );
	}

	/**
	 * @test
	 */
	public function it_removes_html_comments(): void {
		$html = '<!-- wp:paragraph --><p>Content</p><!-- /wp:paragraph -->';

		$markdown = $this->converter->convert( $html );

		$this->assertStringNotContainsString( '<!--', $markdown );
		$this->assertStringContainsString( 'Content', $markdown );
	}

	/**
	 * @test
	 */
	public function it_estimates_tokens(): void {
		$markdown = str_repeat( 'word ', 100 ); // ~500 chars.

		$tokens = $this->converter->estimate_tokens( $markdown );

		// Roughly 500/4 = 125 tokens.
		$this->assertGreaterThan( 100, $tokens );
		$this->assertLessThan( 200, $tokens );
	}

	/**
	 * @test
	 */
	public function it_handles_empty_html(): void {
		$markdown = $this->converter->convert( '' );

		$this->assertSame( "\n", $markdown );
	}

	/**
	 * @test
	 */
	public function it_removes_excessive_blank_lines(): void {
		$html = '<p>Paragraph 1</p>' . str_repeat( '<br>', 10 ) . '<p>Paragraph 2</p>';

		$markdown = $this->converter->convert( $html );

		$this->assertDoesNotMatchRegularExpression( '/\n{4,}/', $markdown );
	}
}
