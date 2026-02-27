<?php
/**
 * Rate Limiter.
 *
 * Limits Markdown requests per IP address to prevent abuse.
 * Uses transients for portability across hosting environments.
 *
 * @package IlloDev\MarkdownNegotiation\Security
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RateLimiter
 *
 * Simple sliding window rate limiter for Markdown requests.
 */
final class RateLimiter {

	/**
	 * Transient prefix for rate limit tracking.
	 *
	 * @var string
	 */
	private const PREFIX = 'jetstaa_mna_rl_';

	/**
	 * Constructor.
	 *
	 * @param int $max_requests Maximum requests per window.
	 * @param int $window       Window duration in seconds.
	 */
	public function __construct(
		private readonly int $max_requests,
		private readonly int $window,
	) {}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'template_redirect', array( $this, 'check_rate_limit' ), 0 );
	}

	/**
	 * Check the rate limit for the current request.
	 *
	 * Only applies to Markdown requests (detected by Accept header).
	 *
	 * @return void
	 */
	public function check_rate_limit(): void {
		// Only rate-limit Markdown requests.
		$accept = isset( $_SERVER['HTTP_ACCEPT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) )
			: '';
		if ( ! str_contains( $accept, 'text/markdown' ) && ! str_contains( $accept, 'text/x-markdown' ) ) {
			return;
		}

		if ( ! $this->is_allowed() ) {
			$this->send_rate_limit_response();
		}
	}

	/**
	 * Check if the current request is within rate limits.
	 *
	 * @return bool True if allowed.
	 */
	public function is_allowed(): bool {
		$ip  = $this->get_client_ip();
		$key = self::PREFIX . md5( $ip );

		$data = get_transient( $key );

		if ( false === $data ) {
			// First request in window.
			set_transient( $key, array(
				'count'  => 1,
				'start'  => time(),
			), $this->window );

			return true;
		}

		$data = (array) $data;

		// Check if window has expired.
		$elapsed = time() - ( $data['start'] ?? 0 );
		if ( $elapsed >= $this->window ) {
			// Reset window.
			set_transient( $key, array(
				'count'  => 1,
				'start'  => time(),
			), $this->window );

			return true;
		}

		// Increment counter.
		$count = ( $data['count'] ?? 0 ) + 1;

		if ( $count > $this->max_requests ) {
			return false;
		}

		$data['count'] = $count;
		set_transient( $key, $data, $this->window - $elapsed );

		return true;
	}

	/**
	 * Send a 429 Too Many Requests response.
	 *
	 * @return void
	 */
	private function send_rate_limit_response(): void {
		http_response_code( 429 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( sprintf( 'Retry-After: %d', $this->window ) );
		header( sprintf( 'X-RateLimit-Limit: %d', $this->max_requests ) );
		header( 'X-RateLimit-Remaining: 0' );

		echo 'Rate limit exceeded. Please try again later.';
		exit;
	}

	/**
	 * Get the client's IP address.
	 *
	 * Handles common proxy headers safely.
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip(): string {
		// Check common proxy headers (trusted environments only).
		// In production, this should be configured based on the reverse proxy setup.
		$headers = array(
			'HTTP_CF_CONNECTING_IP',      // Cloudflare.
			'HTTP_X_FORWARDED_FOR',       // Standard proxy.
			'HTTP_X_REAL_IP',             // nginx.
		);

		/**
		 * Filter the list of trusted proxy headers.
		 *
		 * @param array $headers List of $_SERVER keys to check.
		 */
		$headers = apply_filters( 'jetstaa_mna_trusted_proxy_headers', $headers );

		foreach ( $headers as $header ) {
			$value = isset( $_SERVER[ $header ] )
				? sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) )
				: '';
			if ( ! empty( $value ) ) {
				// X-Forwarded-For may contain multiple IPs; use the first.
				$ip = trim( explode( ',', $value )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '127.0.0.1';
	}
}
