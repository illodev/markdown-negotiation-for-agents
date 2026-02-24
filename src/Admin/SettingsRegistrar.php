<?php
/**
 * Settings Registrar.
 *
 * Registers all plugin settings with the WordPress Settings API.
 *
 * @package IlloDev\MarkdownNegotiation\Admin
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Admin;

/**
 * Class SettingsRegistrar
 *
 * Handles settings registration, sanitization, and validation.
 */
final class SettingsRegistrar {

	/**
	 * Option name in the database.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'jetstaa_mna_settings';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'jetstaa-mna-settings';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'handle_endpoint_change' ), 10, 2 );
	}

	/**
	 * Register all plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::PAGE_SLUG,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_defaults(),
			)
		);

		// Section: General.
		add_settings_section(
			'jetstaa_mna_general',
			__( 'General Settings', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_general_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'enabled',
			__( 'Enable Content Negotiation', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'jetstaa_mna_general',
			array(
				'field'       => 'enabled',
				'description' => __( 'Respond to Accept: text/markdown requests with Markdown content.', 'markdown-negotiation-for-agents' ),
			)
		);

		// Section: Endpoints.
		add_settings_section(
			'jetstaa_mna_endpoints',
			__( 'Access Methods', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_endpoints_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'endpoint_md',
			__( 'Enable .md Extension', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'jetstaa_mna_endpoints',
			array(
				'field'       => 'endpoint_md',
				'description' => __( 'Access Markdown via /post-slug.md URLs.', 'markdown-negotiation-for-agents' ),
			)
		);

		add_settings_field(
			'query_format',
			__( 'Enable ?format=markdown', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'jetstaa_mna_endpoints',
			array(
				'field'       => 'query_format',
				'description' => __( 'Access Markdown via ?format=markdown query parameter.', 'markdown-negotiation-for-agents' ),
			)
		);

		// Section: Content Types.
		add_settings_section(
			'jetstaa_mna_content',
			__( 'Content Types', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_content_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'post_types',
			__( 'Post Types', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_post_types_field' ),
			self::PAGE_SLUG,
			'jetstaa_mna_content'
		);

		add_settings_field(
			'woocommerce',
			__( 'WooCommerce Products', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'jetstaa_mna_content',
			array(
				'field'       => 'woocommerce',
				'description' => __( 'Include WooCommerce product data in Markdown output.', 'markdown-negotiation-for-agents' ),
				'disabled'    => ! class_exists( 'WooCommerce' ),
			)
		);

		// Section: API.
		add_settings_section(
			'jetstaa_mna_api',
			__( 'API Settings', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_api_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'rest_markdown',
			__( 'REST API Markdown', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'jetstaa_mna_api',
			array(
				'field'       => 'rest_markdown',
				'description' => __( 'Add markdown field to REST API responses and register /markdown endpoint.', 'markdown-negotiation-for-agents' ),
			)
		);

		add_settings_field(
			'token_header',
			__( 'Token Count Header', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'jetstaa_mna_api',
			array(
				'field'       => 'token_header',
				'description' => __( 'Include X-Markdown-Tokens header with estimated token count.', 'markdown-negotiation-for-agents' ),
			)
		);

		// Section: Cache.
		add_settings_section(
			'jetstaa_mna_cache',
			__( 'Cache Settings', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_cache_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'cache_enabled',
			__( 'Enable Cache', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'jetstaa_mna_cache',
			array(
				'field'       => 'cache_enabled',
				'description' => __( 'Cache converted Markdown for better performance.', 'markdown-negotiation-for-agents' ),
			)
		);

		add_settings_field(
			'cache_driver',
			__( 'Cache Driver', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_select_field' ),
			self::PAGE_SLUG,
			'jetstaa_mna_cache',
			array(
				'field'   => 'cache_driver',
				'options' => array(
					'auto'      => __( 'Auto (recommended)', 'markdown-negotiation-for-agents' ),
					'object'    => __( 'Object Cache (Redis/Memcached)', 'markdown-negotiation-for-agents' ),
					'transient' => __( 'Transients (database)', 'markdown-negotiation-for-agents' ),
					'file'      => __( 'File System', 'markdown-negotiation-for-agents' ),
				),
			)
		);

		add_settings_field(
			'cache_ttl',
			__( 'Cache TTL (seconds)', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'jetstaa_mna_cache',
			array(
				'field'       => 'cache_ttl',
				'min'         => 0,
				'max'         => 86400,
				'description' => __( 'How long to keep cached Markdown. 0 = no expiry. Default: 3600 (1 hour).', 'markdown-negotiation-for-agents' ),
			)
		);

		// Section: Security.
		add_settings_section(
			'jetstaa_mna_security',
			__( 'Security', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_security_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'rate_limit_enabled',
			__( 'Enable Rate Limiting', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'jetstaa_mna_security',
			array(
				'field'       => 'rate_limit_enabled',
				'description' => __( 'Limit Markdown requests per IP address.', 'markdown-negotiation-for-agents' ),
			)
		);

		add_settings_field(
			'rate_limit_requests',
			__( 'Requests per Window', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'jetstaa_mna_security',
			array(
				'field'       => 'rate_limit_requests',
				'min'         => 1,
				'max'         => 1000,
				'description' => __( 'Maximum Markdown requests per time window.', 'markdown-negotiation-for-agents' ),
			)
		);

		add_settings_field(
			'rate_limit_window',
			__( 'Window Duration (seconds)', 'markdown-negotiation-for-agents' ),
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'jetstaa_mna_security',
			array(
				'field'       => 'rate_limit_window',
				'min'         => 10,
				'max'         => 3600,
				'description' => __( 'Time window duration in seconds.', 'markdown-negotiation-for-agents' ),
			)
		);
	}

	/**
	 * Sanitize all settings.
	 *
	 * @param mixed $input Raw input.
	 *
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return $this->get_defaults();
		}

		$sanitized = array();

		// Booleans.
		$bool_fields = array(
			'enabled',
			'endpoint_md',
			'query_format',
			'woocommerce',
			'rest_markdown',
			'token_header',
			'cache_enabled',
			'rate_limit_enabled',
		);

		foreach ( $bool_fields as $field ) {
			$sanitized[ $field ] = ! empty( $input[ $field ] );
		}

		// Post types (array of slugs).
		$sanitized['post_types'] = array();
		if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$sanitized['post_types'] = array_map( 'sanitize_key', $input['post_types'] );
		}

		// Cache driver.
		$valid_drivers            = array( 'auto', 'object', 'transient', 'file' );
		$sanitized['cache_driver'] = in_array( $input['cache_driver'] ?? 'auto', $valid_drivers, true )
			? $input['cache_driver']
			: 'auto';

		// Numbers.
		$sanitized['cache_ttl']           = absint( $input['cache_ttl'] ?? 3600 );
		$sanitized['rate_limit_requests'] = max( 1, absint( $input['rate_limit_requests'] ?? 60 ) );
		$sanitized['rate_limit_window']   = max( 10, absint( $input['rate_limit_window'] ?? 60 ) );

		return $sanitized;
	}

	/**
	 * Flush rewrite rules after the endpoint_md setting is saved.
	 *
	 * Fires on `update_option_{option_name}`, which is AFTER WordPress saves
	 * the new value.  At this point we can safely register the rewrite rules
	 * (so they are present in $wp_rewrite) before calling flush_rewrite_rules().
	 *
	 * @param array<string, mixed> $old_value Previous setting values.
	 * @param array<string, mixed> $new_value New setting values.
	 *
	 * @return void
	 */
	public function handle_endpoint_change( array $old_value, array $new_value ): void {
		if ( ( $old_value['endpoint_md'] ?? false ) === ( $new_value['endpoint_md'] ?? false ) ) {
			return;
		}

		if ( $new_value['endpoint_md'] ?? false ) {
			// Register the rules now so they are included in the flush.
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
			$post_types = (array) ( $new_value['post_types'] ?? array( 'post', 'page' ) );
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

		flush_rewrite_rules();
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	public function get_defaults(): array {
		return array(
			'enabled'              => true,
			'endpoint_md'          => false,
			'query_format'         => true,
			'post_types'           => array( 'post', 'page' ),
			'woocommerce'          => true,
			'rest_markdown'        => true,
			'token_header'         => true,
			'cache_enabled'        => true,
			'cache_driver'         => 'auto',
			'cache_ttl'            => 3600,
			'rate_limit_enabled'   => false,
			'rate_limit_requests'  => 60,
			'rate_limit_window'    => 60,
		);
	}

	// --- Section renderers ---

	/**
	 * Render the general section description.
	 *
	 * @return void
	 */
	public function render_general_section(): void {
		echo '<p>' . esc_html__( 'Core content negotiation settings.', 'markdown-negotiation-for-agents' ) . '</p>';
	}

	/**
	 * Render the endpoints section description.
	 *
	 * @return void
	 */
	public function render_endpoints_section(): void {
		echo '<p>' . esc_html__( 'Alternative methods for accessing Markdown content besides Accept header negotiation.', 'markdown-negotiation-for-agents' ) . '</p>';
	}

	/**
	 * Render the content section description.
	 *
	 * @return void
	 */
	public function render_content_section(): void {
		echo '<p>' . esc_html__( 'Configure which content types are available as Markdown.', 'markdown-negotiation-for-agents' ) . '</p>';
	}

	/**
	 * Render the API section description.
	 *
	 * @return void
	 */
	public function render_api_section(): void {
		echo '<p>' . esc_html__( 'REST API and response header settings.', 'markdown-negotiation-for-agents' ) . '</p>';
	}

	/**
	 * Render the cache section description.
	 *
	 * @return void
	 */
	public function render_cache_section(): void {
		echo '<p>' . esc_html__( 'Markdown caching for improved performance.', 'markdown-negotiation-for-agents' ) . '</p>';
	}

	/**
	 * Render the security section description.
	 *
	 * @return void
	 */
	public function render_security_section(): void {
		echo '<p>' . esc_html__( 'Rate limiting and security settings.', 'markdown-negotiation-for-agents' ) . '</p>';
	}

	// --- Field renderers ---

	/**
	 * Render a checkbox field.
	 *
	 * @param array $args Field arguments.
	 *
	 * @return void
	 */
	public function render_checkbox_field( array $args ): void {
		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		$field    = $args['field'];
		$value    = $settings[ $field ] ?? false;
		$disabled = $args['disabled'] ?? false;

		printf(
			'<label><input type="checkbox" name="%s[%s]" value="1" %s %s /> %s</label>',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $field ),
			checked( $value, true, false ),
			disabled( $disabled, true, false ),
			esc_html( $args['description'] ?? '' )
		);
	}

	/**
	 * Render a select field.
	 *
	 * @param array $args Field arguments.
	 *
	 * @return void
	 */
	public function render_select_field( array $args ): void {
		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		$field    = $args['field'];
		$value    = $settings[ $field ] ?? '';

		printf(
			'<select name="%s[%s]">',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $field )
		);

		foreach ( $args['options'] as $option_value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $option_value ),
				selected( $value, $option_value, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
	}

	/**
	 * Render a number field.
	 *
	 * @param array $args Field arguments.
	 *
	 * @return void
	 */
	public function render_number_field( array $args ): void {
		$settings = get_option( self::OPTION_NAME, $this->get_defaults() );
		$field    = $args['field'];
		$value    = $settings[ $field ] ?? 0;

		printf(
			'<input type="number" name="%s[%s]" value="%d" min="%d" max="%d" class="small-text" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $field ),
			absint( $value ),
			absint( $args['min'] ?? 0 ),
			absint( $args['max'] ?? 99999 )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render the post types multi-checkbox field.
	 *
	 * @return void
	 */
	public function render_post_types_field(): void {
		$settings   = get_option( self::OPTION_NAME, $this->get_defaults() );
		$selected   = (array) ( $settings['post_types'] ?? array() );
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			printf(
				'<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="%s[post_types][]" value="%s" %s /> %s <code>(%s)</code></label>',
				esc_attr( self::OPTION_NAME ),
				esc_attr( $post_type->name ),
				checked( in_array( $post_type->name, $selected, true ), true, false ),
				esc_html( $post_type->label ),
				esc_html( $post_type->name )
			);
		}
	}
}
