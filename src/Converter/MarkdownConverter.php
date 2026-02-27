<?php
/**
 * Markdown Converter.
 *
 * Primary converter implementation using league/html-to-markdown.
 *
 * ## Why league/html-to-markdown?
 *
 * - Most mature PHP HTML-to-Markdown library (5M+ downloads)
 * - Active maintenance and PHP 8.x support
 * - Extensible converter architecture
 * - Handles edge cases well (nested lists, tables, etc.)
 * - Alternatives considered:
 *   - markdownify: Abandoned, PHP 5 era
 *   - html2text: Too simple, no table support
 *   - Custom DOMDocument parser: Too much maintenance burden
 *
 * @package IlloDev\MarkdownNegotiation\Converter
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Converter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use IlloDev\MarkdownNegotiation\Contracts\ConverterInterface;
use League\HTMLToMarkdown\HtmlConverter;
use League\HTMLToMarkdown\Converter\TableConverter;

/**
 * Class MarkdownConverter
 *
 * Converts WordPress HTML content to well-formatted Markdown.
 */
final class MarkdownConverter implements ConverterInterface {

	/**
	 * The league HTML-to-Markdown converter instance.
	 *
	 * @var HtmlConverter
	 */
	private HtmlConverter $html_converter;

	/**
	 * Constructor.
	 *
	 * @param ContentExtractor $extractor Content extraction service.
	 */
	public function __construct(
		private readonly ContentExtractor $extractor,
	) {
		$this->initialize_converter();
	}

	/**
	 * Initialize the HTML-to-Markdown converter with custom options.
	 *
	 * @return void
	 */
	private function initialize_converter(): void {
		$this->html_converter = new HtmlConverter( array(
			'header_style'    => 'atx',         // Use # style headers.
			'strip_tags'      => true,          // Strip unknown tags.
			'remove_nodes'    => 'script style nav footer header aside form input button select textarea',
			'hard_break'      => false,         // Use double newline for paragraphs.
			'list_item_style' => '-',           // Use - for unordered lists.
			'bold_style'      => '**',          // Use ** for bold.
			'italic_style'    => '*',           // Use * for italic.
			'strip_placeholder_links' => true,  // Remove empty links.
		) );

		// Register the table converter.
		$this->html_converter->getEnvironment()->addConverter( new TableConverter() );

		/**
		 * Action to allow extending the HTML converter.
		 *
		 * @param HtmlConverter $converter The league converter instance.
		 */
		do_action( 'jetstaa_mna_configure_converter', $this->html_converter );
	}

	/**
	 * {@inheritDoc}
	 */
	public function convert( string $html, array $options = array() ): string {
		// If a WP_Post object is available, extract content properly.
		$post = $options['post'] ?? null;

		if ( $post instanceof \WP_Post ) {
			$html = $this->extractor->extract( $post, $options );
		}

		// Convert HTML to Markdown.
		$markdown = $this->html_converter->convert( $html );

		// Post-process the Markdown.
		$markdown = $this->post_process( $markdown );

		/**
		 * Filter the generated Markdown after conversion.
		 *
		 * @param string       $markdown The converted Markdown.
		 * @param string       $html     The source HTML.
		 * @param array        $options  Conversion options.
		 * @param \WP_Post|null $post    The source post, if available.
		 */
		return apply_filters( 'jetstaa_mna_converted_markdown', $markdown, $html, $options, $post );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return class_exists( HtmlConverter::class );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'league/html-to-markdown';
	}

	/**
	 * Post-process converted Markdown.
	 *
	 * Cleans up common conversion artifacts.
	 *
	 * @param string $markdown Raw converted Markdown.
	 *
	 * @return string Cleaned Markdown.
	 */
	private function post_process( string $markdown ): string {
		// Remove excessive blank lines (more than 2 consecutive).
		$markdown = preg_replace( '/\n{3,}/', "\n\n", $markdown );

		// Clean up leading/trailing whitespace.
		$markdown = trim( $markdown );

		// Ensure the document ends with a newline.
		$markdown .= "\n";

		// Fix broken link references.
		$markdown = preg_replace( '/\[([^\]]+)\]\(\s*\)/', '$1', $markdown );

		// Remove HTML comments that may have survived.
		$markdown = preg_replace( '/<!--[\s\S]*?-->/', '', $markdown );

		// Clean up any remaining HTML entities.
		$markdown = html_entity_decode( $markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Re-encode ampersands that should remain.
		$markdown = str_replace( '&', '&amp;', $markdown );
		$markdown = str_replace( '&amp;amp;', '&amp;', $markdown );

		return $markdown;
	}

	/**
	 * Estimate token count for the converted Markdown.
	 *
	 * Uses a simple heuristic: ~4 characters per token for English text.
	 * This is adequate for the X-Markdown-Tokens header.
	 *
	 * @param string $markdown The Markdown content.
	 *
	 * @return int Estimated token count.
	 */
	public function estimate_tokens( string $markdown ): int {
		// Rough GPT tokenizer approximation: ~4 chars per token for English.
		// For multilingual content, this may vary.
		$char_count = mb_strlen( $markdown, 'UTF-8' );

		return (int) ceil( $char_count / 4 );
	}
}
