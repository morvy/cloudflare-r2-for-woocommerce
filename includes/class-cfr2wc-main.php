<?php
/**
 * Main plugin class
 *
 * @package CloudflareR2WC
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main CFR2WC_Main Class
 */
class CFR2WC_Main {
	/**
	 * The single instance of the class.
	 *
	 * @var CFR2WC_Main|null
	 */
	protected static ?CFR2WC_Main $_instance = null;

	/**
	 * R2 Client.
	 *
	 * @var CFR2WC_Client|null
	 */
	public ?CFR2WC_Client $r2_client = null;

	/**
	 * File Cache Manager.
	 *
	 * @var CFR2WC_File_Cache_Manager|null
	 */
	public ?CFR2WC_File_Cache_Manager $file_cache_manager = null;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	public array $settings = array();

	/**
	 * Main instance
	 */
	public static function instance(): CFR2WC_Main {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings = get_option( 'cfr2wc_settings', array() );
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes(): void {
		require_once CFR2WC_PLUGIN_DIR . 'includes/class-cfr2wc-client.php';
		require_once CFR2WC_PLUGIN_DIR . 'includes/class-cfr2wc-shortcode.php';
		require_once CFR2WC_PLUGIN_DIR . 'includes/class-cfr2wc-download-handler.php';

		// Admin classes.
		if ( is_admin() ) {
			require_once CFR2WC_PLUGIN_DIR . 'includes/admin/class-cfr2wc-admin-settings.php';
			require_once CFR2WC_PLUGIN_DIR . 'includes/admin/class-cfr2wc-product-r2-integration.php';
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		// Initialize R2 client if configured.
		if ( $this->is_configured() ) {
			$this->r2_client          = new CFR2WC_Client( $this->settings );
			$this->file_cache_manager = new CFR2WC_File_Cache_Manager( $this->r2_client );
		}

		// Initialize shortcode system (returns filename only, not URLs).
		if ( $this->r2_client instanceof \CFR2WC_Client ) {
			new CFR2WC_Shortcode( $this->r2_client, $this->settings );
		}

		// Initialize download handler.
		if ( $this->r2_client instanceof \CFR2WC_Client ) {
			new CFR2WC_Download_Handler( $this->r2_client );
		}

		// Admin hooks.
		if ( is_admin() ) {
			new CFR2WC_Admin_Settings();

			if ( $this->r2_client && $this->file_cache_manager ) {
				new CFR2WC_Product_R2_Integration( $this->r2_client, $this->file_cache_manager );
			}
		}
	}

	/**
	 * Check if R2 is configured
	 */
	public function is_configured(): bool {
		return ! empty( $this->settings['endpoint'] )
			&& ! empty( $this->settings['access_key_id'] )
			&& ! empty( $this->settings['secret_access_key'] )
			&& ! empty( $this->settings['bucket_name'] );
	}

	/**
	 * Get settings
	 */
	public function get_settings(): array {
		return $this->settings;
	}
}
