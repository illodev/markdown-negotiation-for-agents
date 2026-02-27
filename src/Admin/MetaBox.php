<?php
/**
 * Meta Box.
 *
 * Adds a per-post meta box for Markdown preview and per-post controls.
 *
 * @package IlloDev\MarkdownNegotiation\Admin
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

/**
 * Class MetaBox
 *
 * Per-post Markdown controls and preview.
 */
final class MetaBox {

	/**
	 * Meta key for per-post disable flag.
	 *
	 * @var string
	 */
	private const META_KEY = '_jetstaa_mna_disabled';

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'jetstaa_mna_meta_box';

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
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Register the meta box.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		$post_types = (array) ( $this->settings['post_types'] ?? array( 'post', 'page' ) );

		if ( ( $this->settings['woocommerce'] ?? true ) && class_exists( 'WooCommerce' ) ) {
			$post_types[] = 'product';
		}

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'jetstaa-mna-settings',
				__( 'Markdown Negotiation', 'markdown-negotiation-for-agents' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'low'
			);
		}
	}

	/**
	 * Render the meta box.
	 *
	 * @param WP_Post $post Current post.
	 *
	 * @return void
	 */
	public function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, 'jetstaa_mna_meta_nonce' );

		$disabled = (bool) get_post_meta( $post->ID, self::META_KEY, true );
		?>
		<label>
			<input type="checkbox" name="jetstaa_mna_disabled" value="1" <?php checked( $disabled ); ?> />
			<?php esc_html_e( 'Disable Markdown for this post', 'markdown-negotiation-for-agents' ); ?>
		</label>

		<?php if ( 'publish' === $post->post_status ) : ?>
			<p style="margin-top: 12px;">
				<a href="<?php echo esc_url( add_query_arg( 'format', 'markdown', get_permalink( $post ) ) ); ?>" target="_blank" class="button button-small">
					<?php esc_html_e( 'Preview Markdown', 'markdown-negotiation-for-agents' ); ?>
				</a>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save the per-post meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function save_meta( int $post_id, WP_Post $post ): void {
		// Verify nonce.
		if ( ! isset( $_POST['jetstaa_mna_meta_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['jetstaa_mna_meta_nonce'] ) ),
				self::NONCE_ACTION
			)
		) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save or delete meta.
		$disabled = ! empty( $_POST['jetstaa_mna_disabled'] );

		if ( $disabled ) {
			update_post_meta( $post_id, self::META_KEY, '1' );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	/**
	 * Check if Markdown is disabled for a specific post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True if disabled.
	 */
	public static function is_disabled_for_post( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, self::META_KEY, true );
	}
}
