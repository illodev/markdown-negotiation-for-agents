<?php
/**
 * Integration test for the full content negotiation flow.
 *
 * Tests the complete request → negotiation → conversion → response pipeline.
 *
 * @package IlloDev\MarkdownNegotiation\Tests\Integration
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Tests\Integration;

use IlloDev\MarkdownNegotiation\Tests\TestCase;
use IlloDev\MarkdownNegotiation\Http\ContentNegotiator;
use IlloDev\MarkdownNegotiation\Http\HeaderValidator;
use IlloDev\MarkdownNegotiation\Converter\MarkdownConverter;
use IlloDev\MarkdownNegotiation\Converter\ContentExtractor;
use IlloDev\MarkdownNegotiation\Converter\GutenbergProcessor;
use IlloDev\MarkdownNegotiation\Converter\ShortcodeProcessor;
use IlloDev\MarkdownNegotiation\Security\Sanitizer;
use IlloDev\MarkdownNegotiation\Cache\CacheManager;
use IlloDev\MarkdownNegotiation\Contracts\CacheInterface;
use Mockery;

/**
 * Class ContentNegotiationFlowTest
 *
 * End-to-end integration tests for the content negotiation pipeline.
 */
final class ContentNegotiationFlowTest extends TestCase {

	private ContentNegotiator $negotiator;
	private HeaderValidator $header_validator;
	private MarkdownConverter $converter;
	private CacheManager $cache_manager;

	protected function setUp(): void {
		parent::setUp();

		// Stub all WordPress functions used across the pipeline.
		\Brain\Monkey\Functions\stubs( array(
			'do_action'       => null,
			'apply_filters'   => function ( string $tag, ...$args ) {
				return $args[0];
			},
			'has_blocks'      => false,
			'do_shortcode'    => function ( string $content ): string {
				return $content;
			},
			'wp_strip_all_tags' => function ( string $text ): string {
				return strip_tags( $text );
			},
			'wp_kses_post'    => function ( string $content ): string {
				return $content;
			},
			'wp_kses'         => function ( string $content ): string {
				return strip_tags( $content, '<p><a><strong><em><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><pre><code><img><table><thead><tbody><tr><th><td><hr><br><div><span><figure><figcaption>' );
			},
			'esc_attr'        => function ( string $text ): string {
				return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
			},
			'esc_html'        => function ( string $text ): string {
				return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
			},
			'esc_url'         => function ( string $url ): string {
				return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
			},
			'wp_parse_args'   => function ( $args, $defaults = array() ) {
				return array_merge( $defaults, (array) $args );
			},
		) );

		// Build the converter pipeline.
		$sanitizer           = new Sanitizer();
		$gutenberg_processor = new GutenbergProcessor();
		$shortcode_processor = new ShortcodeProcessor();

		$extractor = new ContentExtractor(
			$sanitizer,
			$gutenberg_processor,
			$shortcode_processor
		);

		$this->converter       = new MarkdownConverter( $extractor );
		$this->negotiator      = new ContentNegotiator();
		$this->header_validator = new HeaderValidator();

		// Create cache manager with a mock driver.
		$cache_driver = Mockery::mock( CacheInterface::class );
		$cache_driver->shouldReceive( 'get' )->andReturn( null )->byDefault();
		$cache_driver->shouldReceive( 'set' )->andReturn( true )->byDefault();
		$cache_driver->shouldReceive( 'delete' )->andReturn( true )->byDefault();
		$cache_driver->shouldReceive( 'flush' )->andReturn( true )->byDefault();

		$this->cache_manager = new CacheManager( $cache_driver, 3600 );
	}

	/**
	 * @test
	 */
	public function full_pipeline_converts_html_post_to_markdown(): void {
		// Simulate Accept: text/markdown request.
		$_SERVER['HTTP_ACCEPT'] = 'text/markdown';
		$this->negotiator->reset();

		// Verify negotiation detects markdown request.
		$this->assertTrue( $this->negotiator->wants_markdown() );
		$this->assertSame( 'text/markdown', $this->negotiator->get_media_type() );

		// Validate the request headers.
		$this->assertTrue( $this->header_validator->validate_request() );

		// Convert HTML content.
		$html = '<h1>My Post Title</h1><p>This is the article content with <strong>bold text</strong> and a <a href="https://example.com">link</a>.</p>';

		$markdown = $this->converter->convert( $html );

		// Verify Markdown output.
		$this->assertStringContainsString( '# My Post Title', $markdown );
		$this->assertStringContainsString( '**bold text**', $markdown );
		$this->assertStringContainsString( '[link](https://example.com)', $markdown );

		// Verify caching works.
		$key = $this->cache_manager->build_key( 42 );
		$this->assertSame( 'md_42', $key );
		$this->assertTrue( $this->cache_manager->set( $key, $markdown ) );

		unset( $_SERVER['HTTP_ACCEPT'] );
	}

