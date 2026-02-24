<?php
/**
 * Content Negotiator.
 *
 * Parses HTTP Accept headers according to RFC 7231 Section 5.3.2.
 * Determines if the client wants Markdown content.
 *
 * @package IlloDev\MarkdownNegotiation\Http
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Http;

use IlloDev\MarkdownNegotiation\Contracts\NegotiatorInterface;

/**
 * Class ContentNegotiator
 *
 * RFC 7231 compliant Accept header parser for content negotiation.
 */
final class ContentNegotiator implements NegotiatorInterface {

	/**
	 * Supported Markdown media types.
	 *
	 * @var array<string>
	 */
	private const MARKDOWN_TYPES = array(
		'text/markdown',
		'text/x-markdown',
	);

	/**
	 * The negotiated media type.
	 *
	 * @var string
	 */
	private string $negotiated_type = '';

	/**
	 * Whether the result has been cached for this request.
	 *
	 * @var bool|null
	 */
	private ?bool $cached_result = null;

	/**
	 * {@inheritDoc}
	 */
	public function wants_markdown(): bool {
		if ( null !== $this->cached_result ) {
			return $this->cached_result;
		}

		$accept = $this->get_accept_header();

		if ( empty( $accept ) ) {
			$this->cached_result = false;
			return false;
		}

		$media_types = $this->parse_accept_header( $accept );

		foreach ( $media_types as $type ) {
			if ( in_array( $type['type'], self::MARKDOWN_TYPES, true ) ) {
				$this->negotiated_type = $type['type'];
				$this->cached_result   = true;
				return true;
			}

			// If text/html or */* comes first with higher quality, don't serve markdown.
			if ( 'text/html' === $type['type'] || '*/*' === $type['type'] ) {
				break;
			}
		}

		$this->cached_result = false;
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_media_type(): string {
		if ( null === $this->cached_result ) {
			$this->wants_markdown();
		}

		return $this->negotiated_type ?: 'text/markdown';
	}

	/**
	 * {@inheritDoc}
	 */
	public function parse_accept_header( string $accept_header ): array {
		$types = array();

		// Split by comma, handling potential whitespace.
		$parts = array_map( 'trim', explode( ',', $accept_header ) );

		foreach ( $parts as $part ) {
			if ( empty( $part ) ) {
				continue;
			}

			// Split media type and parameters.
			$segments = array_map( 'trim', explode( ';', $part ) );
			$type     = strtolower( $segments[0] );

			// Validate media type format.
			if ( ! preg_match( '#^[a-z*]+/[a-z0-9.*+\-]+$#', $type ) ) {
				continue;
			}

			// Parse quality value.
			$quality = 1.0;
			for ( $i = 1; $i < count( $segments ); $i++ ) {
				if ( preg_match( '/^q\s*=\s*([01](?:\.\d{0,3})?)$/', $segments[ $i ], $matches ) ) {
					$quality = (float) $matches[1];
					break;
				}
			}

			$types[] = array(
				'type'    => $type,
				'quality' => $quality,
			);
		}

		// Sort by quality descending, preserving order for equal quality.
		usort( $types, static function ( array $a, array $b ): int {
			if ( $a['quality'] === $b['quality'] ) {
				return 0;
			}
			return $a['quality'] > $b['quality'] ? -1 : 1;
		} );

		return $types;
	}

	/**
	 * Get the Accept header from the current request.
	 *
	 * @return string The Accept header value.
	 */
	private function get_accept_header(): string {
		// Try standard $_SERVER.
		$accept = $_SERVER['HTTP_ACCEPT'] ?? '';

		/**
		 * Filter the Accept header value.
		 *
		 * Useful for testing or custom header sources.
		 *
		 * @param string $accept The Accept header value.
		 */
		return apply_filters( 'jetstaa_mna_accept_header', (string) $accept );
	}

	/**
	 * Reset the cached result (for testing or multiple evaluations).
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->cached_result   = null;
		$this->negotiated_type = '';
	}
}
