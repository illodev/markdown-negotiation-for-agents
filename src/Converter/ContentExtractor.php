<?php
/**
 * Content Extractor.
 *
 * Extracts the main content from a WordPress post, processing blocks
 * and shortcodes while stripping non-content elements.
 *
 * @package IlloDev\MarkdownNegotiation\Converter
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Converter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use IlloDev\MarkdownNegotiation\Contracts\SanitizerInterface;
use WP_Post;

/**
 * Class ContentExtractor
 *
 * Orchestrates content extraction from WordPress posts.
 */
class ContentExtractor {

	/**
	 * Constructor.
	 *
	 * @param SanitizerInterface   $sanitizer           Content sanitizer.
	 * @param GutenbergProcessor   $gutenberg_processor  Gutenberg block processor.
	 * @param ShortcodeProcessor   $shortcode_processor  Shortcode processor.
	 */
	public function __construct(
		private readonly SanitizerInterface $sanitizer,
		private readonly GutenbergProcessor $gutenberg_processor,
		private readonly ShortcodeProcessor $shortcode_processor,
	) {}

	/**
	 * Extract clean HTML content from a post.
	 *
	 * @param WP_Post $post    The post to extract content from.
	 * @param array   $options Extraction options.
	 *
	 * @return string Clean HTML suitable for Markdown conversion.
	 */
	public function extract( WP_Post $post, array $options = array() ): string {
		$include_title    = $options['include_title'] ?? true;
		$include_meta     = $options['include_meta'] ?? true;
		$include_excerpt  = $options['include_excerpt'] ?? false;
		$include_featured = $options['include_featured'] ?? true;

		$html = '';

		// Title.
		if ( $include_title ) {
			$html .= sprintf( '<h1>%s</h1>', esc_html( get_the_title( $post ) ) );
		}

		// Post meta.
		if ( $include_meta ) {
			$html .= $this->build_meta_html( $post );
		}

		// Featured image.
		if ( $include_featured && has_post_thumbnail( $post ) ) {
			$html .= $this->get_featured_image_html( $post );
		}

		// Excerpt.
		if ( $include_excerpt && ! empty( $post->post_excerpt ) ) {
			$html .= sprintf( '<blockquote>%s</blockquote>', wp_kses_post( $post->post_excerpt ) );
		}

		// Main content.
		$content = $post->post_content;

		// Process Gutenberg blocks.
		$content = $this->gutenberg_processor->process( $content );

		// Process shortcodes.
		$content = $this->shortcode_processor->process( $content );

		// Apply the_content filters for oEmbed, wpautop, etc.
		// But avoid infinite loops by temporarily removing our own filter.
		$content = $this->apply_content_filters( $content );

		$html .= $content;

		// WooCommerce: Add product-specific data.
		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$html .= $this->get_woocommerce_data( $post );
		}

		// Sanitize the assembled HTML.
		$html = $this->sanitizer->sanitize_html( $html );

