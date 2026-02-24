<?php
/**
 * Multisite Network Handler.
 *
 * Handles network-wide activation and settings for WordPress Multisite.
 *
 * @package IlloDev\MarkdownNegotiation\Multisite
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Multisite;

/**
 * Class NetworkHandler
 *
 * Manages Multisite-specific functionality.
 */
final class NetworkHandler {

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	public function __construct(
		private readonly array $settings,
	) {}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wpmu_new_blog', array( $this, 'on_new_site' ), 10, 1 );
		add_filter( 'network_admin_plugin_action_links_' . JETSTAA_MNA_PLUGIN_BASENAME, array( $this, 'add_network_action_links' ) );
	}

	/**
	 * Set up defaults when a new site is created.
	 *
	 * @param int $blog_id New site ID.
	 *
	 * @return void
	 */
	public function on_new_site( int $blog_id ): void {
		if ( ! is_plugin_active_for_network( JETSTAA_MNA_PLUGIN_BASENAME ) ) {
			return;
		}

		switch_to_blog( $blog_id );

		$defaults = array(
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

		add_option( 'jetstaa_mna_settings', $defaults );

		restore_current_blog();
	}

	/**
	 * Add network admin action links.
	 *
	 * @param array $links Existing links.
	 *
	 * @return array Modified links.
	 */
	public function add_network_action_links( array $links ): array {
		return $links;
	}
}
