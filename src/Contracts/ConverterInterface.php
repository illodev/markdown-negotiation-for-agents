<?php
/**
 * Converter interface.
 *
 * Defines the contract for HTML-to-Markdown conversion implementations.
 *
 * @package IlloDev\MarkdownNegotiation\Contracts
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface ConverterInterface
 *
 * Abstraction layer for HTML-to-Markdown conversion.
 * Allows swapping the underlying converter implementation.
 */
interface ConverterInterface {

	/**
	 * Convert HTML content to Markdown.
	 *
	 * @param string $html    The HTML content to convert.
	 * @param array  $options Optional conversion options.
	 *
	 * @return string The Markdown content.
	 */
	public function convert( string $html, array $options = array() ): string;

	/**
	 * Check if the converter is available and ready.
	 *
	 * @return bool True if the converter is ready.
	 */
	public function is_available(): bool;

	/**
	 * Get the converter's name/identifier.
	 *
	 * @return string Converter name.
	 */
	public function get_name(): string;
}
