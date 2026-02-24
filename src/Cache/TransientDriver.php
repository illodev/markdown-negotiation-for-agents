<?php
/**
 * Transient Cache Driver.
 *
 * Uses WordPress transients for caching Markdown content.
 * Default fallback when object cache is not available.
 *
 * @package IlloDev\MarkdownNegotiation\Cache
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Cache;

use IlloDev\MarkdownNegotiation\Contracts\CacheInterface;

/**
 * Class TransientDriver
 *
 * Stores cached Markdown using WordPress transients.
 */
final class TransientDriver implements CacheInterface {

	/**
	 * Transient prefix.
	 *
	 * @var string
	 */
	private const PREFIX = 'jetstaa_mna_';

	/**
	 * {@inheritDoc}
	 */
	public function get( string $key ): ?string {
		$value = get_transient( self::PREFIX . $key );

		if ( false === $value ) {
			return null;
		}

		return (string) $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function set( string $key, string $value, int $ttl = 0 ): bool {
		return set_transient( self::PREFIX . $key, $value, $ttl > 0 ? $ttl : 0 );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( string $key ): bool {
		return delete_transient( self::PREFIX . $key );
	}

	/**
	 * {@inheritDoc}
	 */
	public function flush(): bool {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . self::PREFIX . '%',
				'_transient_timeout_' . self::PREFIX . '%'
			)
		);

		return false !== $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return true; // Always available in WordPress.
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_driver_name(): string {
		return 'transient';
	}
}