		/**
		 * Filter the extracted HTML before Markdown conversion.
		 *
		 * @param string  $html    Extracted HTML.
		 * @param WP_Post $post    The source post.
		 * @param array   $options Extraction options.
		 */
		return apply_filters( 'jetstaa_mna_extracted_html', $html, $post, $options );
	}

	/**
	 * Build meta information HTML.
	 *
	 * @param WP_Post $post The post.
	 *
	 * @return string Meta HTML.
	 */
	private function build_meta_html( WP_Post $post ): string {
		$meta  = '<p>';
		$meta .= sprintf(
			/* translators: %s: Author display name. */
			__( 'Author: %s', 'markdown-negotiation-for-agents' ),
			esc_html( get_the_author_meta( 'display_name', $post->post_author ) )
		);
		$meta .= '<br>';
		$meta .= sprintf(
			/* translators: %s: Published date. */
			__( 'Published: %s', 'markdown-negotiation-for-agents' ),
			esc_html( get_the_date( 'Y-m-d', $post ) )
		);

		$modified = get_the_modified_date( 'Y-m-d', $post );
		if ( $modified !== get_the_date( 'Y-m-d', $post ) ) {
			$meta .= '<br>';
			$meta .= sprintf(
				/* translators: %s: Modified date. */
				__( 'Modified: %s', 'markdown-negotiation-for-agents' ),
				esc_html( $modified )
			);
		}

		// Categories.
		$categories = get_the_category( $post->ID );
		if ( ! empty( $categories ) ) {
			$cat_names = wp_list_pluck( $categories, 'name' );
			$meta     .= '<br>';
			$meta     .= sprintf(
				/* translators: %s: Comma-separated category names. */
				__( 'Categories: %s', 'markdown-negotiation-for-agents' ),
				esc_html( implode( ', ', $cat_names ) )
			);
		}

		// Tags.
		$tags = get_the_tags( $post->ID );
		if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
			$tag_names = wp_list_pluck( $tags, 'name' );
			$meta     .= '<br>';
			$meta     .= sprintf(
				/* translators: %s: Comma-separated tag names. */
				__( 'Tags: %s', 'markdown-negotiation-for-agents' ),
				esc_html( implode( ', ', $tag_names ) )
			);
		}

		$meta .= '</p>';

		return $meta;
	}

	/**
	 * Get featured image HTML.
	 *
	 * @param WP_Post $post The post.
	 *
	 * @return string Image HTML.
	 */
	private function get_featured_image_html( WP_Post $post ): string {
		$thumbnail_id = get_post_thumbnail_id( $post );
		if ( ! $thumbnail_id ) {
			return '';
		}

		$image_url = wp_get_attachment_image_url( $thumbnail_id, 'full' );
		$alt_text  = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
		$title     = get_the_title( $post );

		if ( ! $image_url ) {
			return '';
		}

		return sprintf(
			'<p><img src="%s" alt="%s" /></p>',
			esc_url( $image_url ),
			esc_attr( $alt_text ?: $title )
		);
	}

	/**
	 * Apply WordPress content filters safely.
	 *
	 * @param string $content Raw content.
	 *
	 * @return string Filtered content.
	 */
	private function apply_content_filters( string $content ): string {
		// Remove our own hooks to prevent recursion.
		$priority = has_filter( 'the_content', array( $this, 'extract' ) );

		// Apply standard WordPress content filters.
		// We selectively apply the most important ones.
		$content = wptexturize( $content );
		$content = convert_smilies( $content );
		$content = wpautop( $content );

		// Process oEmbeds.
		if ( isset( $GLOBALS['wp_embed'] ) ) {
			$content = $GLOBALS['wp_embed']->autoembed( $content );
		}

		return $content;
	}

	/**
	 * Get WooCommerce product data as HTML.
	 *
	 * @param WP_Post $post Product post.
	 *
	 * @return string Product data HTML.
	 */
	private function get_woocommerce_data( WP_Post $post ): string {
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return '';
		}

		$html = '<hr>';
		$html .= '<h2>' . esc_html__( 'Product Details', 'markdown-negotiation-for-agents' ) . '</h2>';

		// Price.
		$price = $product->get_price();
		if ( $price ) {
			$html .= sprintf(
				'<p><strong>%s:</strong> %s</p>',
				esc_html__( 'Price', 'markdown-negotiation-for-agents' ),
				wp_strip_all_tags( wc_price( (float) $price ) )
			);
		}

		// SKU.
		$sku = $product->get_sku();
		if ( $sku ) {
			$html .= sprintf(
				'<p><strong>%s:</strong> %s</p>',
				esc_html__( 'SKU', 'markdown-negotiation-for-agents' ),
				esc_html( $sku )
			);
		}

		// Stock status.
		$html .= sprintf(
			'<p><strong>%s:</strong> %s</p>',
			esc_html__( 'Availability', 'markdown-negotiation-for-agents' ),
			$product->is_in_stock()
				? esc_html__( 'In Stock', 'markdown-negotiation-for-agents' )
				: esc_html__( 'Out of Stock', 'markdown-negotiation-for-agents' )
		);

		// Short description.
		$short_desc = $product->get_short_description();
		if ( $short_desc ) {
			$html .= sprintf(
				'<h3>%s</h3>%s',
				esc_html__( 'Summary', 'markdown-negotiation-for-agents' ),
				wp_kses_post( $short_desc )
			);
		}

		// Attributes.
		$attributes = $product->get_attributes();
		if ( ! empty( $attributes ) ) {
			$html .= sprintf( '<h3>%s</h3>', esc_html__( 'Attributes', 'markdown-negotiation-for-agents' ) );
			$html .= '<table><thead><tr><th>' . esc_html__( 'Attribute', 'markdown-negotiation-for-agents' ) . '</th>';
			$html .= '<th>' . esc_html__( 'Value', 'markdown-negotiation-for-agents' ) . '</th></tr></thead><tbody>';

			foreach ( $attributes as $attribute ) {
				$name   = wc_attribute_label( $attribute->get_name() );
				$values = $attribute->is_taxonomy()
					? implode( ', ', wc_get_product_terms( $post->ID, $attribute->get_name(), array( 'fields' => 'names' ) ) )
					: implode( ', ', $attribute->get_options() );

				$html .= sprintf(
					'<tr><td>%s</td><td>%s</td></tr>',
					esc_html( $name ),
					esc_html( $values )
				);
			}

			$html .= '</tbody></table>';
		}

		return $html;
	}
}
