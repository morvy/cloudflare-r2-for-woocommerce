<?php
/**
 * File Cache Manager
 *
 * Manages cached R2 file listings in database
 *
 * @package CloudflareR2WC
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CloudflareR2WC File Cache Manager Class
 *
 * Caches R2 file listings to reduce API costs
 */
class CFR2WC_File_Cache_Manager {
	/**
	 * Cache lifetime in seconds (5 minutes).
	 *
	 * @var int
	 */
	private int $cache_lifetime = 300;

	/**
	 * Constructor.
	 *
	 * @param CFR2WC_Client $r2_client R2 client instance.
	 */
	public function __construct( private readonly CFR2WC_Client $r2_client ) {
	}

	/**
	 * Sync R2 files to cache table.
	 *
	 * @param bool $force_refresh Force refresh even if cache is fresh.
	 * @return array Sync results.
	 */
	public function sync_r2_files( bool $force_refresh = false ): array {
		global $wpdb;

		// Check if cache is still fresh.
		if ( ! $force_refresh && ! $this->is_cache_expired() ) {
			return array(
				'success' => true,
				'synced'  => 0,
				'skipped' => 0,
				'deleted' => 0,
				'message' => __( 'Cache is still fresh, no sync needed', 'cfr2wc' ),
			);
		}

		// List all objects from R2.
		$objects = $this->r2_client->list_objects( '', 10000, false );

		if ( false === $objects ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to list R2 objects', 'cfr2wc' ),
			);
		}

		$table   = CFR2WC_Database::get_table_name( CFR2WC_Database::TABLE_FILE_CACHE );
		$synced  = 0;
		$skipped = 0;

		// Track object keys from R2.
		$r2_keys = array();

