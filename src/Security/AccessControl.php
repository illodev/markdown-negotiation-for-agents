<?php
/**
 * Access Control.
 *
 * Manages access permissions for Markdown content.
 * Ensures private, draft, and password-protected posts
 * are not exposed via content negotiation.
 *
 * @package IlloDev\MarkdownNegotiation\Security
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Security;

use IlloDev\MarkdownNegotiation\Admin\MetaBox;
use WP_Post;

/**
 * Class AccessControl
 *
 * Enforces content access policies.
 */
final class AccessControl {

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	public function __construct(
		private readonly array $settings,
	) {}

	/**
	 * Check if a post can be accessed as Markdown.
	 *
	 * @param WP_Post $post The post to check.
	 *
	 * @return bool True if access is allowed.
	 */
	public function can_access( WP_Post $post ): bool {
		// Must be published.
		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		// Password-protected posts require the password.
		if ( ! empty( $post->post_password ) ) {
			if ( ! $this->is_password_provided( $post ) ) {
				return false;
			}
		}

		// Check per-post disable flag.
		if ( MetaBox::is_disabled_for_post( $post->ID ) ) {
			return false;
		}

		// Check post type.
		$allowed_types = (array) ( $this->settings['post_types'] ?? array( 'post', 'page' ) );

		if ( 'product' === $post->post_type ) {
			if ( ! ( $this->settings['woocommerce'] ?? true ) ) {
				return false;
			}
		} elseif ( ! in_array( $post->post_type, $allowed_types, true ) ) {
			return false;
		}

		/**
		 * Filter whether a post can be accessed as Markdown.
		 *
		 * @param bool    $can_access Whether access is allowed.
		 * @param WP_Post $post       The post being checked.
		 */
		return apply_filters( 'jetstaa_mna_can_access', true, $post );
	}

	/**
	 * Check if the password for a protected post has been provided.
	 *
	 * For HTTP content negotiation (headless/API clients), passwords
	 * cannot be checked via cookies. We require Basic Auth or a
	 * custom header in such cases.
	 *
	 * @param WP_Post $post The post.
	 *
	 * @return bool True if password is provided and correct.
	 */
	private function is_password_provided( WP_Post $post ): bool {
		// Check WordPress cookie-based password protection.
		if ( ! post_password_required( $post ) ) {
			return true;
		}

		// Check custom header for API clients.
		$header_password = $_SERVER['HTTP_X_WP_POST_PASSWORD'] ?? '';
		if ( ! empty( $header_password ) ) {
			return $header_password === $post->post_password;
		}

		return false;
	}
}
