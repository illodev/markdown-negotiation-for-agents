<?php
/**
 * Content Sanitizer.
 *
 * Strips non-content HTML elements and sanitizes output
 * to prevent information leakage and XSS via Markdown.
 *
 * ## Security Considerations
 *
 * Even though Markdown is plain text, malicious content could:
 * - Leak private data via hidden HTML elements
 * - Contain JavaScript in HTML comments
 * - Include tracking pixels
 * - Expose admin-only UI elements
 *
 * @package IlloDev\MarkdownNegotiation\Security
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use IlloDev\MarkdownNegotiation\Contracts\SanitizerInterface;

/**
 * Class Sanitizer
 *
 * Comprehensive HTML and Markdown sanitization.
 */
final class Sanitizer implements SanitizerInterface {

	/**
	 * HTML tags to completely strip (including content).
	 *
	 * @var array<string>
	 */
	private const STRIP_TAGS = array(
		'script',
		'style',
		'noscript',
		'iframe',
		'object',
		'embed',
		'applet',
		'form',
		'input',
		'select',
		'textarea',
		'button',
		'nav',
		'header',
		'footer',
		'aside',
		'svg',
		'canvas',
		'video',
		'audio',
		'source',
		'track',
	);

	/**
	 * CSS class patterns indicating non-content elements.
	 *
	 * @var array<string>
	 */
	private const NON_CONTENT_CLASSES = array(
		'sidebar',
		'widget',
		'menu',
		'nav',
		'navigation',
		'breadcrumb',
		'pagination',
		'social-share',
		'share-buttons',
		'related-posts',
		'comments',
		'comment-form',
		'cookie-notice',
		'popup',
		'modal',
		'advertisement',
		'ad-container',
		'screen-reader-text',
		'sr-only',
		'visually-hidden',
		'wp-block-navigation',
		'wp-block-search',
	);

	/**
	 * {@inheritDoc}
	 */
	public function sanitize_html( string $html ): string {
		if ( empty( $html ) ) {
			return '';
		}

		// Step 1: Remove HTML comments.
		$html = preg_replace( '/<!--[\s\S]*?-->/', '', $html );

		// Step 2: Remove script/style tags and their content.
		foreach ( self::STRIP_TAGS as $tag ) {
			$html = preg_replace(
				sprintf( '/<\s*%1$s\b[^>]*>[\s\S]*?<\s*\/\s*%1$s\s*>/i', preg_quote( $tag, '/' ) ),
				'',
				$html
			);
			// Also remove self-closing variants.
			$html = preg_replace(
				sprintf( '/<\s*%s\b[^>]*\/?>/i', preg_quote( $tag, '/' ) ),
				'',
				$html
			);
		}

		// Step 3: Remove elements with non-content classes.
		foreach ( self::NON_CONTENT_CLASSES as $class ) {
			$html = preg_replace(
				sprintf( '/<[^>]+class\s*=\s*"[^"]*\b%s\b[^"]*"[^>]*>[\s\S]*?<\/[^>]+>/i', preg_quote( $class, '/' ) ),
				'',
				$html
			);
		}

		// Step 4: Remove inline styles.
		$html = preg_replace( '/\s+style\s*=\s*"[^"]*"/i', '', $html );
		$html = preg_replace( "/\s+style\s*=\s*'[^']*'/i", '', $html );

		// Step 5: Remove inline event handlers (onclick, onload, etc.).
		$html = preg_replace( '/\s+on\w+\s*=\s*"[^"]*"/i', '', $html );
		$html = preg_replace( "/\s+on\w+\s*=\s*'[^']*'/i", '', $html );

		// Step 6: Remove data-* attributes (may contain tracking info).
		$html = preg_replace( '/\s+data-[\w-]+\s*=\s*"[^"]*"/i', '', $html );

		// Step 7: Remove aria-* attributes (not relevant for Markdown).
		$html = preg_replace( '/\s+aria-[\w-]+\s*=\s*"[^"]*"/i', '', $html );

		// Step 8: Remove role attributes.
		$html = preg_replace( '/\s+role\s*=\s*"[^"]*"/i', '', $html );

		// Step 9: Sanitize image sources (remove tracking pixels).
		$html = preg_replace(
			'/<img[^>]+(width|height)\s*=\s*"[01]"[^>]*>/i',
			'',
			$html
		);

		// Step 10: Remove empty elements that may remain.
		$html = preg_replace( '/<(div|span|p)\b[^>]*>\s*<\/\1>/i', '', $html );

		/**
		 * Filter the sanitized HTML.
		 *
		 * @param string $html The sanitized HTML.
		 */
		return apply_filters( 'jetstaa_mna_sanitized_html', $html );
	}

	/**
	 * {@inheritDoc}
	 */
	public function sanitize_markdown( string $markdown ): string {
		if ( empty( $markdown ) ) {
			return '';
		}

		// Remove any remaining HTML tags.
		$markdown = preg_replace( '/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $markdown );
		$markdown = preg_replace( '/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $markdown );

		// Remove JavaScript URLs.
		$markdown = preg_replace( '/\[([^\]]*)\]\(javascript:[^)]*\)/i', '$1', $markdown );

		// Remove data: URLs (except images).
		$markdown = preg_replace( '/\[([^\]]*)\]\(data:(?!image\/)[^)]*\)/i', '$1', $markdown );

		// Remove mailto: links with suspicious patterns.
		$markdown = preg_replace( '/\[([^\]]*)\]\(mailto:[^)]{200,}\)/i', '$1', $markdown );

		// Remove any null bytes.
		$markdown = str_replace( "\0", '', $markdown );

		/**
		 * Filter the sanitized Markdown.
		 *
		 * @param string $markdown The sanitized Markdown.
		 */
		return apply_filters( 'jetstaa_mna_sanitized_markdown', $markdown );
	}
}
