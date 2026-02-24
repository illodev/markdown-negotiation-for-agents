<?php
/**
 * Gutenberg Block Processor.
 *
 * Processes Gutenberg block markup to produce clean HTML
 * suitable for Markdown conversion.
 *
 * @package IlloDev\MarkdownNegotiation\Converter
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Converter;

/**
 * Class GutenbergProcessor
 *
 * Handles Gutenberg-specific block processing before conversion.
 */
final class GutenbergProcessor {

	/**
	 * Blocks that should be skipped during conversion.
	 *
	 * @var array<string>
	 */
	private const SKIP_BLOCKS = array(
		'core/navigation',
		'core/spacer',
		'core/separator',
		'core/buttons',
		'core/button',
		'core/social-links',
		'core/social-link',
		'core/search',
		'core/loginout',
		'core/post-navigation-link',
		'core/site-logo',
		'core/site-title',
		'core/site-tagline',
		'core/template-part',
		'core/widget-area',
	);

	/**
	 * Process Gutenberg content into clean HTML.
	 *
	 * @param string $content Raw post content (may contain block markup).
	 *
	 * @return string Processed HTML content.
	 */
	public function process( string $content ): string {
		// If not using block editor, return as-is.
		if ( ! function_exists( 'has_blocks' ) || ! has_blocks( $content ) ) {
			return $content;
		}

		$blocks = parse_blocks( $content );
		$output = '';

		foreach ( $blocks as $block ) {
			$output .= $this->render_block( $block );
		}

		return $output;
	}

	/**
	 * Render a single block to HTML, skipping non-content blocks.
	 *
	 * @param array $block Block data.
	 *
	 * @return string Rendered HTML.
	 */
	private function render_block( array $block ): string {
		// Skip empty blocks.
		if ( empty( $block['blockName'] ) && empty( trim( $block['innerHTML'] ?? '' ) ) ) {
			return '';
		}

		// Skip non-content blocks.
		$block_name = $block['blockName'] ?? '';
		if ( in_array( $block_name, self::SKIP_BLOCKS, true ) ) {
			return '';
		}

		/**
		 * Filter whether a specific block should be skipped.
		 *
		 * @param bool   $skip  Whether to skip this block.
		 * @param string $name  Block name.
		 * @param array  $block Full block data.
		 */
		if ( apply_filters( 'jetstaa_mna_skip_block', false, $block_name, $block ) ) {
			return '';
		}

		// Handle specific block types.
		$html = match ( true ) {
			str_starts_with( $block_name, 'core/code' )     => $this->process_code_block( $block ),
			str_starts_with( $block_name, 'core/table' )    => $this->process_table_block( $block ),
			str_starts_with( $block_name, 'core/image' )    => $this->process_image_block( $block ),
			str_starts_with( $block_name, 'core/gallery' )  => $this->process_gallery_block( $block ),
			str_starts_with( $block_name, 'core/embed' )    => $this->process_embed_block( $block ),
			str_starts_with( $block_name, 'core/columns' )  => $this->process_columns_block( $block ),
			str_starts_with( $block_name, 'core/group' )    => $this->process_group_block( $block ),
			default                                          => render_block( $block ),
		};

		/**
		 * Filter the rendered HTML for a block before markdown conversion.
		 *
		 * @param string $html  Rendered HTML.
		 * @param string $name  Block name.
		 * @param array  $block Full block data.
		 */
		return apply_filters( 'jetstaa_mna_block_html', $html, $block_name, $block );
	}

	/**
	 * Process a code block preserving language hints.
	 *
	 * @param array $block Block data.
	 *
	 * @return string HTML with language class preserved.
	 */
	private function process_code_block( array $block ): string {
		$html     = render_block( $block );
		$language = $block['attrs']['language'] ?? '';

		if ( $language ) {
			// Ensure the language class is present for markdown code fence detection.
			$html = preg_replace(
				'/<code/',
				'<code class="language-' . esc_attr( $language ) . '"',
				$html,
				1
			);
		}

		return $html;
	}

	/**
	 * Process a table block.
	 *
	 * @param array $block Block data.
	 *
	 * @return string HTML table.
	 */
	private function process_table_block( array $block ): string {
		return render_block( $block );
	}

	/**
	 * Process an image block preserving alt text.
	 *
	 * @param array $block Block data.
	 *
	 * @return string HTML image.
	 */
	private function process_image_block( array $block ): string {
		return render_block( $block );
	}

	/**
	 * Process a gallery block as individual images.
	 *
	 * @param array $block Block data.
	 *
	 * @return string HTML images.
	 */
	private function process_gallery_block( array $block ): string {
		$html = '';

		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				$html .= $this->render_block( $inner_block );
			}
		} else {
			$html = render_block( $block );
		}

		return $html;
	}

	/**
	 * Process an embed block.
	 *
	 * @param array $block Block data.
	 *
	 * @return string Embed URL as link.
	 */
	private function process_embed_block( array $block ): string {
		$url = $block['attrs']['url'] ?? '';

		if ( $url ) {
			return sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( $url ),
				esc_html( $url )
			);
		}

		return render_block( $block );
	}

	/**
	 * Process a columns block by rendering inner blocks.
	 *
	 * @param array $block Block data.
	 *
	 * @return string HTML from inner blocks.
	 */
	private function process_columns_block( array $block ): string {
		$html = '';

		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $column ) {
				if ( ! empty( $column['innerBlocks'] ) ) {
					foreach ( $column['innerBlocks'] as $inner_block ) {
						$html .= $this->render_block( $inner_block );
					}
				}
			}
		}

		return $html ?: render_block( $block );
	}

	/**
	 * Process a group block by rendering inner blocks.
	 *
	 * @param array $block Block data.
	 *
	 * @return string HTML from inner blocks.
	 */
	private function process_group_block( array $block ): string {
		$html = '';

		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				$html .= $this->render_block( $inner_block );
			}
		}

		return $html ?: render_block( $block );
	}
}
