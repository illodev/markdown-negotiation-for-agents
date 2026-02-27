<?php
/**
 * Cache interface.
 *
 * Defines the contract for cache driver implementations.
 *
 * @package IlloDev\MarkdownNegotiation\Contracts
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface CacheInterface
 *
 * Abstraction for cache storage backends.
 */
interface CacheInterface {

	/**
	 * Retrieve a cached markdown value.
	 *
	 * @param string $key Cache key.
	 *
	 * @return string|null Cached value or null if not found/expired.
	 */
	public function get( string $key ): ?string;

	/**
	 * Store a value in cache.
	 *
	 * @param string $key   Cache key.
	 * @param string $value The value to cache.
	 * @param int    $ttl   Time-to-live in seconds. 0 = no expiry.
	 *
	 * @return bool True on success.
	 */
	public function set( string $key, string $value, int $ttl = 0 ): bool;

	/**
	 * Delete a cached value.
	 *
	 * @param string $key Cache key.
	 *
	 * @return bool True on success.
	 */
	public function delete( string $key ): bool;

	/**
	 * Flush all cached markdown values.
	 *
	 * @return bool True on success.
	 */
	public function flush(): bool;

	/**
	 * Check if the cache driver is available.
	 *
	 * @return bool True if available.
	 */
	public function is_available(): bool;

	/**
	 * Get the driver name/identifier.
	 *
	 * @return string Driver name.
	 */
	public function get_driver_name(): string;
}
