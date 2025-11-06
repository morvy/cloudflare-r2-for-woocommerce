<?php
/**
 * Cloudflare R2 Client
 *
 * @package CloudflareR2WC
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * CloudflareR2WC Client Class
 *
 * Wrapper for AWS SDK S3Client to interact with Cloudflare R2
 */
class CFR2WC_Client {
	/**
	 * S3 Client instance
	 *
	 * @var \Aws\S3\S3Client|null
	 */
	private ?\Aws\S3\S3Client $client = null;

	/**
	 * File cache instance
	 *
	 * @var CFR2WC_File_Cache|null
	 */
	private ?CFR2WC_File_Cache $cache = null;

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
		// Decrypt credentials before initializing client.
		$this->settings = $this->prepare_credentials( $this->settings );
		$this->init_client();
		$this->cache = new CFR2WC_File_Cache();
	}

	/**
	 * Prepare credentials by decrypting or loading from constants
	 *
	 * @param array $settings Raw settings.
	 * @return array Settings with decrypted credentials
	 */
	private function prepare_credentials( array $settings ): array {
		$storage_mode = $settings['credential_storage_mode'] ?? 'database';

		// If using constants, override with wp-config.php values.
		if ( 'constants' === $storage_mode ) {
			if ( defined( 'CLOUDFLARE_R2_ACCESS_KEY_ID' ) ) {
				$settings['access_key_id'] = CLOUDFLARE_R2_ACCESS_KEY_ID;
				CFR2WC_Logger::debug( 'Using access key ID from wp-config.php constant' );
			}
			if ( defined( 'CLOUDFLARE_R2_SECRET_ACCESS_KEY' ) ) {
				$settings['secret_access_key'] = CLOUDFLARE_R2_SECRET_ACCESS_KEY;
				CFR2WC_Logger::debug( 'Using secret access key from wp-config.php constant' );
			}
		} else {
			// Database mode: decrypt credentials.
			if ( ! empty( $settings['access_key_id'] ) ) {
				try {
					$settings['access_key_id'] = CFR2WC_Encryption::decrypt( $settings['access_key_id'] );
				} catch ( Exception $e ) {
					CFR2WC_Logger::error( 'Failed to decrypt access key ID: ' . $e->getMessage() );
				}
			}

			if ( ! empty( $settings['secret_access_key'] ) ) {
				try {
					$settings['secret_access_key'] = CFR2WC_Encryption::decrypt( $settings['secret_access_key'] );
				} catch ( Exception $e ) {
					CFR2WC_Logger::error( 'Failed to decrypt secret access key: ' . $e->getMessage() );
				}
			}
		}

		return $settings;
	}

	/**
	 * Initialize S3 Client for R2
	 */
	private function init_client(): void {
		try {
			// Use provided endpoint or build from account_id (backward compatibility).
			if ( ! empty( $this->settings['endpoint'] ) ) {
				$endpoint = $this->settings['endpoint'];
			} elseif ( ! empty( $this->settings['account_id'] ) ) {
				// Fallback: build endpoint from account_id for backward compatibility.
				$endpoint = sprintf(
					'https://%s.r2.cloudflarestorage.com',
					$this->settings['account_id']
				);
			} else {
				CFR2WC_Logger::error( 'No endpoint or account_id provided' );
				return;
			}

			$this->client = new S3Client(
				array(
					'version'                 => 'latest',
					'region'                  => 'auto',
					'endpoint'                => $endpoint,
					'credentials'             => array(
						'key'    => $this->settings['access_key_id'],
						'secret' => $this->settings['secret_access_key'],
					),
					'use_path_style_endpoint' => false,
				)
			);

			CFR2WC_Logger::debug( 'R2 client initialized successfully' );
		} catch ( AwsException $e ) {
			CFR2WC_Logger::error( 'Failed to initialize R2 client: ' . $e->getMessage() );
		}
	}

	/**
	 * Upload file to R2
	 *
	 * @param string $file_path Local file path.
	 * @param string $key Remote key/path in R2.
	 * @param array  $args Additional arguments.
	 * @return string|false False on failure, URL on success
	 */
	public function upload_file( string $file_path, $key, $args = array() ): string|false {
		if ( ! file_exists( $file_path ) ) {
			CFR2WC_Logger::error( 'File does not exist: ' . $file_path );
			return false;
		}

		CFR2WC_Logger::debug(
			'Uploading file to R2',
			array(
				'key'       => $key,
				'file_path' => $file_path,
			)
		);

		try {
			$default_args = array(
				'Bucket'     => $this->settings['bucket_name'],
				'Key'        => $key,
				'SourceFile' => $file_path,
				'ACL'        => 'public-read',
			);

			// Add content type if available.
			$mime_type = mime_content_type( $file_path );
			if ( $mime_type ) {
				$default_args['ContentType'] = $mime_type;
			}

			$upload_args = array_merge( $default_args, $args );

			$result = $this->client->putObject( $upload_args );

			$file_size = filesize( $file_path );
			CFR2WC_Logger::info(
				'File uploaded successfully',
				array(
					'key'  => $key,
					'size' => $file_size,
				)
			);
			return $this->get_object_url( $key );
		} catch ( AwsException $e ) {
			CFR2WC_Logger::error( 'Upload failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Delete file from R2
	 *
	 * @param string $key Remote key/path in R2.
	 * @return bool Success status
	 */
	public function delete_file( $key ): bool {
		CFR2WC_Logger::debug( 'Deleting file from R2', array( 'key' => $key ) );

		try {
			$this->client->deleteObject(
				array(
					'Bucket' => $this->settings['bucket_name'],
					'Key'    => $key,
				)
			);

			CFR2WC_Logger::info( 'File deleted successfully', array( 'key' => $key ) );
			return true;
		} catch ( AwsException $e ) {
			CFR2WC_Logger::error( 'Delete failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Check if file exists in R2
	 *
	 * @param string $key Remote key/path in R2.
	 * @return bool
	 */
	public function file_exists( $key ) {
		try {
			return $this->client->doesObjectExist(
				$this->settings['bucket_name'],
				$key
			);
		} catch ( AwsException $e ) {
			CFR2WC_Logger::error( 'Existence check failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get object URL
	 *
	 * @param string $key Remote key/path in R2.
	 * @return string URL to the object
	 */
	public function get_object_url( $key ): string {
		// Use custom domain if configured.
		if ( ! empty( $this->settings['custom_domain'] ) ) {
			return trailingslashit( $this->settings['custom_domain'] ) . ltrim( $key, '/' );
		}

		// Default R2 public URL format.
		return sprintf(
			'https://%s.r2.dev/%s',
			$this->settings['bucket_name'],
			ltrim( $key, '/' )
		);
	}

	/**
	 * Test connection to R2
	 *
	 * @return array Response with status and message
	 */
	public function test_connection(): array {
		try {
			// Try to list objects (limit to 1) to test connection.
			$result = $this->client->listObjectsV2(
				array(
					'Bucket'  => $this->settings['bucket_name'],
					'MaxKeys' => 1,
				)
			);

			return array(
				'success' => true,
				'message' => __( 'Connection successful!', 'cfr2wc' ),
			);
		} catch ( AwsException $e ) {
			$error_message = sprintf(
				/* translators: %s: error message from AWS exception. */
				__( 'Connection failed: %s', 'cfr2wc' ),
				$e->getMessage()
			);
			return array(
				'success' => false,
				'message' => $error_message,
			);
		}
	}

	/**
	 * Get pre-signed URL for file download
	 *
	 * @param string $key Object key/path.
	 * @param int    $expiration Expiration time in seconds (default 3600 = 1 hour).
	 * @return string|false Pre-signed URL or false on failure
	 */
	public function get_presigned_url( $key, $expiration = 3600 ): string|false {
		CFR2WC_Logger::debug(
			'Generating presigned URL',
			array(
				'key'        => $key,
				'expiration' => $expiration,
			)
		);

		try {
			$cmd = $this->client->getCommand(
				'GetObject',
				array(
					'Bucket' => $this->settings['bucket_name'],
					'Key'    => $key,
				)
			);

			$request = $this->client->createPresignedRequest( $cmd, "+{$expiration} seconds" );

			CFR2WC_Logger::debug( 'Presigned URL generated successfully', array( 'key' => $key ) );
			return (string) $request->getUri();
		} catch ( AwsException $e ) {
			CFR2WC_Logger::error( 'Failed to generate pre-signed URL: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * List objects in bucket
	 *
	 * @param string $prefix Prefix to filter by.
	 * @param int    $max_keys Maximum number of keys to return.
	 * @param bool   $use_cache Whether to use cache (default: true).
	 * @param int    $cache_lifetime Cache lifetime in seconds (default: 300 = 5 minutes).
	 * @return array|false Array of objects or false on failure
	 */
	public function list_objects( string $prefix = '', $max_keys = 1000, $use_cache = true, ?int $cache_lifetime = 300 ) {
		// Generate cache key.
		$cache_key = 'list_objects_' . $this->settings['bucket_name'] . '_' . md5( $prefix . '_' . $max_keys );

		// Try to get from cache first.
		if ( $use_cache && $this->cache ) {
			$cached = $this->cache->get( $cache_key, $cache_lifetime );
			if ( false !== $cached ) {
				CFR2WC_Logger::debug(
					'List objects from cache',
					array(
						'prefix' => $prefix,
						'count'  => count( $cached ),
					)
				);
				return $cached;
			}
		}

		CFR2WC_Logger::debug(
			'Listing objects from R2',
			array(
				'prefix'   => $prefix,
				'max_keys' => $max_keys,
			)
		);

		try {
			$params = array(
				'Bucket'  => $this->settings['bucket_name'],
				'MaxKeys' => $max_keys,
			);

			if ( '' !== $prefix && '0' !== $prefix ) {
				$params['Prefix'] = $prefix;
			}

			$result = $this->client->listObjectsV2( $params );

			$objects = $result['Contents'] ?? array();

			CFR2WC_Logger::debug( 'Objects listed successfully', array( 'count' => count( $objects ) ) );

			// Store in cache.
			if ( $use_cache && $this->cache ) {
				$this->cache->set( $cache_key, $objects );
			}

			return $objects;
		} catch ( AwsException $e ) {
			CFR2WC_Logger::error( 'Failed to list objects: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get object metadata
	 *
	 * @param string $key Object key.
	 * @return array|false Object metadata or false
	 */
	public function get_object_metadata( $key ) {
		try {
			$result = $this->client->headObject(
				array(
					'Bucket' => $this->settings['bucket_name'],
					'Key'    => $key,
				)
			);

			return $result->toArray();
		} catch ( AwsException $e ) {
			CFR2WC_Logger::error( 'Failed to get object metadata: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get S3 Client instance
	 *
	 * @return S3Client
	 */
	public function get_client(): ?\Aws\S3\S3Client {
		return $this->client;
	}

	/**
	 * Clear cache for list operations
	 *
	 * @param string $prefix Optional prefix to clear cache for specific folder.
	 * @return bool Success status
	 */
	public function clear_list_cache( string $prefix = '' ): bool {
		if ( ! $this->cache instanceof \CFR2WC_File_Cache ) {
			return false;
		}

		// Clear specific cache key.
		$cache_key = 'list_objects_' . $this->settings['bucket_name'] . '_' . md5( $prefix . '_1000' );
		return $this->cache->delete( $cache_key );
	}

	/**
	 * Clear all R2 caches
	 *
	 * @return bool Success status
	 */
	public function clear_all_cache(): bool {
		if ( ! $this->cache instanceof \CFR2WC_File_Cache ) {
			return false;
		}

		return $this->cache->clear_all();
	}
}
