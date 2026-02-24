<?php
/**
 * Object Cache Driver.
 *
 * Uses the WordPress object cache (Redis, Memcached, etc.)
 * for high-performance Markdown storage.
 *
 * @package IlloDev\MarkdownNegotiation\Cache
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Cache;

use IlloDev\MarkdownNegotiation\Contracts\CacheInterface;

/**
 * Class ObjectCacheDriver
 *
 * Stores cached Markdown in the WordPress object cache.
 * Preferred driver when a persistent object cache is available.
 */
final class ObjectCacheDriver implements CacheInterface {

	/**
	 * Cache group name.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'jetstaa_mna';

	/**
	 * {@inheritDoc}
	 */
	public function get( string $key ): ?string {
		$value = wp_cache_get( $key, self::CACHE_GROUP );

		if ( false === $value ) {
			return null;
		}

		return (string) $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function set( string $key, string $value, int $ttl = 0 ): bool {
		return wp_cache_set( $key, $value, self::CACHE_GROUP, $ttl );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( string $key ): bool {
		return wp_cache_delete( $key, self::CACHE_GROUP );
	}

	/**
	 * {@inheritDoc}
	 */
	public function flush(): bool {
		// WordPress object cache doesn't support group-level flush natively.
		// We use a version key to invalidate all entries.
		$version = (int) wp_cache_get( 'version', self::CACHE_GROUP );
		return wp_cache_set( 'version', $version + 1, self::CACHE_GROUP );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		// Check if a persistent object cache is available.
		return wp_using_ext_object_cache();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_driver_name(): string {
		return 'object-cache';
	}
}
