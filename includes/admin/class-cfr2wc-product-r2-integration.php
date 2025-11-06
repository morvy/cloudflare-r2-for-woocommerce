<?php
/**
 * Product R2 Integration
 *
 * Adds R2 file selector to WooCommerce product edit screen
 *
 * @package CloudflareR2WC
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CloudflareR2WC Product R2 Integration Class
 *
 * Integrates R2 file selector with WooCommerce product downloads
 */
class CFR2WC_Product_R2_Integration {
	/**
	 * Constructor.
	 *
	 * @param CFR2WC_Client             $r2_client R2 client instance.
	 * @param CFR2WC_File_Cache_Manager $file_cache_manager File cache manager.
	 */
	public function __construct( private readonly CFR2WC_Client $r2_client, private readonly CFR2WC_File_Cache_Manager $file_cache_manager ) {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		// Add R2 buttons to product downloads metabox.
		add_action( 'woocommerce_product_options_downloads', array( $this, 'render_r2_buttons' ) );

		// Enqueue assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_cfr2wc_get_folder_tree', array( $this, 'ajax_get_folder_tree' ) );
		add_action( 'wp_ajax_cfr2wc_search_files', array( $this, 'ajax_search_files' ) );
		add_action( 'wp_ajax_cfr2wc_upload_to_r2', array( $this, 'ajax_upload_to_r2' ) );
		add_action( 'wp_ajax_cfr2wc_sync_r2_files', array( $this, 'ajax_sync_r2_files' ) );
	}

