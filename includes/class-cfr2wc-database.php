<?php
/**
 * Database Schema Management
 *
 * @package CloudflareR2WC
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CloudflareR2WC Database Class
 *
 * Manages database schema for file cache
 */
class CFR2WC_Database {
	/**
	 * Database version
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Table name constants
	 */
	const TABLE_FILE_CACHE = 'cfr2wc_file_cache';

	/**
	 * Get table name with WordPress prefix
	 *
	 * @param string $table Table constant.
	 * @return string Full table name with prefix
	 */
	public static function get_table_name( string $table ): string {
		global $wpdb;
		return $wpdb->prefix . $table;
	}

	/**
	 * Create database tables
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table           = self::get_table_name( self::TABLE_FILE_CACHE );

		// File cache table - stores R2 file listings.
		$sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            object_key varchar(500) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_size bigint(20) unsigned DEFAULT 0,
            mime_type varchar(100) DEFAULT NULL,
            last_modified datetime DEFAULT NULL,
            folder_path varchar(500) DEFAULT NULL,
            cached_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY object_key (object_key),
            KEY folder_path (folder_path),
            KEY file_name (file_name(191)),
            KEY last_modified (last_modified),
            KEY cached_at (cached_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'cfr2wc_db_version', self::DB_VERSION );
	}

	/**
	 * Drop all plugin tables
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$table = self::get_table_name( self::TABLE_FILE_CACHE );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );

		delete_option( 'cfr2wc_db_version' );
	}

	/**
	 * Check database version and create/update tables if needed
	 */
	public static function check_database_version(): void {
		$current_version = get_option( 'cfr2wc_db_version' );

		if ( self::DB_VERSION !== $current_version ) {
			self::create_tables();
		}
	}

	/**
	 * Get current database version
	 *
	 * @return string Database version
	 */
	public static function get_db_version(): string {
		return self::DB_VERSION;
	}
}
