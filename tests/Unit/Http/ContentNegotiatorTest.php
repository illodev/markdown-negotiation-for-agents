<?php
/**
 * Tests for ContentNegotiator.
 *
 * @package IlloDev\MarkdownNegotiation\Tests\Unit\Http
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Tests\Unit\Http;

use IlloDev\MarkdownNegotiation\Http\ContentNegotiator;
use IlloDev\MarkdownNegotiation\Tests\TestCase;

/**
 * Class ContentNegotiatorTest
 *
 * Tests for Accept header parsing and content negotiation.
 */
final class ContentNegotiatorTest extends TestCase {

	private ContentNegotiator $negotiator;

	protected function setUp(): void {
		parent::setUp();
		$this->negotiator = new ContentNegotiator();
	}

	/**
	 * @test
	 */
	public function it_parses_simple_accept_header(): void {
		$result = $this->negotiator->parse_accept_header( 'text/markdown' );

		$this->assertCount( 1, $result );
		$this->assertSame( 'text/markdown', $result[0]['type'] );
		$this->assertSame( 1.0, $result[0]['quality'] );
	}

	/**
	 * @test
	 */
	public function it_parses_multiple_types(): void {
		$result = $this->negotiator->parse_accept_header( 'text/html, text/markdown, application/json' );

		$this->assertCount( 3, $result );
		$this->assertSame( 'text/html', $result[0]['type'] );
		$this->assertSame( 'text/markdown', $result[1]['type'] );
		$this->assertSame( 'application/json', $result[2]['type'] );
	}

	/**
	 * @test
	 */
	public function it_sorts_by_quality_value(): void {
		$result = $this->negotiator->parse_accept_header(
			'text/html;q=0.5, text/markdown;q=1.0, application/json;q=0.8'
		);

		$this->assertSame( 'text/markdown', $result[0]['type'] );
		$this->assertSame( 'application/json', $result[1]['type'] );
		$this->assertSame( 'text/html', $result[2]['type'] );
	}

	/**
	 * @test
	 */
	public function it_handles_quality_with_spaces(): void {
		$result = $this->negotiator->parse_accept_header( 'text/markdown; q=0.9' );

		$this->assertSame( 0.9, $result[0]['quality'] );
	}

	/**
	 * @test
	 */
	public function it_handles_empty_accept_header(): void {
		$result = $this->negotiator->parse_accept_header( '' );

		$this->assertCount( 0, $result );
	}

	/**
	 * @test
	 */
	public function it_handles_wildcard(): void {
		$result = $this->negotiator->parse_accept_header( '*/*' );

		$this->assertCount( 1, $result );
		$this->assertSame( '*/*', $result[0]['type'] );
	}

	/**
	 * @test
	 */
	public function it_supports_text_x_markdown(): void {
		$result = $this->negotiator->parse_accept_header( 'text/x-markdown' );

		$this->assertSame( 'text/x-markdown', $result[0]['type'] );
	}

	/**
	 * @test
	 */
	public function it_ignores_invalid_media_types(): void {
		$result = $this->negotiator->parse_accept_header( 'invalid, text/markdown' );

		$this->assertCount( 1, $result );
		$this->assertSame( 'text/markdown', $result[0]['type'] );
	}

	/**
	 * @test
	 */
	public function it_preserves_order_for_equal_quality(): void {
		$result = $this->negotiator->parse_accept_header( 'text/html, text/markdown' );

		$this->assertSame( 'text/html', $result[0]['type'] );
		$this->assertSame( 'text/markdown', $result[1]['type'] );
	}

	/**
	 * @test
	 */
	public function it_handles_complex_real_world_header(): void {
		$result = $this->negotiator->parse_accept_header(
			'text/markdown, text/html;q=0.9, application/xhtml+xml;q=0.9, */*;q=0.1'
		);

		$this->assertSame( 'text/markdown', $result[0]['type'] );
	}

	/**
	 * @test
	 */
	public function it_detects_wants_markdown_from_server_var(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/markdown';

		// Mock apply_filters for the hook.
		\Brain\Monkey\Filters\expectApplied( 'jetstaa_mna_accept_header' )
			->once()
			->andReturnFirstArg();

		$negotiator = new ContentNegotiator();
		$this->assertTrue( $negotiator->wants_markdown() );
		$this->assertSame( 'text/markdown', $negotiator->get_media_type() );

		unset( $_SERVER['HTTP_ACCEPT'] );
	}

	/**
	 * @test
	 */
	public function it_rejects_when_html_preferred(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/html, text/markdown;q=0.5';

		\Brain\Monkey\Filters\expectApplied( 'jetstaa_mna_accept_header' )
			->once()
			->andReturnFirstArg();

		$negotiator = new ContentNegotiator();
		$this->assertFalse( $negotiator->wants_markdown() );

		unset( $_SERVER['HTTP_ACCEPT'] );
	}

	/**
	 * @test
	 */
	public function it_caches_result_for_same_request(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/markdown';

		\Brain\Monkey\Filters\expectApplied( 'jetstaa_mna_accept_header' )
			->once()
			->andReturnFirstArg();

		$negotiator = new ContentNegotiator();

		// Call twice - filter should only be applied once.
		$negotiator->wants_markdown();
		$negotiator->wants_markdown();

		unset( $_SERVER['HTTP_ACCEPT'] );
	}

	/**
	 * @test
	 */
	public function it_can_reset_cached_result(): void {
		$_SERVER['HTTP_ACCEPT'] = 'text/markdown';

		\Brain\Monkey\Filters\expectApplied( 'jetstaa_mna_accept_header' )
			->twice()
			->andReturnFirstArg();

		$negotiator = new ContentNegotiator();
		$negotiator->wants_markdown();
		$negotiator->reset();
		$negotiator->wants_markdown();

		unset( $_SERVER['HTTP_ACCEPT'] );
	}
}
