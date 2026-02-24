<?php
/**
 * Settings Page.
 *
 * Renders the plugin's admin settings page.
 *
 * @package IlloDev\MarkdownNegotiation\Admin
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Admin;

use IlloDev\MarkdownNegotiation\Cache\CacheManager;

/**
 * Class SettingsPage
 *
 * Admin settings page with capability checks and proper nonce handling.
 */
final class SettingsPage {

	/**
	 * Required capability for accessing settings.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Constructor.
	 *
	 * @param SettingsRegistrar    $registrar     Settings registrar.
	 * @param CacheManager         $cache_manager Cache manager.
	 * @param array<string, mixed> $settings      Current plugin settings.
	 */
	public function __construct(
		private readonly SettingsRegistrar $registrar,
		private readonly CacheManager $cache_manager,
		private readonly array $settings,
	) {}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_jetstaa_mna_flush_cache', array( $this, 'handle_flush_cache' ) );
		add_filter( 'plugin_action_links_' . JETSTAA_MNA_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Add the settings page to WordPress admin menu.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_options_page(
			__( 'Markdown Negotiation', 'markdown-negotiation-for-agents' ),
			__( 'Markdown Negotiation', 'markdown-negotiation-for-agents' ),
			self::CAPABILITY,
			SettingsRegistrar::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . SettingsRegistrar::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'jetstaa-mna-admin',
			JETSTAA_MNA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			JETSTAA_MNA_VERSION
		);

		wp_enqueue_script(
			'jetstaa-mna-admin',
			JETSTAA_MNA_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			JETSTAA_MNA_VERSION,
			true
		);
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Existing links.
	 *
	 * @return array Modified links.
	 */
	public function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . SettingsRegistrar::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'markdown-negotiation-for-agents' )
		);

		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to access this page.', 'markdown-negotiation-for-agents' )
			);
		}

		$cache_stats = $this->cache_manager->get_stats();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="jetstaa-mna-admin-header">
				<p><?php esc_html_e( 'Serve your WordPress content as Markdown via HTTP content negotiation. Ideal for AI agents, LLMs, and developer tools.', 'markdown-negotiation-for-agents' ); ?></p>
			</div>

			<?php settings_errors(); ?>

			<div class="jetstaa-mna-status-bar">
				<span class="jetstaa-mna-status <?php echo $this->settings['enabled'] ? 'active' : 'inactive'; ?>">
					<?php
					echo $this->settings['enabled']
						? esc_html__( 'Content Negotiation: Active', 'markdown-negotiation-for-agents' )
						: esc_html__( 'Content Negotiation: Inactive', 'markdown-negotiation-for-agents' );
					?>
				</span>
				<span class="jetstaa-mna-cache-info">
					<?php
					printf(
						/* translators: %s: Cache driver name. */
						esc_html__( 'Cache Driver: %s', 'markdown-negotiation-for-agents' ),
						esc_html( $cache_stats['driver'] )
					);
					?>
				</span>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( SettingsRegistrar::PAGE_SLUG );
				do_settings_sections( SettingsRegistrar::PAGE_SLUG );
				submit_button();
				?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Cache Management', 'markdown-negotiation-for-agents' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="jetstaa_mna_flush_cache">
				<?php wp_nonce_field( 'jetstaa_mna_flush_cache', 'jetstaa_mna_nonce' ); ?>
				<?php submit_button( __( 'Flush Markdown Cache', 'markdown-negotiation-for-agents' ), 'secondary' ); ?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Quick Test', 'markdown-negotiation-for-agents' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Use this curl command to test content negotiation:', 'markdown-negotiation-for-agents' ); ?>
			</p>
			<code>curl -H "Accept: text/markdown" <?php echo esc_html( home_url( '/' ) ); ?></code>
		</div>
		<?php
	}

	/**
	 * Handle cache flush action.
	 *
	 * @return void
	 */
	public function handle_flush_cache(): void {
		// Verify nonce.
		if ( ! isset( $_POST['jetstaa_mna_nonce'] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['jetstaa_mna_nonce'] ) ),
			'jetstaa_mna_flush_cache'
		) ) {
			wp_die( esc_html__( 'Security check failed.', 'markdown-negotiation-for-agents' ) );
		}

		// Check capability.
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'markdown-negotiation-for-agents' ) );
		}

		$this->cache_manager->flush_all();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => SettingsRegistrar::PAGE_SLUG,
					'flushed' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
