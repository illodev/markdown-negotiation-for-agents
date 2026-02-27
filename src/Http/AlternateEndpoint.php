<?php
/**
 * Alternate Endpoint Handler.
 *
 * Provides alternative ways to access Markdown content:
 * - .md URL extension (e.g., /my-post.md)
 * - ?format=markdown query parameter
 *
 * @package IlloDev\MarkdownNegotiation\Http
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

/**
 * Class AlternateEndpoint
 *
 * Registers rewrite rules and query vars for alternative Markdown access.
 */
final class AlternateEndpoint {

	/**
	 * Constructor.
	 *
	 * @param ResponseHandler      $response_handler Response handler.
	 * @param array<string, mixed> $settings        Plugin settings.
	 */
	public function __construct(
		private readonly ResponseHandler $response_handler,
		private readonly array $settings,
	) {}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( $this->settings['endpoint_md'] ?? false ) {
			add_action( 'init', array( $this, 'register_rewrite_rules' ) );
			add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		}

		add_action( 'template_redirect', array( $this, 'handle_alternate_request' ), 0 );
	}

	/**
	 * Register rewrite rules for .md extension.
	 *
	 * @return void
	 */
	public function register_rewrite_rules(): void {
		// Match any slug ending in .md.
		add_rewrite_rule(
			'^(.+)\.md/?$',
			'index.php?pagename=$matches[1]&jetstaa_mna_format=markdown',
			'top'
		);

		add_rewrite_rule(
			'^(.+)\.md/?$',
			'index.php?name=$matches[1]&jetstaa_mna_format=markdown',
			'top'
		);

		// Custom post types.
		$post_types = (array) ( $this->settings['post_types'] ?? array( 'post', 'page' ) );
		foreach ( $post_types as $post_type ) {
			if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
				continue;
			}

			$post_type_obj = get_post_type_object( $post_type );
			if ( $post_type_obj && $post_type_obj->rewrite ) {
				$slug = $post_type_obj->rewrite['slug'] ?? $post_type;
				add_rewrite_rule(
					sprintf( '^%s/(.+)\.md/?$', preg_quote( $slug, '/' ) ),
					sprintf( 'index.php?post_type=%s&name=$matches[1]&jetstaa_mna_format=markdown', $post_type ),
					'top'
				);
			}
		}
	}

	/**
	 * Register custom query variables.
	 *
	 * @param array $vars Existing query vars.
	 *
	 * @return array Modified query vars.
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'jetstaa_mna_format';
		return $vars;
	}

	/**
	 * Handle alternate endpoint requests.
	 *
	 * @return void
	 */
	public function handle_alternate_request(): void {
		$format = '';

		// Check query var (from rewrite rule).
		$format = get_query_var( 'jetstaa_mna_format', '' );

		// Check ?format=markdown query parameter.
		if ( empty( $format ) && ( $this->settings['query_format'] ?? true ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$format = isset( $_GET['format'] ) ? sanitize_text_field( wp_unslash( $_GET['format'] ) ) : '';
		}

		if ( 'markdown' !== $format ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		// Serve Markdown directly.
		$this->response_handler->serve_markdown( $post );
	}
}
