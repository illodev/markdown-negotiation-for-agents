<?php
/**
 * Header Validator.
 *
 * Validates and sanitizes HTTP headers for security.
 *
 * @package IlloDev\MarkdownNegotiation\Http
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Http;

/**
 * Class HeaderValidator
 *
 * Security-focused validation for HTTP headers.
 */
final class HeaderValidator {

	/**
	 * Maximum allowed Accept header length.
	 *
	 * Protects against excessively long header attacks.
	 *
	 * @var int
	 */
	private const MAX_ACCEPT_LENGTH = 1024;

	/**
	 * Validate the Accept header.
	 *
	 * @param string $accept_header The raw Accept header value.
	 *
	 * @return bool True if the header is valid.
	 */
	public function validate_accept_header( string $accept_header ): bool {
		// Check length.
		if ( strlen( $accept_header ) > self::MAX_ACCEPT_LENGTH ) {
			return false;
		}

		// Check for null bytes.
		if ( str_contains( $accept_header, "\0" ) ) {
			return false;
		}

		// Check for invalid characters (only allow printable ASCII).
		if ( preg_match( '/[^\x20-\x7E]/', $accept_header ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate that the request is safe to serve markdown.
	 *
	 * Checks for common attack patterns.
	 *
	 * @return bool True if the request appears safe.
	 */
	public function validate_request(): bool {
		// Check for suspicious Accept header injection attempts.
		$accept = $_SERVER['HTTP_ACCEPT'] ?? '';

		if ( ! $this->validate_accept_header( $accept ) ) {
			return false;
		}

		return true;
	}
}