	/**
	 * Render R2 buttons in product downloads section.
	 * Note: Buttons are injected via JavaScript into each file row.
	 */
	public function render_r2_buttons(): void {
		// JavaScript will inject R2 buttons into each file row.
		// Modal markup follows below.
		?>

		<!-- R2 File Selector Modal -->
		<div id="cfr2wc-modal" class="cfr2wc-modal" style="display: none;">
			<div class="cfr2wc-modal-overlay"></div>
			<div class="cfr2wc-modal-content">
				<div class="cfr2wc-modal-header">
					<h2><?php esc_html_e( 'Select File from R2', 'cfr2wc' ); ?></h2>
					<button type="button" class="cfr2wc-modal-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="cfr2wc-modal-body">
					<!-- Breadcrumb Navigation -->
					<div class="cfr2wc-breadcrumb-section">
						<label><?php esc_html_e( 'Navigate:', 'cfr2wc' ); ?></label>
						<div class="cfr2wc-breadcrumb-wrapper">
							<div class="cfr2wc-breadcrumb" id="cfr2wc-breadcrumb">
								<span class="cfr2wc-breadcrumb-item cfr2wc-breadcrumb-root" data-path="">
									<span class="dashicons dashicons-admin-home"></span>
									<?php esc_html_e( 'Root', 'cfr2wc' ); ?>
								</span>
								<div class="cfr2wc-breadcrumb-input-container">
									<input type="text" id="cfr2wc-breadcrumb-input" class="cfr2wc-breadcrumb-input" placeholder="<?php esc_attr_e( 'folder...', 'cfr2wc' ); ?>" autocomplete="off">
									<div class="cfr2wc-autocomplete-dropdown" id="cfr2wc-autocomplete-dropdown" style="display: none;"></div>
								</div>
							</div>
						</div>
					</div>

					<!-- File Search/Select -->
					<div class="cfr2wc-file-section">
						<div class="cfr2wc-file-search">
							<label><?php esc_html_e( 'Select File:', 'cfr2wc' ); ?></label>
							<select id="cfr2wc-file-select" style="width: 100%;">
								<option value=""><?php esc_html_e( 'Type to search files in current folder...', 'cfr2wc' ); ?></option>
							</select>
						</div>
						<div class="cfr2wc-file-preview" id="cfr2wc-file-preview" style="display: none;">
							<h4><?php esc_html_e( 'Selected File', 'cfr2wc' ); ?></h4>
							<table class="cfr2wc-file-details">
								<tr>
									<th><?php esc_html_e( 'File Name:', 'cfr2wc' ); ?></th>
									<td id="cfr2wc-preview-name"></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Size:', 'cfr2wc' ); ?></th>
									<td id="cfr2wc-preview-size"></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Path:', 'cfr2wc' ); ?></th>
									<td id="cfr2wc-preview-path"></td>
								</tr>
							</table>
						</div>
					</div>
				</div>
				<div class="cfr2wc-modal-footer">
					<button type="button" class="button button-secondary cfr2wc-modal-cancel">
						<?php esc_html_e( 'Cancel', 'cfr2wc' ); ?>
					</button>
					<button type="button" class="button button-primary cfr2wc-add-file-btn" disabled>
						<?php esc_html_e( 'Add to Product', 'cfr2wc' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Upload Modal -->
		<div id="cfr2wc-upload-modal" class="cfr2wc-modal" style="display: none;">
			<div class="cfr2wc-modal-overlay"></div>
			<div class="cfr2wc-modal-content cfr2wc-upload-modal-content">
				<div class="cfr2wc-modal-header">
					<h2><?php esc_html_e( 'Upload File to R2', 'cfr2wc' ); ?></h2>
					<button type="button" class="cfr2wc-upload-modal-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="cfr2wc-modal-body">
					<div class="cfr2wc-upload-area">
						<!-- Breadcrumb Navigation for Upload -->
						<div class="cfr2wc-breadcrumb-section">
							<label><?php esc_html_e( 'Upload to:', 'cfr2wc' ); ?></label>
							<div class="cfr2wc-breadcrumb-wrapper">
								<div class="cfr2wc-breadcrumb" id="cfr2wc-upload-breadcrumb">
									<span class="cfr2wc-breadcrumb-item cfr2wc-breadcrumb-root" data-path="">
										<span class="dashicons dashicons-admin-home"></span>
										<?php esc_html_e( 'Root', 'cfr2wc' ); ?>
									</span>
									<div class="cfr2wc-breadcrumb-input-container">
										<input type="text" id="cfr2wc-upload-breadcrumb-input" class="cfr2wc-breadcrumb-input" placeholder="<?php esc_attr_e( 'folder...', 'cfr2wc' ); ?>" autocomplete="off">
										<div class="cfr2wc-autocomplete-dropdown" id="cfr2wc-upload-autocomplete-dropdown" style="display: none;"></div>
									</div>
								</div>
							</div>
						</div>

						<input type="file" id="cfr2wc-file-input" style="display: none;">
						<div class="cfr2wc-drop-zone" id="cfr2wc-drop-zone">
							<span class="dashicons dashicons-upload"></span>
							<p><?php esc_html_e( 'Click to select file or drag and drop', 'cfr2wc' ); ?></p>
						</div>
						<div class="cfr2wc-upload-progress" id="cfr2wc-upload-progress" style="display: none;">
							<div class="cfr2wc-progress-bar">
								<div class="cfr2wc-progress-fill"></div>
							</div>
							<p class="cfr2wc-upload-status"></p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load on product edit page.
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		// Enqueue Select2 (if not already enqueued).
		if ( ! wp_script_is( 'select2', 'enqueued' ) ) {
			wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
			wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0', true );
		}

		// Determine if we should load minified assets.
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		// Enqueue plugin styles.
		wp_enqueue_style(
			'cfr2wc-product-r2-selector',
			CFR2WC_PLUGIN_URL . 'assets/css/product-r2-selector' . $suffix . '.css',
			array(),
			CFR2WC_VERSION
		);

		// Enqueue plugin scripts.
		wp_enqueue_script(
			'cfr2wc-product-r2-selector',
			CFR2WC_PLUGIN_URL . 'assets/js/product-r2-selector' . $suffix . '.js',
			array( 'jquery', 'select2' ),
			CFR2WC_VERSION,
			true
		);

		// Localize script.
		$settings = get_option( 'cfr2wc_settings', array() );

		wp_localize_script(
			'cfr2wc-product-r2-selector',
			'cfr2wcProduct',
			array(
				'ajax_url'                  => admin_url( 'admin-ajax.php' ),
				'nonce'                     => wp_create_nonce( 'cfr2wc_product_nonce' ),
				'product_id'                => get_the_ID(),
				'use_generic_download_name' => isset( $settings['use_generic_download_name'] ) && 'yes' === $settings['use_generic_download_name'],
				'strings'                   => array(
					'loading'        => __( 'Loading...', 'cfr2wc' ),
					'error'          => __( 'Error', 'cfr2wc' ),
					'no_results'     => __( 'No files found', 'cfr2wc' ),
					'select_file'    => __( 'Please select a file', 'cfr2wc' ),
					'uploading'      => __( 'Uploading...', 'cfr2wc' ),
					'upload_success' => __( 'File uploaded successfully', 'cfr2wc' ),
					'upload_error'   => __( 'Upload failed', 'cfr2wc' ),
					'choose_r2'      => __( 'Choose', 'cfr2wc' ),
					'upload_r2'      => __( 'Upload', 'cfr2wc' ),
				),
			)
		);

		// Auto-sync if cache is empty.
		if ( $this->file_cache_manager->is_cache_empty() ) {
			$this->file_cache_manager->sync_r2_files();
		}
	}

	/**
	 * Check rate limit for operations.
	 *
	 * @param string $operation Operation name (e.g., 'upload', 'sync').
	 * @param int    $limit Maximum operations allowed.
	 * @param int    $window Time window in seconds.
	 * @return bool True if within limit, false otherwise.
	 */
	private function check_rate_limit( string $operation, int $limit, int $window ): bool {
		$user_id = get_current_user_id();
		$key     = 'cfr2wc_rate_' . $operation . '_' . $user_id;

		$current = get_transient( $key );

		if ( false === $current ) {
			// First operation in this window.
			set_transient( $key, 1, $window );
			return true;
		}

		if ( $current >= $limit ) {
			CFR2WC_Logger::warning(
				'Rate limit exceeded',
				array(
					'operation' => $operation,
					'user_id'   => $user_id,
					'limit'     => $limit,
				)
			);
			return false;
		}

		// Increment counter.
		set_transient( $key, $current + 1, $window );
		return true;
	}

	/**
	 * Validate file upload.
	 *
	 * @param array $file Uploaded file from $_FILES.
	 * @return array Validation result with 'valid' and 'message'.
	 */
	private function validate_upload( array $file ): array {
		// Check for upload errors.
		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== $file['error'] ) {
			return array(
				'valid'   => false,
				'message' => $this->get_upload_error_message( $file['error'] ),
			);
		}

		// Get allowed file types from WordPress.
		$allowed_types = get_allowed_mime_types();

		// Check file type.
		$file_type = wp_check_filetype( $file['name'], $allowed_types );

		if ( ! $file_type['type'] ) {
			CFR2WC_Logger::warning( 'File type not allowed', array( 'filename' => $file['name'] ) );
			return array(
				'valid'   => false,
				'message' => __( 'File type not allowed. Please upload a valid file.', 'cfr2wc' ),
			);
		}

		// Check file size (100MB default limit).
		$max_size = apply_filters( 'cfr2wc_max_upload_size', 100 * 1024 * 1024 );

		if ( $file['size'] > $max_size ) {
			CFR2WC_Logger::warning(
				'File too large',
				array(
					'filename' => $file['name'],
					'size'     => $file['size'],
				)
			);
			$error_message = sprintf(
				/* translators: %s: Maximum file size. */
				__( 'File too large. Maximum size: %s', 'cfr2wc' ),
				size_format( $max_size )
			);
			return array(
				'valid'   => false,
				'message' => $error_message,
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Get human-readable upload error message.
	 *
	 * @param int $error_code PHP upload error code.
	 * @return string Error message.
	 */
	private function get_upload_error_message( int $error_code ): string {
		$errors = array(
			UPLOAD_ERR_INI_SIZE   => __( 'File exceeds upload_max_filesize in php.ini', 'cfr2wc' ),
			UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds MAX_FILE_SIZE in HTML form', 'cfr2wc' ),
			UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded', 'cfr2wc' ),
			UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded', 'cfr2wc' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'Missing temporary upload folder', 'cfr2wc' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk', 'cfr2wc' ),
			UPLOAD_ERR_EXTENSION  => __( 'Upload stopped by PHP extension', 'cfr2wc' ),
		);

		return $errors[ $error_code ] ?? __( 'Unknown upload error', 'cfr2wc' );
	}

	/**
	 * AJAX: Get folder tree.
	 */
	public function ajax_get_folder_tree(): void {
		check_ajax_referer( 'cfr2wc_product_nonce', 'nonce' );

		// phpcs:ignore WordPress.WP.Capabilities.Unknown
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'cfr2wc' ) ) );
		}

		$tree = $this->file_cache_manager->get_folder_tree();

		wp_send_json_success( array( 'tree' => $tree ) );
	}

	/**
	 * AJAX: Search files (Select2 compatible).
	 */
	public function ajax_search_files(): void {
		check_ajax_referer( 'cfr2wc_product_nonce', 'nonce' );

		// phpcs:ignore WordPress.WP.Capabilities.Unknown
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'cfr2wc' ) ) );
		}

		$search      = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$folder_path = isset( $_GET['folder_path'] ) ? sanitize_text_field( wp_unslash( $_GET['folder_path'] ) ) : '';

		$files = $this->file_cache_manager->search_files( $search, $folder_path );

		wp_send_json_success( array( 'files' => $files ) );
	}

	/**
	 * AJAX: Upload file to R2.
	 */
	public function ajax_upload_to_r2(): void {
		check_ajax_referer( 'cfr2wc_product_nonce', 'nonce' );

		// phpcs:ignore WordPress.WP.Capabilities.Unknown
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'cfr2wc' ) ) );
		}

		// Rate limiting: 20 uploads per hour per user.
		if ( ! $this->check_rate_limit( 'upload', 20, 3600 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many uploads. Please wait before uploading more files.', 'cfr2wc' ) ) );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded', 'cfr2wc' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = $_FILES['file'];

		// Validate file upload.
		$validation = $this->validate_upload( $file );
		if ( ! $validation['valid'] ) {
			wp_send_json_error( array( 'message' => $validation['message'] ) );
		}

		$folder_path = isset( $_POST['folder_path'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_path'] ) ) : '';
		// Remove leading/trailing slashes.
		$folder_path = trim( $folder_path, '/' );

		// Build object key.
		$filename   = sanitize_file_name( $file['name'] );
		$object_key = '' !== $folder_path && '0' !== $folder_path ? $folder_path . '/' . $filename : $filename;

		// Upload to R2.
		$result = $this->r2_client->upload_file( $file['tmp_name'], $object_key );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to upload to R2', 'cfr2wc' ) ) );
		}

		// Add to cache.
		$this->file_cache_manager->sync_r2_files( true );

		// Log file assignment to product (user will add it via JS).
		CFR2WC_Logger::info(
			'File uploaded and ready for product assignment',
			array(
				'object_key' => $object_key,
				'filename'   => $filename,
				'folder'     => $folder_path,
			)
		);

		wp_send_json_success(
			array(
				'message'    => __( 'File uploaded successfully', 'cfr2wc' ),
				'object_key' => $object_key,
				'file_name'  => $filename,
			)
		);
	}

	/**
	 * AJAX: Sync R2 files.
	 */
	public function ajax_sync_r2_files(): void {
		check_ajax_referer( 'cfr2wc_product_nonce', 'nonce' );

		// phpcs:ignore WordPress.WP.Capabilities.Unknown
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'cfr2wc' ) ) );
		}

		$result = $this->file_cache_manager->sync_r2_files( true );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}
}
