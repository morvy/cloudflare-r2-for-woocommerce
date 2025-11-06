<?php
/**
 * Admin Settings Page
 *
 * @package CloudflareR2WC
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CloudflareR2WC Admin Settings Class
 */
class CFR2WC_Admin_Settings {
	/**
	 * Current section.
	 *
	 * @var string
	 */
	private string $current_section = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_cloudflare_r2', array( $this, 'render_settings_page' ) );
		add_action( 'woocommerce_update_options_cloudflare_r2', array( $this, 'update_settings' ) );
		add_action( 'woocommerce_sections_cloudflare_r2', array( $this, 'render_sections' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_cfr2wc_test_connection', array( $this, 'ajax_test_connection' ) );
	}

	/**
	 * Add settings tab to WooCommerce.
	 *
	 * @param array $settings_tabs Existing settings tabs.
	 * @return array Modified settings tabs.
	 */
	public function add_settings_tab( array $settings_tabs ): array {
		$settings_tabs['cloudflare_r2'] = __( 'Cloudflare R2', 'cfr2wc' );
		return $settings_tabs;
	}

	/**
	 * Get sections
	 */
	public function get_sections(): array {
		return array(
			''           => __( 'General', 'cfr2wc' ),
			'connection' => __( 'Connection', 'cfr2wc' ),
		);
	}

	/**
	 * Render sections.
	 */
	public function render_sections(): void {
		global $current_section;

		$sections = $this->get_sections();

		if ( array() === $sections || 1 === count( $sections ) ) {
			return;
		}

		echo '<ul class="subsubsub">';

		$array_keys = array_keys( $sections );

		foreach ( $sections as $id => $label ) {
			echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=cloudflare_r2&section=' . sanitize_title( $id ) ) ) . '" class="' . ( $current_section === $id ? 'current' : '' ) . '">' . esc_html( $label ) . '</a> ' . ( end( $array_keys ) === $id ? '' : '|' ) . ' </li>';
		}

		echo '</ul><br class="clear" />';
	}

	/**
	 * Get settings fields.
	 */
	public function get_settings(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->current_section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		if ( 'connection' === $this->current_section ) {
			return $this->get_connection_settings();
		}

		return $this->get_general_settings();
	}

	/**
	 * Get general settings
	 */
	private function get_general_settings(): array {
		return array(
			array(
				'title' => __( 'General Settings', 'cfr2wc' ),
				'type'  => 'title',
				'desc'  => __( 'Configure general download and security settings.', 'cfr2wc' ),
				'id'    => 'cfr2wc_general_section',
			),
			array(
				'title'   => __( 'Enable Public Downloads', 'cfr2wc' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Allow public access to files without WooCommerce authentication (uses custom domain).', 'cfr2wc' ),
				'id'      => 'cfr2wc_enable_public_downloads',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Check Download Permissions', 'cfr2wc' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Verify customer has purchased the product before allowing download.', 'cfr2wc' ),
				'id'      => 'cfr2wc_check_permissions',
				'default' => 'yes',
			),
			array(
				'title'   => __( 'Use Generic Download Name', 'cfr2wc' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Display "Download" for all files instead of the actual filename.', 'cfr2wc' ),
				'id'      => 'cfr2wc_use_generic_download_name',
				'default' => 'no',
			),
			array(
				'title'             => __( 'Download URL Expiration', 'cfr2wc' ),
				'type'              => 'number',
				'desc'              => __( 'Hours until download link expires (1-720 hours, default 24).', 'cfr2wc' ),
				'id'                => 'cfr2wc_url_expiration_hours',
				'default'           => '24',
				'css'               => 'width:100px;',
				'custom_attributes' => array(
					'min'  => '1',
					'max'  => '720',
					'step' => '1',
				),
			),
			array(
				'title'       => __( 'Custom Domain', 'cfr2wc' ),
				'type'        => 'text',
				'desc'        => __( 'Your R2 custom domain (e.g., cdn.yourdomain.com). Only used when public downloads are enabled.', 'cfr2wc' ),
				'id'          => 'cfr2wc_custom_domain',
				'css'         => 'min-width:400px;',
				'placeholder' => 'cdn.yourdomain.com',
				'class'       => 'cfr2wc-custom-domain-field',
			),
			array(
				'title'   => __( 'Enable Debug Mode', 'cfr2wc' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Enable debug logging using WooCommerce logger. Logs will be available in WooCommerce > Status > Logs.', 'cfr2wc' ),
				'id'      => 'cfr2wc_debug_mode',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Debug Level', 'cfr2wc' ),
				'type'    => 'select',
				'desc'    => __( 'Select the minimum log level to record. Only applies when Debug Mode is enabled.', 'cfr2wc' ),
				'id'      => 'cfr2wc_debug_level',
				'default' => 'error',
				'options' => array(
					'debug'    => __( 'Debug (All messages)', 'cfr2wc' ),
					'info'     => __( 'Info (Informational messages)', 'cfr2wc' ),
					'warning'  => __( 'Warning (Warning messages)', 'cfr2wc' ),
					'error'    => __( 'Error (Error messages only)', 'cfr2wc' ),
					'critical' => __( 'Critical (Critical errors only)', 'cfr2wc' ),
				),
				'class'   => 'cfr2wc-debug-level-field',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cfr2wc_general_section',
			),
		);
	}

	/**
	 * Get connection settings.
	 */
	private function get_connection_settings(): array {
		$storage_mode = get_option( 'cfr2wc_credential_storage_mode', 'database' );

		$credentials_location_desc = '<div id="storage_mode_database" style="' . ( 'database' === $storage_mode ? 'display:block;' : 'display:none;' ) . '">' .
			__( 'Enter your R2 credentials in the fields below. They will be encrypted using AES-256-GCM and stored securely in your WordPress database.', 'cfr2wc' ) .
			'</div>' .
			'<div id="storage_mode_constants" style="' . ( 'constants' === $storage_mode ? 'display:block;' : 'display:none;' ) . '">' .
				sprintf(
					/* translators: %1$s - wp-config.php, %2$s - constant name, %3$s - constant name. */
					__( '<strong>Advanced:</strong> Edit your %1$s file and define the constants %2$s and %3$s containing your R2 credentials. These will be used instead of database values.', 'cfr2wc' ),
					'<code>wp-config.php</code>',
					'<code>CLOUDFLARE_R2_ACCESS_KEY_ID</code>',
					'<code>CLOUDFLARE_R2_SECRET_ACCESS_KEY</code>'
				) .
			'</div>';

		return array(
			array(
				'title' => __( 'R2 Connection Credentials', 'cfr2wc' ),
				'type'  => 'title',
				'desc'  => __( 'Configure your Cloudflare R2 API credentials.', 'cfr2wc' ),
				'id'    => 'cfr2wc_connection_section',
			),
			array(
				'title'   => __( 'Credentials Storage', 'cfr2wc' ),
				'type'    => 'radio',
				'desc'    => $credentials_location_desc,
				'id'      => 'cfr2wc_credential_storage_mode',
				'default' => 'database',
				'options' => array(
					'database'  => __( 'Save to database (encrypted with AES-256-GCM)', 'cfr2wc' ),
					'constants' => __( 'Use wp-config.php constants (unencrypted)', 'cfr2wc' ),
				),
				'class'   => 'cfr2wc-storage-mode-field',
			),
			array(
				'title'             => __( 'R2 Endpoint', 'cfr2wc' ),
				'type'              => 'text',
				'desc'              => __( 'Example: https://account-id.r2.cloudflarestorage.com (Global) or https://account-id.eu.r2.cloudflarestorage.com (EU)', 'cfr2wc' ),
				'id'                => 'cfr2wc_endpoint',
				'css'               => 'min-width:400px;',
				'placeholder'       => 'https://your-account-id.r2.cloudflarestorage.com',
				'custom_attributes' => array( 'required' => 'required' ),
			),
			array(
				'title'             => __( 'Access Key ID', 'cfr2wc' ),
				'type'              => 'text',
				'desc'              => __( 'Your R2 API token access key', 'cfr2wc' ),
				'id'                => 'cfr2wc_access_key_id',
				'css'               => 'min-width:400px;',
				'custom_attributes' => array( 'required' => 'required' ),
				'class'             => 'cfr2wc-credential-field',
			),
			array(
				'title'             => __( 'Secret Access Key', 'cfr2wc' ),
				'type'              => 'password',
				'desc'              => __( 'Your R2 API token secret key', 'cfr2wc' ),
				'id'                => 'cfr2wc_secret_access_key',
				'css'               => 'min-width:400px;',
				'custom_attributes' => array( 'required' => 'required' ),
				'class'             => 'cfr2wc-credential-field',
			),
			array(
				'title'             => __( 'Bucket Name', 'cfr2wc' ),
				'type'              => 'text',
				'desc'              => __( 'The name of your R2 bucket', 'cfr2wc' ),
				'id'                => 'cfr2wc_bucket_name',
				'css'               => 'min-width:400px;',
				'custom_attributes' => array( 'required' => 'required' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cfr2wc_connection_section',
			),
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page(): void {
		woocommerce_admin_fields( $this->get_settings() );

		// Add Test Connection button only on Connection section.
		if ( 'connection' === $this->current_section ) {
			?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Test Connection', 'cfr2wc' ); ?></th>
					<td>
						<p class="description"><?php esc_html_e( 'Test your R2 connection with the credentials entered above.', 'cfr2wc' ); ?></p>
						<button type="button" id="cfr2wc-test-connection" class="button button-secondary">
							<?php esc_html_e( 'Test Connection', 'cfr2wc' ); ?>
						</button>
						<span class="cfr2wc-connection-status" style="display:none; margin-left: 10px;"></span>
					</td>
				</tr>
			</table>
			<?php
		}
	}

	/**
	 * Update settings.
	 */
	public function update_settings(): void {
		// Get storage mode before updating.
		$storage_mode = get_option( 'cfr2wc_credential_storage_mode', 'database' );

		// Standard WooCommerce settings update.
		woocommerce_update_options( $this->get_settings() );

		// Only encrypt credentials if using database storage.
		if ( 'database' === $storage_mode ) {
			// Get the posted credentials.
			$access_key_id     = get_option( 'cfr2wc_access_key_id', '' );
			$secret_access_key = get_option( 'cfr2wc_secret_access_key', '' );

			// Encrypt if not already encrypted.
			if ( ! empty( $access_key_id ) && ! CFR2WC_Encryption::is_encrypted( $access_key_id ) ) {
				try {
					$encrypted_key = CFR2WC_Encryption::encrypt( $access_key_id );
					update_option( 'cfr2wc_access_key_id', $encrypted_key );
					CFR2WC_Logger::info( 'Access key ID encrypted successfully' );
				} catch ( Exception $e ) {
					CFR2WC_Logger::error( 'Failed to encrypt access key ID: ' . $e->getMessage() );
				}
			}

			if ( ! empty( $secret_access_key ) && ! CFR2WC_Encryption::is_encrypted( $secret_access_key ) ) {
				try {
					$encrypted_secret = CFR2WC_Encryption::encrypt( $secret_access_key );
					update_option( 'cfr2wc_secret_access_key', $encrypted_secret );
					CFR2WC_Logger::info( 'Secret access key encrypted successfully' );
				} catch ( Exception $e ) {
					CFR2WC_Logger::error( 'Failed to encrypt secret access key: ' . $e->getMessage() );
				}
			}
		}

		// Update old format settings for backward compatibility.
		// Note: credentials in this array are still encrypted when read from options.
		update_option(
			'cfr2wc_settings',
			array(
				'endpoint'                  => get_option( 'cfr2wc_endpoint', '' ),
				'access_key_id'             => get_option( 'cfr2wc_access_key_id', '' ),
				'secret_access_key'         => get_option( 'cfr2wc_secret_access_key', '' ),
				'bucket_name'               => get_option( 'cfr2wc_bucket_name', '' ),
				'custom_domain'             => get_option( 'cfr2wc_custom_domain', '' ),
				'enable_public_downloads'   => get_option( 'cfr2wc_enable_public_downloads', 'no' ),
				'check_permissions'         => get_option( 'cfr2wc_check_permissions', 'yes' ),
				'use_generic_download_name' => get_option( 'cfr2wc_use_generic_download_name', 'no' ),
				'url_expiration_hours'      => get_option( 'cfr2wc_url_expiration_hours', '24' ),
				'debug_mode'                => get_option( 'cfr2wc_debug_mode', 'no' ),
				'debug_level'               => get_option( 'cfr2wc_debug_level', 'error' ),
				'credential_storage_mode'   => get_option( 'cfr2wc_credential_storage_mode', 'database' ),
			)
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ): void {
		// Only load on WooCommerce settings page.
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// Only load on our specific tab.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		if ( 'cloudflare_r2' !== $current_tab ) {
			return;
		}

		// Determine if we should load minified assets.
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_style(
			'cfr2wc-admin',
			CFR2WC_PLUGIN_URL . 'assets/css/admin' . $suffix . '.css',
			array(),
			CFR2WC_VERSION
		);

		wp_enqueue_script(
			'cfr2wc-admin',
			CFR2WC_PLUGIN_URL . 'assets/js/admin' . $suffix . '.js',
			array( 'jquery' ),
			CFR2WC_VERSION,
			true
		);

		wp_localize_script(
			'cfr2wc-admin',
			'cfr2wcAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'cfr2wc_admin_nonce' ),
			)
		);
	}

	/**
	 * AJAX: Test R2 connection.
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'cfr2wc_admin_nonce', 'nonce' );

		// phpcs:ignore WordPress.WP.Capabilities.Unknown
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'cfr2wc' ) ) );
		}

		// Get credentials from POST.
		$endpoint          = isset( $_POST['endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['endpoint'] ), array( 'http', 'https' ) ) : '';
		$access_key_id     = isset( $_POST['access_key_id'] ) ? sanitize_text_field( wp_unslash( $_POST['access_key_id'] ) ) : '';
		$secret_access_key = isset( $_POST['secret_access_key'] ) ? sanitize_text_field( wp_unslash( $_POST['secret_access_key'] ) ) : '';
		$bucket_name       = isset( $_POST['bucket_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bucket_name'] ) ) : '';

		// Validate required fields.
		if ( empty( $endpoint ) || empty( $access_key_id ) || empty( $secret_access_key ) || empty( $bucket_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields', 'cfr2wc' ) ) );
		}

		try {
			// Create temporary R2 client with provided credentials.
			$settings = array(
				'endpoint'          => $endpoint,
				'access_key_id'     => $access_key_id,
				'secret_access_key' => $secret_access_key,
				'bucket_name'       => $bucket_name,
			);

			$r2_client = new CFR2WC_Client( $settings );

			// Try to list objects (just check if bucket is accessible).
			$result = $r2_client->list_objects( '', 1 );

			if ( false !== $result ) {
				wp_send_json_success( array( 'message' => __( 'Connection successful! Your credentials are valid.', 'cfr2wc' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Connection failed. Please check your credentials.', 'cfr2wc' ) ) );
			}
		} catch ( Exception $e ) {
			/* translators: %s: Error message from exception. */
			wp_send_json_error( array( 'message' => sprintf( __( 'Connection error: %s', 'cfr2wc' ), $e->getMessage() ) ) );
		}
	}
}
