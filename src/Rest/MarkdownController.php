<?php
/**
 * REST API Markdown Controller.
 *
 * Provides a dedicated /wp-json/jetstaa-mna/v1/markdown/<id> endpoint.
 *
 * @package IlloDev\MarkdownNegotiation\Rest
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Rest;

use IlloDev\MarkdownNegotiation\Cache\CacheManager;
use IlloDev\MarkdownNegotiation\Contracts\ConverterInterface;
use IlloDev\MarkdownNegotiation\Converter\MarkdownConverter;
use IlloDev\MarkdownNegotiation\Security\AccessControl;
use WP_Error;
use WP_Post;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class MarkdownController
 *
 * REST API controller for Markdown endpoints.
 */
final class MarkdownController extends WP_REST_Controller {

	/**
	 * Namespace for the REST route.
	 *
	 * @var string
	 */
	protected $namespace = 'jetstaa-mna/v1';

	/**
	 * Constructor.
	 *
	 * @param ConverterInterface   $converter      Markdown converter.
	 * @param CacheManager         $cache_manager  Cache manager.
	 * @param AccessControl        $access_control Access control.
	 * @param array<string, mixed> $settings       Plugin settings.
	 */
	public function __construct(
		private readonly ConverterInterface $converter,
		private readonly CacheManager $cache_manager,
		private readonly AccessControl $access_control,
		private readonly array $settings,
	) {
		$this->rest_base = 'markdown';
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /wp-json/jetstaa-mna/v1/markdown/<id>
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description'       => __( 'Post ID.', 'markdown-negotiation-for-agents' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// GET /wp-json/jetstaa-mna/v1/markdown (list)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'post_type' => array(
							'description'       => __( 'Post type slug.', 'markdown-negotiation-for-agents' ),
							'type'              => 'string',
							'default'           => 'post',
							'sanitize_callback' => 'sanitize_key',
						),
						'per_page' => array(
							'description'       => __( 'Number of items per page.', 'markdown-negotiation-for-agents' ),
							'type'              => 'integer',
							'default'           => 10,
							'minimum'           => 1,
							'maximum'           => 100,
							'sanitize_callback' => 'absint',
						),
						'page' => array(
							'description'       => __( 'Page number.', 'markdown-negotiation-for-agents' ),
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// GET /wp-json/jetstaa-mna/v1/status
		register_rest_route(
			$this->namespace,
			'/status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Check permissions for getting a single item.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|WP_Error True or error.
	 */
	public function get_item_permissions_check( $request ): bool|WP_Error {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'rest_post_invalid_id',
				__( 'Invalid post ID.', 'markdown-negotiation-for-agents' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $this->access_control->can_access( $post ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this content.', 'markdown-negotiation-for-agents' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get a single post as Markdown.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function get_item( $request ): WP_REST_Response|WP_Error {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'rest_post_invalid_id',
				__( 'Invalid post ID.', 'markdown-negotiation-for-agents' ),
				array( 'status' => 404 )
			);
		}

		// Try cache.
		$cache_key = $this->cache_manager->build_key( $post->ID, 'rest' );
		$markdown  = $this->cache_manager->get( $cache_key );

		if ( null === $markdown ) {
			$markdown = $this->converter->convert( '', array( 'post' => $post ) );
			$this->cache_manager->set( $cache_key, $markdown );
		}

		$tokens = 0;
		if ( $this->converter instanceof MarkdownConverter ) {
			$tokens = $this->converter->estimate_tokens( $markdown );
		}

		$data = array(
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'slug'      => $post->post_name,
			'permalink' => get_permalink( $post ),
			'markdown'  => $markdown,
			'tokens'    => $tokens,
			'modified'  => $post->post_modified_gmt,
		);

		$response = new WP_REST_Response( $data, 200 );

		$response->header( 'X-Markdown-Source', 'wordpress-plugin' );
		$response->header( 'X-Markdown-Tokens', (string) $tokens );

		return $response;
	}

	/**
	 * Get a list of posts as Markdown.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response Response.
	 */
	public function get_items( $request ): WP_REST_Response {
		$post_type = $request->get_param( 'post_type' );
		$per_page  = (int) $request->get_param( 'per_page' );
		$page      = (int) $request->get_param( 'page' );

		$allowed_types = (array) ( $this->settings['post_types'] ?? array( 'post', 'page' ) );
		if ( ! in_array( $post_type, $allowed_types, true ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Post type not enabled for Markdown.', 'markdown-negotiation-for-agents' ) ),
				400
			);
		}

		$query = new \WP_Query( array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'no_found_rows'  => false,
		) );

		$items = array();

		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'slug'      => $post->post_name,
				'permalink' => get_permalink( $post ),
				'modified'  => $post->post_modified_gmt,
			);
		}

		$response = new WP_REST_Response( $items, 200 );

		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

		return $response;
	}

	/**
	 * Get plugin status.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response Response.
	 */
	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		$cache_stats = $this->cache_manager->get_stats();

		return new WP_REST_Response( array(
			'version'        => JETSTAA_MNA_VERSION,
			'enabled'        => $this->settings['enabled'] ?? true,
			'converter'      => $this->converter->get_name(),
			'cache_driver'   => $cache_stats['driver'],
			'post_types'     => $this->settings['post_types'] ?? array( 'post', 'page' ),
			'supported_types' => array( 'text/markdown', 'text/x-markdown' ),
		), 200 );
	}

	/**
	 * Get the item schema.
	 *
	 * @return array Schema array.
	 */
	public function get_public_item_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'markdown',
			'type'       => 'object',
			'properties' => array(
				'id'        => array(
					'description' => __( 'Post ID.', 'markdown-negotiation-for-agents' ),
					'type'        => 'integer',
				),
				'title'     => array(
					'description' => __( 'Post title.', 'markdown-negotiation-for-agents' ),
					'type'        => 'string',
				),
				'slug'      => array(
					'description' => __( 'Post slug.', 'markdown-negotiation-for-agents' ),
					'type'        => 'string',
				),
				'permalink' => array(
					'description' => __( 'Post permalink.', 'markdown-negotiation-for-agents' ),
					'type'        => 'string',
					'format'      => 'uri',
				),
				'markdown'  => array(
					'description' => __( 'Post content as Markdown.', 'markdown-negotiation-for-agents' ),
					'type'        => 'string',
				),
				'tokens'    => array(
					'description' => __( 'Estimated token count.', 'markdown-negotiation-for-agents' ),
					'type'        => 'integer',
				),
				'modified'  => array(
					'description' => __( 'Last modified date (GMT).', 'markdown-negotiation-for-agents' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),
			),
		);
	}
}