	/**
	 * @test
	 */
	public function pipeline_rejects_html_requests(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/html, application/xhtml+xml, */*;q=0.1';
		$this->negotiator->reset();

		$this->assertFalse( $this->negotiator->wants_markdown() );

		unset( $_SERVER['HTTP_ACCEPT'] );
	}

	/**
	 * @test
	 */
	public function pipeline_handles_quality_values_correctly(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/html;q=0.5, text/markdown;q=0.9';
		$this->negotiator->reset();

		$this->assertTrue( $this->negotiator->wants_markdown() );
		$this->assertSame( 'text/markdown', $this->negotiator->get_media_type() );

		unset( $_SERVER['HTTP_ACCEPT'] );
	}

	/**
	 * @test
	 */
	public function pipeline_prefers_html_when_higher_quality(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/html;q=1.0, text/markdown;q=0.5';
		$this->negotiator->reset();

		$this->assertFalse( $this->negotiator->wants_markdown() );

		unset( $_SERVER['HTTP_ACCEPT'] );
	}

	/**
	 * @test
	 */
	public function pipeline_converts_complex_content(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/markdown';
		$this->negotiator->reset();

		$html = <<<'HTML'
<h1>Documentation Guide</h1>
<p>Welcome to the documentation. This guide covers:</p>
<ul>
<li>Installation steps</li>
<li>Configuration options</li>
<li>API reference</li>
</ul>
<h2>Installation</h2>
<p>Run the following command:</p>
<pre><code>composer require package/name</code></pre>
<blockquote><p>Note: Requires PHP 8.1+</p></blockquote>
<h2>Links</h2>
<p>Visit <a href="https://example.com">our website</a> for more info.</p>
<p><img src="https://example.com/logo.png" alt="Logo" /></p>
HTML;

		$markdown = $this->converter->convert( $html );

		// Verify all content types converted.
		$this->assertStringContainsString( '# Documentation Guide', $markdown );
		$this->assertStringContainsString( '## Installation', $markdown );
		$this->assertStringContainsString( '- Installation steps', $markdown );
		$this->assertStringContainsString( '- Configuration options', $markdown );
		$this->assertStringContainsString( '- API reference', $markdown );
		$this->assertStringContainsString( 'composer require package/name', $markdown );
		$this->assertStringContainsString( '> Note: Requires PHP 8.1+', $markdown );
		$this->assertStringContainsString( '[our website](https://example.com)', $markdown );
		$this->assertStringContainsString( '![Logo](https://example.com/logo.png)', $markdown );

		unset( $_SERVER['HTTP_ACCEPT'] );
	}

	/**
	 * @test
	 */
	public function pipeline_strips_dangerous_content(): void {
		$html = '<h1>Clean Content</h1><script>alert("xss")</script><p>Safe paragraph.</p><style>.hidden{display:none}</style>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringContainsString( '# Clean Content', $markdown );
		$this->assertStringContainsString( 'Safe paragraph.', $markdown );
		$this->assertStringNotContainsString( 'alert', $markdown );
		$this->assertStringNotContainsString( 'script', $markdown );
		$this->assertStringNotContainsString( 'style', $markdown );
		$this->assertStringNotContainsString( '.hidden', $markdown );
	}

	/**
	 * @test
	 */
	public function pipeline_handles_x_markdown_type(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/x-markdown';
		$this->negotiator->reset();

		$this->assertTrue( $this->negotiator->wants_markdown() );
		$this->assertSame( 'text/x-markdown', $this->negotiator->get_media_type() );

		unset( $_SERVER['HTTP_ACCEPT'] );
	}

	/**
	 * @test
	 */
	public function pipeline_validates_malicious_headers(): void {
		$_SERVER['HTTP_ACCEPT'] = str_repeat( 'a', 2000 );

		$this->assertFalse( $this->header_validator->validate_request() );

		unset( $_SERVER['HTTP_ACCEPT'] );
	}

	/**
	 * @test
	 */
	public function cache_invalidation_flow(): void {
		$post_id = 42;

		// Build key and verify format.
		$key = $this->cache_manager->build_key( $post_id );
		$this->assertSame( 'md_42', $key );

		// Set and get cache.
		$this->assertTrue( $this->cache_manager->set( $key, '# Test' ) );
		// Note: get returns null because mock is set to return null by default.
		$this->assertNull( $this->cache_manager->get( $key ) );

		// Invalidate.
		$this->cache_manager->invalidate_post( $post_id );

		// Flush all.
		$this->assertTrue( $this->cache_manager->flush_all() );
	}

	/**
	 * @test
	 */
	public function converter_estimates_tokens(): void {
		$markdown = str_repeat( 'a', 400 ); // 400 chars ≈ 100 tokens.

		$tokens = $this->converter->estimate_tokens( $markdown );

		$this->assertSame( 100, $tokens );
	}

	/**
	 * @test
	 */
	public function converter_handles_tables(): void {
		$html = '<table><thead><tr><th>Name</th><th>Value</th></tr></thead><tbody><tr><td>A</td><td>1</td></tr><tr><td>B</td><td>2</td></tr></tbody></table>';

		$markdown = $this->converter->convert( $html );

		$this->assertStringContainsString( 'Name', $markdown );
		$this->assertStringContainsString( 'Value', $markdown );
		$this->assertStringContainsString( '|', $markdown );
	}

	/**
	 * @test
	 */
	public function empty_accept_header_does_not_trigger_markdown(): void {
		unset( $_SERVER['HTTP_ACCEPT'] );
		$this->negotiator->reset();

		$this->assertFalse( $this->negotiator->wants_markdown() );
	}

	/**
	 * @test
	 */
	public function wildcard_accept_does_not_trigger_markdown(): void {
		$_SERVER['HTTP_ACCEPT'] = '*/*';
		$this->negotiator->reset();

		$this->assertFalse( $this->negotiator->wants_markdown() );

		unset( $_SERVER['HTTP_ACCEPT'] );
	}

	/**
	 * @test
	 */
	public function browser_accept_header_does_not_trigger_markdown(): void {
		// Typical browser Accept header.
		$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8';
		$this->negotiator->reset();

		$this->assertFalse( $this->negotiator->wants_markdown() );

		unset( $_SERVER['HTTP_ACCEPT'] );
	}
}
