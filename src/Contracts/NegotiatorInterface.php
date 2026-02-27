<?php
/**
 * Negotiator interface.
 *
 * Defines the contract for HTTP content negotiation.
 *
 * @package IlloDev\MarkdownNegotiation\Contracts
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface NegotiatorInterface
 *
 * Abstraction for HTTP content negotiation logic.
 */
interface NegotiatorInterface {

	/**
	 * Determine if the current request wants Markdown.
	 *
	 * Parses the Accept header and checks for text/markdown
	 * or text/x-markdown media types.
	 *
	 * @return bool True if Markdown is requested.
	 */
	public function wants_markdown(): bool;

	/**
	 * Get the negotiated media type.
	 *
	 * @return string The matched media type (e.g., 'text/markdown').
	 */
	public function get_media_type(): string;

	/**
	 * Parse an Accept header value into structured media types.
	 *
	 * @param string $accept_header The raw Accept header value.
	 *
	 * @return array<array{type: string, quality: float}> Parsed media types sorted by quality.
	 */
	public function parse_accept_header( string $accept_header ): array;
}