		foreach ( $objects as $object ) {
			$key       = $object['Key'];
			$r2_keys[] = $key;

			// Skip folders (keys ending with /).
			if ( str_ends_with( (string) $key, '/' ) ) {
				continue;
			}

			// Extract folder path.
			$folder_path = dirname( (string) $key );
			if ( '.' === $folder_path ) {
				$folder_path = '';
			}

			// Check if exists in cache.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT id FROM {$table} WHERE object_key = %s",
					$key
				)
			);

			$data = array(
				'object_key'    => $key,
				'file_name'     => basename( (string) $key ),
				'file_size'     => $object['Size'] ?? 0,
				'last_modified' => isset( $object['LastModified'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $object['LastModified'] ) ) : null,
				'folder_path'   => $folder_path,
				'cached_at'     => current_time( 'mysql' ),
			);

			if ( $existing ) {
				// Update existing.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $table, $data, array( 'object_key' => $key ) );
				++$skipped;
			} else {
				// Insert new.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert( $table, $data );
				++$synced;
			}
		}

		// Delete cached files that no longer exist in R2.
		$deleted = 0;
		if ( array() !== $r2_keys ) {
			$placeholders = implode( ',', array_fill( 0, count( $r2_keys ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted_rows = $wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					"DELETE FROM {$table} WHERE object_key NOT IN ({$placeholders})",
					...$r2_keys
				)
			);
			$deleted = false !== $deleted_rows ? $deleted_rows : 0;
		}

		$message = sprintf(
			/* translators: %1$d: number of new files, %2$d: number of updated files, %3$d: number of deleted files. */
			__( 'Synced %1$d new files, updated %2$d existing, deleted %3$d obsolete', 'cfr2wc' ),
			$synced,
			$skipped,
			$deleted
		);

		return array(
			'success' => true,
			'synced'  => $synced,
			'skipped' => $skipped,
			'deleted' => $deleted,
			'total'   => count( $objects ),
			'message' => $message,
		);
	}

	/**
	 * Get folder tree structure.
	 *
	 * @return array Nested folder structure.
	 */
	public function get_folder_tree(): array {
		// Check cache file first.
		$cache_file     = $this->get_folder_tree_cache_file();
		$cache_duration = 3600; // 1 hour cache.

		if ( file_exists( $cache_file ) ) {
			$cache_age = time() - filemtime( $cache_file );
			if ( $cache_age < $cache_duration ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$cached_data = json_decode( file_get_contents( $cache_file ), true );
				if ( null !== $cached_data ) {
					return $cached_data;
				}
			}
		}

		// Get all folder paths from R2 (not just from file cache).
		$folders = $this->get_all_r2_folders();

		if ( array() === $folders ) {
			return array();
		}

		// Build tree structure.
		$tree = $this->build_folder_tree( $folders );

		// Cache to file.
		$this->cache_folder_tree( $tree );

		return $tree;
	}

	/**
	 * Get folder tree cache file path.
	 */
	private function get_folder_tree_cache_file(): string {
		$upload_dir = wp_upload_dir();
		$cache_dir  = trailingslashit( $upload_dir['basedir'] ) . 'cfr2wc-cache';

		if ( ! file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
			// Add .htaccess to protect cache directory.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $cache_dir . '/.htaccess', 'Deny from all' );
		}

		return $cache_dir . '/folder-tree.json';
	}

	/**
	 * Cache folder tree to JSON file.
	 *
	 * @param array $tree Folder tree.
	 */
	private function cache_folder_tree( array $tree ): void {
		$cache_file = $this->get_folder_tree_cache_file();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.json_encode_json_encode
		file_put_contents( $cache_file, json_encode( $tree, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Get all folders from R2.
	 *
	 * @return array Folder paths.
	 */
	private function get_all_r2_folders(): array {
		$client = $this->r2_client->get_client();

		if ( ! $client instanceof \Aws\S3\S3Client ) {
			return array();
		}

		try {
			$folders            = array();
			$prefix             = '';
			$continuation_token = null;

			// Get bucket name from settings.
			$settings    = get_option( 'cfr2wc_settings', array() );
			$bucket_name = $settings['bucket_name'] ?? '';

			if ( empty( $bucket_name ) ) {
				return array();
			}

			// List all objects to extract folder paths.
			do {
				$params = array(
					'Bucket'  => $bucket_name,
					'Prefix'  => $prefix,
					'MaxKeys' => 1000,
				);

				if ( $continuation_token ) {
					$params['ContinuationToken'] = $continuation_token;
				}

				$result = $client->listObjectsV2( $params );

				// Extract folder paths from object keys.
				if ( isset( $result['Contents'] ) ) {
					foreach ( $result['Contents'] as $object ) {
						$key = $object['Key'];

						// Check if this is a folder object (ends with /).
						if ( str_ends_with( (string) $key, '/' ) ) {
							$folder_path = rtrim( (string) $key, '/' );
							if ( $folder_path && ! in_array( $folder_path, $folders, true ) ) {
								$folders[] = $folder_path;
							}

							// Also add all parent folders.
							$parts = explode( '/', $folder_path );
							$path  = '';
							foreach ( $parts as $part ) {
								$path .= ( '' !== $path && '0' !== $path ? '/' : '' ) . $part;
								if ( ! in_array( $path, $folders, true ) ) {
									$folders[] = $path;
								}
							}
						} else {
							// Extract all folder segments from file keys.
							$parts = explode( '/', (string) $key );
							array_pop( $parts ); // Remove filename.

							if ( array() !== $parts ) {
								// Build cumulative paths.
								$path = '';
								foreach ( $parts as $part ) {
									$path .= ( '' !== $path && '0' !== $path ? '/' : '' ) . $part;
									if ( ! in_array( $path, $folders, true ) ) {
										$folders[] = $path;
									}
								}
							}
						}
					}
				}

				$continuation_token = $result['IsTruncated'] ? $result['NextContinuationToken'] : null;

			} while ( $continuation_token );

			sort( $folders );
			return $folders;

		} catch ( Exception $e ) {
			CFR2WC_Logger::error( 'Error getting R2 folders: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Build nested folder tree from paths.
	 *
	 * @param array $paths Folder paths.
	 * @return array<string, mixed> Nested structure.
	 */
	private function build_folder_tree( array $paths ): array {
		$tree = array();

		foreach ( $paths as $path ) {
			$parts   = explode( '/', (string) $path );
			$current = &$tree;

			foreach ( $parts as $part ) {
				if ( ! array_key_exists( $part, $current ) ) {
					$current[ $part ] = array();
				}
				// PHPStan: After we set it to array, we know it's an array.
				/**
				 * Current reference array.
				 *
				 * @var array<string, mixed> $current
				 */
				$current = &$current[ $part ];
			}
		}

		return $tree;
	}

	/**
	 * Search files by name.
	 *
	 * @param string $search_term Search term.
	 * @param string $folder_path Limit to specific folder.
	 * @return array File results.
	 */
	public function search_files( string $search_term, string $folder_path = '' ): array {
		global $wpdb;

		$table = CFR2WC_Database::get_table_name( CFR2WC_Database::TABLE_FILE_CACHE );

		$where  = array();
		$params = array();

		// Search by filename.
		if ( '' !== $search_term && '0' !== $search_term ) {
			$where[]  = 'file_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search_term ) . '%';
		}

		// Filter by folder.
		if ( '' !== $folder_path && '0' !== $folder_path ) {
			$where[]  = 'folder_path = %s';
			$params[] = $folder_path;
		}

		$where_clause = array() === $where ? '' : 'WHERE ' . implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = "SELECT * FROM {$table} {$where_clause} ORDER BY file_name ASC LIMIT 50";

		if ( array() !== $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $query, ...$params );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query );

		// Format results.
		return array_map(
			function ( $file ): array {
				return array(
					'id'                  => $file->id,
					'object_key'          => $file->object_key,
					'file_name'           => $file->file_name,
					'file_size'           => $file->file_size,
					'file_size_formatted' => size_format( $file->file_size, 2 ),
					'mime_type'           => $file->mime_type,
					'folder_path'         => $file->folder_path,
					'last_modified'       => $file->last_modified,
				);
			},
			$results
		);
	}

	/**
	 * Get files in specific folder.
	 *
	 * @param string $folder_path Folder path (empty for root).
	 * @return array Files.
	 */
	public function get_files_in_folder( string $folder_path = '' ): array {
		global $wpdb;

		$table = CFR2WC_Database::get_table_name( CFR2WC_Database::TABLE_FILE_CACHE );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE folder_path = %s ORDER BY file_name ASC",
				$folder_path
			)
		);

		// Format results.
		return array_map(
			function ( $file ): array {
				return array(
					'id'                  => $file->id,
					'object_key'          => $file->object_key,
					'file_name'           => $file->file_name,
					'file_size'           => $file->file_size,
					'file_size_formatted' => size_format( $file->file_size, 2 ),
					'mime_type'           => $file->mime_type,
					'folder_path'         => $file->folder_path,
					'last_modified'       => $file->last_modified,
				);
			},
			$results
		);
	}

	/**
	 * Check if cache is expired.
	 *
	 * @return bool True if expired.
	 */
	public function is_cache_expired(): bool {
		global $wpdb;

		$table = CFR2WC_Database::get_table_name( CFR2WC_Database::TABLE_FILE_CACHE );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$latest_cache = $wpdb->get_var( "SELECT MAX(cached_at) FROM {$table}" );

		if ( ! $latest_cache ) {
			return true;
		}

		$cache_time = strtotime( (string) $latest_cache );
		return ( time() - $cache_time ) > $this->cache_lifetime;
	}

	/**
	 * Check if cache is empty.
	 *
	 * @return bool True if empty.
	 */
	public function is_cache_empty(): bool {
		global $wpdb;

		$table = CFR2WC_Database::get_table_name( CFR2WC_Database::TABLE_FILE_CACHE );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		return '0' === $count || 0 === $count;
	}

	/**
	 * Clear expired cache entries.
	 *
	 * @return int Number of entries deleted.
	 */
	public function clear_expired_cache(): int {
		global $wpdb;

		$table = CFR2WC_Database::get_table_name( CFR2WC_Database::TABLE_FILE_CACHE );

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $this->cache_lifetime );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$table} WHERE cached_at < %s",
				$cutoff
			)
		);

		return false !== $deleted ? $deleted : 0;
	}

	/**
	 * Clear all cache.
	 *
	 * @return bool Success.
	 */
	public function clear_all_cache(): bool {
		global $wpdb;

		$table = CFR2WC_Database::get_table_name( CFR2WC_Database::TABLE_FILE_CACHE );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query( "TRUNCATE TABLE {$table}" );

		return false !== $result;
	}
}
