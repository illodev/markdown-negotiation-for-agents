<?php
/**
 * Response Handler.
 *
 * Intercepts WordPress template loading and serves Markdown
 * when content negotiation determines it's appropriate.
 *
 * @package IlloDev\MarkdownNegotiation\Http
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use IlloDev\MarkdownNegotiation\Cache\CacheManager;
use IlloDev\MarkdownNegotiation\Contracts\ConverterInterface;
use IlloDev\MarkdownNegotiation\Contracts\NegotiatorInterface;
use IlloDev\MarkdownNegotiation\Converter\MarkdownConverter;
use IlloDev\MarkdownNegotiation\Security\AccessControl;
use WP_Post;

/**
 * Class ResponseHandler
 *
 * Handles serving Markdown responses for singular content.
 */
final class ResponseHandler {

	/**
	 * Constructor.
	 *
	 * @param ConverterInterface   $converter        Markdown converter.
	 * @param CacheManager         $cache_manager    Cache manager.
	 * @param NegotiatorInterface  $negotiator       Content negotiator.
	 * @param AccessControl        $access_control   Access control.
	 * @param HeaderValidator      $header_validator  Header validator.
	 * @param array<string, mixed> $settings         Plugin settings.
	 */
	public function __construct(
		private readonly ConverterInterface $converter,
		private readonly CacheManager $cache_manager,
		private readonly NegotiatorInterface $negotiator,
		private readonly AccessControl $access_control,
		private readonly HeaderValidator $header_validator,
		private readonly array $settings,
	) {}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Hook early in the template loading process.
		add_action( 'template_redirect', array( $this, 'handle_request' ), 1 );
	}

	/**
	 * Handle the incoming request.
	 *
	 * Checks if Markdown is requested and serves it.
	 *
	 * @return void
	 */
	public function handle_request(): void {
		// Only handle singular content.
		if ( ! is_singular() ) {
			return;
		}

		// Validate the request is safe.
		if ( ! $this->header_validator->validate_request() ) {
			$this->send_error_response( 400, 'Bad Request: malformed Accept header' );
			return;
		}

		// Check if Markdown is requested.
		if ( ! $this->negotiator->wants_markdown() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		// Check if this post type is allowed.
		if ( ! $this->is_post_type_allowed( $post ) ) {
			return;
		}

		// Check access control.
		if ( ! $this->access_control->can_access( $post ) ) {
			$this->send_error_response( 403, 'Access denied' );
			return;
		}

		// Serve the Markdown response.
		$this->serve_markdown( $post );
	}

	/**
	 * Serve a Markdown response for the given post.
	 *
	 * @param WP_Post $post The post to serve.
	 *
	 * @return void
	 */
	public function serve_markdown( WP_Post $post ): void {
		// Try cache first.
		$cache_key = $this->cache_manager->build_key( $post->ID );
		$markdown  = $this->cache_manager->get( $cache_key );

		if ( null === $markdown ) {
			// Convert to Markdown.
			$markdown = $this->converter->convert( '', array( 'post' => $post ) );

			// Cache the result.
			$this->cache_manager->set( $cache_key, $markdown );
		}

		$this->send_markdown_response( $markdown, $post );
	}

	/**
	 * Send the Markdown HTTP response.
	 *
	 * @param string  $markdown The Markdown content.
	 * @param WP_Post $post     The source post.
	 *
	 * @return void
	 */
	private function send_markdown_response( string $markdown, WP_Post $post ): void {
		// Determine media type.
		$media_type = $this->negotiator->get_media_type();

		// Calculate token estimate.
		$tokens = 0;
		if ( $this->settings['token_header'] ?? true ) {
			if ( $this->converter instanceof MarkdownConverter ) {
				$tokens = $this->converter->estimate_tokens( $markdown );
			}
		}

		// Set response headers.
		$this->send_headers( $media_type, $markdown, $tokens, $post );

		/**
		 * Filter the Markdown content before sending the response.
		 *
		 * @param string  $markdown   The Markdown content.
		 * @param WP_Post $post       The source post.
		 * @param string  $media_type The negotiated media type.
		 */
		$markdown = apply_filters( 'jetstaa_mna_response_markdown', $markdown, $post, $media_type );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown is plain text.
		echo $markdown;
		exit;
	}

	/**
	 * Send response headers.
	 *
	 * @param string  $media_type The media type to use.
	 * @param string  $markdown   The Markdown content (for Content-Length).
	 * @param int     $tokens     Estimated token count.
	 * @param WP_Post $post       The source post.
	 *
	 * @return void
	 */
	private function send_headers( string $media_type, string $markdown, int $tokens, WP_Post $post ): void {
		// Remove any previously set headers that might conflict.
		if ( ! headers_sent() ) {
			header_remove( 'Content-Type' );

			// Core response headers.
			header( sprintf( 'Content-Type: %s; charset=utf-8', $media_type ) );
			header( 'Vary: Accept' );
			header( sprintf( 'Content-Length: %d', strlen( $markdown ) ) );
			header( 'X-Markdown-Source: wordpress-plugin' );
			header( sprintf( 'X-Markdown-Plugin-Version: %s', JETSTAA_MNA_VERSION ) );

			// Token count header.
			if ( $tokens > 0 ) {
				header( sprintf( 'X-Markdown-Tokens: %d', $tokens ) );
			}

			// Last-Modified header for caching.
			$modified_time = strtotime( $post->post_modified_gmt );
			if ( $modified_time ) {
				header( sprintf( 'Last-Modified: %s', gmdate( 'D, d M Y H:i:s', $modified_time ) . ' GMT' ) );
			}

			// ETag for conditional requests.
			$etag = sprintf( 'W/"%s-%s"', $post->ID, md5( $markdown ) );
			header( sprintf( 'ETag: %s', $etag ) );

			// Cache control.
			header( 'Cache-Control: public, max-age=300, s-maxage=3600' );

			// Handle conditional requests.
			$this->handle_conditional_request( $etag, $modified_time );

			/**
			 * Action to add custom response headers.
			 *
			 * @param WP_Post $post    The source post.
			 * @param string  $markdown The Markdown content.
			 */
			do_action( 'jetstaa_mna_send_headers', $post, $markdown );
		}
	}

	/**
	 * Handle HTTP conditional requests (304 Not Modified).
	 *
	 * @param string   $etag          The ETag value.
	 * @param int|bool $modified_time The last modified timestamp.
	 *
	 * @return void
	 */
	private function handle_conditional_request( string $etag, int|bool $modified_time ): void {
		// Check If-None-Match.
		$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) )
			: '';
		if ( $if_none_match && $if_none_match === $etag ) {
			http_response_code( 304 );
			exit;
		}

		// Check If-Modified-Since.
		$if_modified_since = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) )
			: '';
		if ( $if_modified_since && $modified_time ) {
			$since_time = strtotime( $if_modified_since );
			if ( $since_time && $since_time >= $modified_time ) {
				http_response_code( 304 );
				exit;
			}
		}
	}

	/**
	 * Send an error response.
	 *
	 * @param int    $status_code HTTP status code.
	 * @param string $message     Error message.
	 *
	 * @return void
	 */
	private function send_error_response( int $status_code, string $message ): void {
		http_response_code( $status_code );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $message );
		exit;
	}

	/**
	 * Check if the post type is allowed for Markdown conversion.
	 *
	 * @param WP_Post $post The post to check.
	 *
	 * @return bool True if allowed.
	 */
	private function is_post_type_allowed( WP_Post $post ): bool {
		$allowed_types = (array) ( $this->settings['post_types'] ?? array( 'post', 'page' ) );

		// Check WooCommerce products.
		if ( 'product' === $post->post_type ) {
			return (bool) ( $this->settings['woocommerce'] ?? true );
		}

		/**
		 * Filter the allowed post types.
		 *
		 * @param array   $allowed_types List of allowed post type slugs.
		 * @param WP_Post $post          The post being checked.
		 */
		$allowed_types = apply_filters( 'jetstaa_mna_allowed_post_types', $allowed_types, $post );

		return in_array( $post->post_type, $allowed_types, true );
	}
}
