<?php
/**
 * Cache Manager.
 *
 * Orchestrates caching operations and provides cache invalidation
 * hooks for WordPress post lifecycle events.
 *
 * @package IlloDev\MarkdownNegotiation\Cache
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use IlloDev\MarkdownNegotiation\Contracts\CacheInterface;

/**
 * Class CacheManager
 *
 * High-level cache operations with automatic invalidation.
 */
final class CacheManager {

	/**
	 * Cache key prefix.
	 *
	 * @var string
	 */
	private const KEY_PREFIX = 'md_';

	/**
	 * Constructor.
	 *
	 * @param CacheInterface $driver Cache driver implementation.
	 * @param int            $ttl    Default TTL in seconds.
	 */
	public function __construct(
		private readonly CacheInterface $driver,
		private readonly int $ttl = 3600,
	) {}

	/**
	 * Register WordPress hooks for cache invalidation.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Invalidate on post save/update.
		add_action( 'save_post', array( $this, 'invalidate_post' ), 10, 1 );

		// Invalidate on post trash/delete.
		add_action( 'trashed_post', array( $this, 'invalidate_post' ), 10, 1 );
		add_action( 'deleted_post', array( $this, 'invalidate_post' ), 10, 1 );

		// Invalidate on post status change.
		add_action( 'transition_post_status', array( $this, 'on_status_change' ), 10, 3 );

		// Invalidate on term changes (categories, tags).
		add_action( 'set_object_terms', array( $this, 'invalidate_post' ), 10, 1 );

		// Invalidate on meta changes.
		add_action( 'updated_post_meta', array( $this, 'on_meta_update' ), 10, 4 );
	}

	/**
	 * Build a cache key for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $suffix  Optional suffix (e.g., for different variants).
	 *
	 * @return string Cache key.
	 */
	public function build_key( int $post_id, string $suffix = '' ): string {
		$key = self::KEY_PREFIX . $post_id;

		if ( $suffix ) {
			$key .= '_' . $suffix;
		}

		/**
		 * Filter the cache key for a post.
		 *
		 * @param string $key     The cache key.
		 * @param int    $post_id The post ID.
		 * @param string $suffix  The key suffix.
		 */
		return apply_filters( 'jetstaa_mna_cache_key', $key, $post_id, $suffix );
	}

	/**
	 * Get cached Markdown for a key.
	 *
	 * @param string $key Cache key.
	 *
	 * @return string|null Cached content or null.
	 */
	public function get( string $key ): ?string {
		return $this->driver->get( $key );
	}

	/**
	 * Store Markdown content in cache.
	 *
	 * @param string $key   Cache key.
	 * @param string $value Markdown content.
	 * @param int    $ttl   Optional custom TTL.
	 *
	 * @return bool Success.
	 */
	public function set( string $key, string $value, int $ttl = 0 ): bool {
		return $this->driver->set( $key, $value, $ttl > 0 ? $ttl : $this->ttl );
	}

	/**
	 * Invalidate cached Markdown for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function invalidate_post( int $post_id ): void {
		$key = $this->build_key( $post_id );
		$this->driver->delete( $key );

		/**
		 * Fires after a post's Markdown cache is invalidated.
		 *
		 * @param int $post_id The post ID.
		 */
		do_action( 'jetstaa_mna_cache_invalidated', $post_id );
	}

	/**
	 * Handle post status transitions.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post object.
	 *
	 * @return void
	 */
	public function on_status_change( string $new_status, string $old_status, \WP_Post $post ): void {
		$this->invalidate_post( $post->ID );
	}

	/**
	 * Handle post meta updates.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 *
	 * @return void
	 */
	public function on_meta_update( int $meta_id, int $post_id, string $meta_key, mixed $meta_value ): void {
		// Only invalidate for content-relevant meta changes.
		$ignored_meta = array(
			'_edit_lock',
			'_edit_last',
			'_pingme',
			'_encloseme',
			'_jetstaa_mna_',
		);

		foreach ( $ignored_meta as $prefix ) {
			if ( str_starts_with( $meta_key, $prefix ) ) {
				return;
			}
		}

		$this->invalidate_post( $post_id );
	}

	/**
	 * Flush all cached Markdown content.
	 *
	 * @return bool Success.
	 */
	public function flush_all(): bool {
		$result = $this->driver->flush();

		/**
		 * Fires after the entire Markdown cache is flushed.
		 */
		do_action( 'jetstaa_mna_cache_flushed' );

		return $result;
	}

	/**
	 * Get the active cache driver.
	 *
	 * @return CacheInterface
	 */
	public function get_driver(): CacheInterface {
		return $this->driver;
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array{driver: string, available: bool}
	 */
	public function get_stats(): array {
		return array(
			'driver'    => $this->driver->get_driver_name(),
			'available' => $this->driver->is_available(),
		);
	}
}
