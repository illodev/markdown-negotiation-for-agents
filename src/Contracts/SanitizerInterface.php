<?php
/**
 * Sanitizer interface.
 *
 * Defines the contract for content sanitization before markdown conversion.
 *
 * @package IlloDev\MarkdownNegotiation\Contracts
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface SanitizerInterface
 *
 * Abstraction for sanitizing content before and after conversion.
 */
interface SanitizerInterface {

	/**
	 * Sanitize HTML content before conversion.
	 *
	 * Strips scripts, styles, navigation, sidebars, and other
	 * non-content elements.
	 *
	 * @param string $html Raw HTML content.
	 *
	 * @return string Sanitized HTML.
	 */
	public function sanitize_html( string $html ): string;

	/**
	 * Sanitize markdown output after conversion.
	 *
	 * Removes any remaining unsafe content from the markdown.
	 *
	 * @param string $markdown Raw markdown output.
	 *
	 * @return string Sanitized markdown.
	 */
	public function sanitize_markdown( string $markdown ): string;
}
