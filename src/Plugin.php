<?php
/**
 * Main Plugin orchestrator.
 *
 * Bootstraps all plugin components, registers services in the DI container,
 * and hooks into WordPress lifecycle events.
 *
 * @package IlloDev\MarkdownNegotiation
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation;

use IlloDev\MarkdownNegotiation\Admin\SettingsPage;
use IlloDev\MarkdownNegotiation\Admin\SettingsRegistrar;
use IlloDev\MarkdownNegotiation\Admin\MetaBox;
use IlloDev\MarkdownNegotiation\Cache\CacheManager;
use IlloDev\MarkdownNegotiation\Cache\ObjectCacheDriver;
use IlloDev\MarkdownNegotiation\Cache\TransientDriver;
use IlloDev\MarkdownNegotiation\Cache\FileCacheDriver;
use IlloDev\MarkdownNegotiation\Cli\MarkdownCommand;
use IlloDev\MarkdownNegotiation\Contracts\CacheInterface;
use IlloDev\MarkdownNegotiation\Contracts\ConverterInterface;
use IlloDev\MarkdownNegotiation\Contracts\NegotiatorInterface;
use IlloDev\MarkdownNegotiation\Contracts\SanitizerInterface;
use IlloDev\MarkdownNegotiation\Converter\MarkdownConverter;
use IlloDev\MarkdownNegotiation\Converter\ContentExtractor;
use IlloDev\MarkdownNegotiation\Converter\GutenbergProcessor;
use IlloDev\MarkdownNegotiation\Converter\ShortcodeProcessor;
use IlloDev\MarkdownNegotiation\Http\ContentNegotiator;
use IlloDev\MarkdownNegotiation\Http\ResponseHandler;
use IlloDev\MarkdownNegotiation\Http\HeaderValidator;
use IlloDev\MarkdownNegotiation\Http\AlternateEndpoint;
use IlloDev\MarkdownNegotiation\Rest\MarkdownController;
use IlloDev\MarkdownNegotiation\Rest\FieldRegistrar;
use IlloDev\MarkdownNegotiation\Security\Sanitizer;
use IlloDev\MarkdownNegotiation\Security\AccessControl;
use IlloDev\MarkdownNegotiation\Security\RateLimiter;
use IlloDev\MarkdownNegotiation\Multisite\NetworkHandler;

/**
 * Class Plugin
 *
 * Singleton that orchestrates all plugin subsystems.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * DI container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Plugin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * Whether the plugin has been initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor for singleton.
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Initialize the plugin.
	 *
	 * Registers all services and hooks.
	 *
	 * @return void
	 */
	public function initialize(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->load_settings();
		$this->register_services();
		$this->register_hooks();

		$this->initialized = true;

		/**
		 * Fires after the Markdown Negotiation plugin is fully initialized.
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'jetstaa_mna_initialized', $this );
	}

	/**
	 * Get the DI container.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed Setting value.
	 */
	public function setting( string $key, mixed $default = null ): mixed {
		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		return $this->settings;
	}

	/**
	 * Load plugin settings from the database.
	 *
	 * @return void
	 */
	private function load_settings(): void {
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

		$stored = get_option( 'jetstaa_mna_settings', array() );

		$this->settings = wp_parse_args(
			is_array( $stored ) ? $stored : array(),
			$defaults
		);

		/**
		 * Filter the plugin settings after loading.
		 *
		 * @param array $settings Current settings.
		 */
		$this->settings = apply_filters( 'jetstaa_mna_settings', $this->settings );
	}

	/**
	 * Register all services in the DI container.
	 *
	 * @return void
	 */
	private function register_services(): void {
		// Register self.
		$this->container->instance( Plugin::class, $this );

		// Security: Sanitizer.
		$this->container->singleton(
			SanitizerInterface::class,
			static fn( Container $c ): SanitizerInterface => new Sanitizer()
		);

		// Security: Access Control.
		$this->container->singleton(
			AccessControl::class,
			fn( Container $c ): AccessControl => new AccessControl( $this->settings )
		);

		// Security: Rate Limiter.
		$this->container->singleton(
			RateLimiter::class,
			fn( Container $c ): RateLimiter => new RateLimiter(
				(int) $this->setting( 'rate_limit_requests', 60 ),
				(int) $this->setting( 'rate_limit_window', 60 )
			)
		);

		// Converter: Gutenberg Processor.
		$this->container->singleton(
			GutenbergProcessor::class,
			static fn( Container $c ): GutenbergProcessor => new GutenbergProcessor()
		);

		// Converter: Shortcode Processor.
		$this->container->singleton(
			ShortcodeProcessor::class,
			static fn( Container $c ): ShortcodeProcessor => new ShortcodeProcessor()
		);

		// Converter: Content Extractor.
		$this->container->singleton(
			ContentExtractor::class,
			static fn( Container $c ): ContentExtractor => new ContentExtractor(
				$c->get( SanitizerInterface::class ),
				$c->get( GutenbergProcessor::class ),
				$c->get( ShortcodeProcessor::class )
			)
		);

		// Converter: Main Markdown Converter.
		$this->container->singleton(
			ConverterInterface::class,
			static fn( Container $c ): ConverterInterface => new MarkdownConverter(
				$c->get( ContentExtractor::class )
			)
		);

		// Cache: Resolve appropriate driver.
		$this->container->singleton(
			CacheInterface::class,
			fn( Container $c ): CacheInterface => $this->resolve_cache_driver()
		);

		// Cache: Manager.
		$this->container->singleton(
			CacheManager::class,
			fn( Container $c ): CacheManager => new CacheManager(
				$c->get( CacheInterface::class ),
				(int) $this->setting( 'cache_ttl', 3600 )
			)
		);

		// HTTP: Content Negotiator.
		$this->container->singleton(
			NegotiatorInterface::class,
			static fn( Container $c ): NegotiatorInterface => new ContentNegotiator()
		);

		// HTTP: Header Validator.
		$this->container->singleton(
			HeaderValidator::class,
			static fn( Container $c ): HeaderValidator => new HeaderValidator()
		);

		// HTTP: Response Handler.
		$this->container->singleton(
			ResponseHandler::class,
			fn( Container $c ): ResponseHandler => new ResponseHandler(
				$c->get( ConverterInterface::class ),
				$c->get( CacheManager::class ),
				$c->get( NegotiatorInterface::class ),
				$c->get( AccessControl::class ),
				$c->get( HeaderValidator::class ),
				$this->settings
			)
		);

		// HTTP: Alternate Endpoint.
		$this->container->singleton(
			AlternateEndpoint::class,
			fn( Container $c ): AlternateEndpoint => new AlternateEndpoint(
				$c->get( ResponseHandler::class ),
				$this->settings
			)
		);

		// REST: Field Registrar.
		$this->container->singleton(
			FieldRegistrar::class,
			fn( Container $c ): FieldRegistrar => new FieldRegistrar(
				$c->get( ConverterInterface::class ),
				$c->get( CacheManager::class ),
				$this->settings
			)
		);

		// REST: Controller.
		$this->container->singleton(
			MarkdownController::class,
			fn( Container $c ): MarkdownController => new MarkdownController(
				$c->get( ConverterInterface::class ),
				$c->get( CacheManager::class ),
				$c->get( AccessControl::class ),
				$this->settings
			)
		);

		// Admin: Settings Registrar.
		$this->container->singleton(
			SettingsRegistrar::class,
			static fn( Container $c ): SettingsRegistrar => new SettingsRegistrar()
		);

		// Admin: Settings Page.
		$this->container->singleton(
			SettingsPage::class,
			fn( Container $c ): SettingsPage => new SettingsPage(
				$c->get( SettingsRegistrar::class ),
				$c->get( CacheManager::class ),
				$this->settings
			)
		);

		// Admin: Meta Box.
		$this->container->singleton(
			MetaBox::class,
			fn( Container $c ): MetaBox => new MetaBox( $this->settings )
		);

		// Multisite: Network Handler.
		$this->container->singleton(
			NetworkHandler::class,
			fn( Container $c ): NetworkHandler => new NetworkHandler( $this->settings )
		);

		/**
		 * Fires after all services are registered in the container.
		 *
		 * Use this to override or extend registered services.
		 *
		 * @param Container $container The DI container.
		 * @param Plugin    $plugin    The plugin instance.
		 */
		do_action( 'jetstaa_mna_services_registered', $this->container, $this );
	}

	/**
	 * Register all WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Load text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Only register frontend hooks if enabled.
		if ( $this->setting( 'enabled', true ) ) {
			/** @var ResponseHandler $response_handler */
			$response_handler = $this->container->get( ResponseHandler::class );
			$response_handler->register_hooks();

			// Rate limiter.
			if ( $this->setting( 'rate_limit_enabled', false ) ) {
				/** @var RateLimiter $rate_limiter */
				$rate_limiter = $this->container->get( RateLimiter::class );
				$rate_limiter->register_hooks();
			}

			// Alternate endpoints.
			if ( $this->setting( 'endpoint_md', false ) || $this->setting( 'query_format', true ) ) {
				/** @var AlternateEndpoint $alternate_endpoint */
				$alternate_endpoint = $this->container->get( AlternateEndpoint::class );
				$alternate_endpoint->register_hooks();
			}
		}

		// REST API.
		if ( $this->setting( 'rest_markdown', true ) ) {
			/** @var FieldRegistrar $field_registrar */
			$field_registrar = $this->container->get( FieldRegistrar::class );
			$field_registrar->register_hooks();

			/** @var MarkdownController $markdown_controller */
			$markdown_controller = $this->container->get( MarkdownController::class );
			$markdown_controller->register_hooks();
		}

		// Cache invalidation.
		if ( $this->setting( 'cache_enabled', true ) ) {
			/** @var CacheManager $cache_manager */
			$cache_manager = $this->container->get( CacheManager::class );
			$cache_manager->register_hooks();
		}

		// Admin.
		if ( is_admin() ) {
			/** @var SettingsPage $settings_page */
			$settings_page = $this->container->get( SettingsPage::class );
			$settings_page->register_hooks();

			/** @var SettingsRegistrar $settings_registrar */
			$settings_registrar = $this->container->get( SettingsRegistrar::class );
			$settings_registrar->register_hooks();

			/** @var MetaBox $meta_box */
			$meta_box = $this->container->get( MetaBox::class );
			$meta_box->register_hooks();
		}

		// Multisite.
		if ( is_multisite() ) {
			/** @var NetworkHandler $network_handler */
			$network_handler = $this->container->get( NetworkHandler::class );
			$network_handler->register_hooks();
		}

		// WP-CLI.
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			$this->register_cli_commands();
		}

		// Add Vary header to all responses for cache correctness.
		add_filter( 'wp_headers', array( $this, 'add_vary_header' ) );

		// Add Link rel alternate header.
		add_action( 'wp_head', array( $this, 'add_link_alternate' ) );
		add_action( 'send_headers', array( $this, 'send_link_header' ) );
	}

	/**
	 * Load plugin text domain for i18n.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'markdown-negotiation-for-agents',
			false,
			dirname( JETSTAA_MNA_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Add Vary: Accept header to WordPress responses.
	 *
	 * Critical for cache correctness: instructs CDNs and proxies to vary
	 * cached responses based on the Accept header.
	 *
	 * @param array $headers Existing response headers.
	 *
	 * @return array Modified headers.
	 */
	public function add_vary_header( array $headers ): array {
		$existing = $headers['Vary'] ?? '';
		$parts    = array_map( 'trim', explode( ',', $existing ) );

		if ( ! in_array( 'Accept', $parts, true ) ) {
			$parts[]          = 'Accept';
			$headers['Vary'] = implode( ', ', array_filter( $parts ) );
		}

		return $headers;
	}

	/**
	 * Output <link rel="alternate"> tag for Markdown in HTML head.
	 *
	 * Signals existence of Markdown representation to AI agents.
	 *
	 * @return void
	 */
	public function add_link_alternate(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		$allowed_types = (array) $this->setting( 'post_types', array( 'post', 'page' ) );
		if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
			return;
		}

		$url = get_permalink( $post );
		if ( ! $url ) {
			return;
		}

		printf(
			'<link rel="alternate" type="text/markdown" href="%s" title="%s" />' . "\n",
			esc_url( $url ),
			esc_attr( get_the_title( $post ) )
		);
	}

	/**
	 * Send Link header for content negotiation discovery.
	 *
	 * @return void
	 */
	public function send_link_header(): void {
		if ( ! is_singular() || headers_sent() ) {
			return;
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		$allowed_types = (array) $this->setting( 'post_types', array( 'post', 'page' ) );
		if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
			return;
		}

		$url = get_permalink( $post );
		if ( ! $url ) {
			return;
		}

		header( sprintf(
			'Link: <%s>; rel="alternate"; type="text/markdown"',
			esc_url_raw( $url )
		), false );
	}

	/**
	 * Register WP-CLI commands.
	 *
	 * @return void
	 */
	private function register_cli_commands(): void {
		$command = new MarkdownCommand(
			$this->container->get( ConverterInterface::class ),
			$this->container->get( CacheManager::class ),
			$this->settings
		);

		\WP_CLI::add_command( 'markdown', $command );
	}

	/**
	 * Resolve the appropriate cache driver based on settings and availability.
	 *
	 * @return CacheInterface The resolved cache driver.
	 */
	private function resolve_cache_driver(): CacheInterface {
		$driver = $this->setting( 'cache_driver', 'auto' );

		if ( 'auto' === $driver ) {
			// Prefer object cache > transients > file.
			$object_cache = new ObjectCacheDriver();
			if ( $object_cache->is_available() ) {
				return $object_cache;
			}

			return new TransientDriver();
		}

		return match ( $driver ) {
			'object'    => new ObjectCacheDriver(),
			'transient' => new TransientDriver(),
			'file'      => new FileCacheDriver(),
			default     => new TransientDriver(),
		};
	}

	/**
	 * Reset the singleton instance (for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}
}
