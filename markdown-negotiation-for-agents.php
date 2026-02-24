<?php
/**
 * Plugin Name:       Markdown Negotiation for Agents
 * Plugin URI:        https://github.com/illodev/markdown-negotiation-for-agents
 * Description:       Serves WordPress content as Markdown via HTTP content negotiation (Accept: text/markdown). Ideal for AI agents, LLMs, and developer tools.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            illodev
 * Author URI:        https://illodev.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       markdown-negotiation-for-agents
 * Domain Path:       /languages
 * Network:           true
 *
 * @package IlloDev\MarkdownNegotiation
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'JETSTAA_MNA_VERSION', '1.0.0' );
define( 'JETSTAA_MNA_PLUGIN_FILE', __FILE__ );
define( 'JETSTAA_MNA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JETSTAA_MNA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JETSTAA_MNA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'JETSTAA_MNA_MIN_PHP', '8.1' );
define( 'JETSTAA_MNA_MIN_WP', '6.0' );

/**
 * Check minimum requirements before loading the plugin.
 *
 * @return bool True if requirements are met.
 */
function jetstaa_mna_check_requirements(): bool {
	if ( version_compare( PHP_VERSION, JETSTAA_MNA_MIN_PHP, '<' ) ) {
		add_action( 'admin_notices', static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: Required PHP version, 2: Current PHP version. */
						__( 'Markdown Negotiation for Agents requires PHP %1$s or higher. You are running PHP %2$s.', 'markdown-negotiation-for-agents' ),
						JETSTAA_MNA_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		} );
		return false;
	}

	if ( version_compare( get_bloginfo( 'version' ), JETSTAA_MNA_MIN_WP, '<' ) ) {
		add_action( 'admin_notices', static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: Required WP version, 2: Current WP version. */
						__( 'Markdown Negotiation for Agents requires WordPress %1$s or higher. You are running WordPress %2$s.', 'markdown-negotiation-for-agents' ),
						JETSTAA_MNA_MIN_WP,
						get_bloginfo( 'version' )
					)
				)
			);
		} );
		return false;
	}

	return true;
}

/**
 * Load the Composer autoloader.
 *
 * @return bool True if loaded.
 */
function jetstaa_mna_load_autoloader(): bool {
	$autoloader = JETSTAA_MNA_PLUGIN_DIR . 'vendor/autoload.php';

	if ( ! file_exists( $autoloader ) ) {
		add_action( 'admin_notices', static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__(
					'Markdown Negotiation for Agents: Composer autoloader not found. Please run "composer install" in the plugin directory.',
					'markdown-negotiation-for-agents'
				)
			);
		} );
		return false;
	}

	require_once $autoloader;
	return true;
}

/**
 * Bootstrap the plugin.
 */
function jetstaa_mna_bootstrap(): void {
	if ( ! jetstaa_mna_check_requirements() ) {
		return;
	}

	if ( ! jetstaa_mna_load_autoloader() ) {
		return;
	}

	// Initialize the plugin.
	$plugin = IlloDev\MarkdownNegotiation\Plugin::get_instance();
	$plugin->initialize();
}

// Register activation/deactivation hooks.
register_activation_hook( __FILE__, static function (): void {
	if ( ! jetstaa_mna_check_requirements() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'Markdown Negotiation for Agents cannot be activated. Check PHP and WordPress version requirements.', 'markdown-negotiation-for-agents' ),
			'Plugin Activation Error',
			array( 'back_link' => true )
		);
	}

	// Set default options.
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

	if ( false === get_option( 'jetstaa_mna_settings' ) ) {
		add_option( 'jetstaa_mna_settings', $defaults );
	}

	// Flush rewrite rules for .md endpoint.
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, static function (): void {
	flush_rewrite_rules();

	// Clean up transients.
	global $wpdb;
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_jetstaa_mna_%' OR option_name LIKE '_transient_timeout_jetstaa_mna_%'"
	);
} );

// Bootstrap on plugins_loaded to ensure all dependencies are available.
add_action( 'plugins_loaded', 'jetstaa_mna_bootstrap', 5 );
