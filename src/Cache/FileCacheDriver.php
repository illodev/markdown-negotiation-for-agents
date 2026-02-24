<?php
/**
 * File Cache Driver.
 *
 * Stores cached Markdown as files in the uploads directory.
 * Useful for sites without a persistent object cache that want
 * to avoid transient bloat in the options table.
 *
 * @package IlloDev\MarkdownNegotiation\Cache
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Cache;

use IlloDev\MarkdownNegotiation\Contracts\CacheInterface;

/**
 * Class FileCacheDriver
 *
 * File-based cache storage in wp-content/uploads/jetstaa-mna-cache/.
 */
final class FileCacheDriver implements CacheInterface {

	/**
	 * Cache directory path.
	 *
	 * @var string
	 */
	private string $cache_dir;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$upload_dir      = wp_upload_dir();
		$this->cache_dir = trailingslashit( $upload_dir['basedir'] ) . 'jetstaa-mna-cache/';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get( string $key ): ?string {
		$file = $this->get_file_path( $key );

		if ( ! file_exists( $file ) ) {
			return null;
		}

		// Check expiry.
		$meta_file = $file . '.meta';
		if ( file_exists( $meta_file ) ) {
			$meta = (array) json_decode( (string) file_get_contents( $meta_file ), true );
			$ttl  = $meta['ttl'] ?? 0;

			if ( $ttl > 0 ) {
				$created = $meta['created'] ?? 0;
				if ( time() - $created > $ttl ) {
					// Expired.
					$this->delete( $key );
					return null;
				}
			}
		}

		$content = file_get_contents( $file );
		return false !== $content ? $content : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function set( string $key, string $value, int $ttl = 0 ): bool {
		$this->ensure_cache_dir();

		$file = $this->get_file_path( $key );

		// Write content.
		$result = file_put_contents( $file, $value, LOCK_EX );

		if ( false === $result ) {
			return false;
		}

		// Write meta.
		$meta = array(
			'created' => time(),
			'ttl'     => $ttl,
			'size'    => strlen( $value ),
		);

		file_put_contents( $file . '.meta', wp_json_encode( $meta ), LOCK_EX );

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( string $key ): bool {
		$file = $this->get_file_path( $key );

		$deleted = true;

		if ( file_exists( $file ) ) {
			$deleted = unlink( $file );
		}

		$meta_file = $file . '.meta';
		if ( file_exists( $meta_file ) ) {
			unlink( $meta_file );
		}

		return $deleted;
	}

	/**
	 * {@inheritDoc}
	 */
	public function flush(): bool {
		if ( ! is_dir( $this->cache_dir ) ) {
			return true;
		}

		$files = glob( $this->cache_dir . '*' );

		if ( false === $files ) {
			return false;
		}

		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		$this->ensure_cache_dir();
		return is_dir( $this->cache_dir ) && is_writable( $this->cache_dir );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_driver_name(): string {
		return 'file';
	}

	/**
	 * Get the file path for a cache key.
	 *
	 * @param string $key Cache key.
	 *
	 * @return string File path.
	 */
	private function get_file_path( string $key ): string {
		// Use a hash to avoid filesystem issues with special characters.
		$hash = md5( $key );

		// Use subdirectory based on first 2 chars for performance with many files.
		$subdir = substr( $hash, 0, 2 );

		return $this->cache_dir . $subdir . '/' . $hash . '.md';
	}

	/**
	 * Ensure the cache directory exists.
	 *
	 * @return void
	 */
	private function ensure_cache_dir(): void {
		if ( ! is_dir( $this->cache_dir ) ) {
			wp_mkdir_p( $this->cache_dir );

			// Add .htaccess for security.
			$htaccess = $this->cache_dir . '.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Deny from all\n" );
			}

			// Add index.php for security.
			$index = $this->cache_dir . 'index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, "<?php\n// Silence is golden.\n" );
			}
		}
	}
}
