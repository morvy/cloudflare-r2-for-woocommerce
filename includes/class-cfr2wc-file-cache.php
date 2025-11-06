<?php
/**
 * File-based cache system for R2 operations
 *
 * @package CloudflareR2WC
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CloudflareR2WC File Cache Class
 *
 * Simple file-based caching to reduce R2 API calls and costs
 */
class CFR2WC_File_Cache {
	/**
	 * Cache directory path
	 *
	 * @var string
	 */
	private readonly string $cache_dir;

	/**
	 * Default cache lifetime in seconds (5 minutes)
	 *
	 * @var int
	 */
	private int $default_lifetime = 300;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Use wp-content/cache/cloudflare-r2/ directory.
		$this->cache_dir = WP_CONTENT_DIR . '/cache/cloudflare-r2/';

		// Create cache directory if it doesn't exist.
		if ( ! file_exists( $this->cache_dir ) ) {
			wp_mkdir_p( $this->cache_dir );

			// Add .htaccess to protect cache directory.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$htaccess = $this->cache_dir . '.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Deny from all\n" );
			}

			// Add index.php to prevent directory listing.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$index = $this->cache_dir . 'index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, "<?php\n// Silence is golden.\n" );
			}
		}
	}

	/**
	 * Get cache file path for a key
	 *
	 * @param string $key Cache key.
	 * @return string Cache file path
	 */
	private function get_cache_file( $key ): string {
		// Hash the key to create a safe filename.
		$hash = md5( $key );
		return $this->cache_dir . $hash . '.cache';
	}

	/**
	 * Get cached data
	 *
	 * @param string   $key Cache key.
	 * @param int|null $max_age Maximum age in seconds (null = use default).
	 * @return mixed|false Cached data or false if not found/expired
	 */
	public function get( $key, ?int $max_age = null ) {
		$cache_file = $this->get_cache_file( $key );

		if ( ! file_exists( $cache_file ) ) {
			return false;
		}

		// Check if cache is expired.
		$max_age ??= $this->default_lifetime;
		$file_time = filemtime( $cache_file );

		if ( time() - $file_time > $max_age ) {
			// Cache expired, delete it.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $cache_file );
			return false;
		}

		// Read and unserialize cached data.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = file_get_contents( $cache_file );

		if ( false === $data ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		return unserialize( $data );
	}

	/**
	 * Set cached data
	 *
	 * @param string $key Cache key.
	 * @param mixed  $data Data to cache.
	 * @return bool Success status
	 */
	public function set( $key, mixed $data ): bool {
		$cache_file = $this->get_cache_file( $key );

		// Serialize data.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$serialized = serialize( $data );

		// Write to cache file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $cache_file, $serialized, LOCK_EX );

		return false !== $result;
	}

	/**
	 * Delete cached data
	 *
	 * @param string $key Cache key.
	 * @return bool Success status
	 */
	public function delete( $key ): bool {
		$cache_file = $this->get_cache_file( $key );

		if ( ! file_exists( $cache_file ) ) {
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged
		return @unlink( $cache_file );
	}

	/**
	 * Clear all cached data
	 *
	 * @return bool Success status
	 */
	public function clear_all(): bool {
		$files = glob( $this->cache_dir . '*.cache' );

		if ( false === $files ) {
			return false;
		}

		foreach ( $files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $file );
		}

		return true;
	}

	/**
	 * Clear expired cache files
	 *
	 * @return int Number of files deleted
	 */
	public function clear_expired(): int {
		$files = glob( $this->cache_dir . '*.cache' );

		if ( false === $files ) {
			return 0;
		}

		$deleted = 0;
		$now     = time();

		foreach ( $files as $file ) {
			$file_time = filemtime( $file );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $now - $file_time > $this->default_lifetime && @unlink( $file ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Cache stats (file_count, total_size)
	 */
	public function get_stats(): array {
		$files = glob( $this->cache_dir . '*.cache' );

		if ( false === $files ) {
			return array(
				'file_count' => 0,
				'total_size' => 0,
			);
		}

		$total_size = 0;

		foreach ( $files as $file ) {
			$total_size += filesize( $file );
		}

		return array(
			'file_count' => count( $files ),
			'total_size' => $total_size,
		);
	}
}
