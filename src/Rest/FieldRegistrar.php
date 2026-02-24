<?php
/**
 * REST API Field Registrar.
 *
 * Adds a 'markdown' field to REST API responses for enabled post types.
 *
 * @package IlloDev\MarkdownNegotiation\Rest
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Rest;

use IlloDev\MarkdownNegotiation\Cache\CacheManager;
use IlloDev\MarkdownNegotiation\Contracts\ConverterInterface;
use WP_Post;
use WP_REST_Request;

/**
 * Class FieldRegistrar
 *
 * Registers custom REST API fields for Markdown content.
 */
final class FieldRegistrar {

	/**
	 * Constructor.
	 *
	 * @param ConverterInterface   $converter     Markdown converter.
	 * @param CacheManager         $cache_manager Cache manager.
	 * @param array<string, mixed> $settings      Plugin settings.
	 */
	public function __construct(
		private readonly ConverterInterface $converter,
		private readonly CacheManager $cache_manager,
		private readonly array $settings,
	) {}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_fields' ) );
	}

	/**
	 * Register the markdown field for all enabled post types.
	 *
	 * @return void
	 */
	public function register_fields(): void {
		$post_types = (array) ( $this->settings['post_types'] ?? array( 'post', 'page' ) );

		if ( ( $this->settings['woocommerce'] ?? true ) && class_exists( 'WooCommerce' ) ) {
			$post_types[] = 'product';
		}

		foreach ( $post_types as $post_type ) {
			register_rest_field(
				$post_type,
				'markdown',
				array(
					'get_callback'    => array( $this, 'get_markdown_field' ),
					'update_callback' => null,
					'schema'          => array(
						'description' => __( 'Post content converted to Markdown.', 'markdown-negotiation-for-agents' ),
						'type'        => 'object',
						'context'     => array( 'view' ),
						'properties'  => array(
							'content' => array(
								'type'        => 'string',
								'description' => __( 'Markdown content.', 'markdown-negotiation-for-agents' ),
							),
							'tokens' => array(
								'type'        => 'integer',
								'description' => __( 'Estimated token count.', 'markdown-negotiation-for-agents' ),
							),
						),
					),
				)
			);
		}
	}

	/**
	 * Get the Markdown field value for a REST API response.
	 *
	 * @param array           $object  Post data array.
	 * @param string          $field   Field name.
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return array|null Markdown data or null.
	 */
	public function get_markdown_field( array $object, string $field, WP_REST_Request $request ): ?array {
		// Check if markdown is requested via _format parameter.
		$format = $request->get_param( '_format' );
		if ( 'markdown' !== $format && ! $request->get_param( 'include_markdown' ) ) {
			return null;
		}

		$post_id = $object['id'] ?? 0;
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		// Check post status.
		if ( 'publish' !== $post->post_status ) {
			return null;
		}

		// Try cache.
		$cache_key = $this->cache_manager->build_key( $post->ID, 'rest' );
		$markdown  = $this->cache_manager->get( $cache_key );

		if ( null === $markdown ) {
			$markdown = $this->converter->convert( '', array( 'post' => $post ) );
			$this->cache_manager->set( $cache_key, $markdown );
		}

		$tokens = 0;
		if ( $this->converter instanceof \IlloDev\MarkdownNegotiation\Converter\MarkdownConverter ) {
			$tokens = $this->converter->estimate_tokens( $markdown );
		}

		return array(
			'content' => $markdown,
			'tokens'  => $tokens,
		);
	}
}
