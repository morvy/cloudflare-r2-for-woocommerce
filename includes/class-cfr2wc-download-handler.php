<?php
/**
 * Download Handler
 *
 * @package CloudflareR2WC
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CloudflareR2WC Download Handler Class
 *
 * Handles WooCommerce product downloads from R2
 */
class CFR2WC_Download_Handler {
	/**
	 * Constructor
	 *
	 * @param CFR2WC_Client $r2_client R2 client instance.
	 */
	public function __construct( private readonly CFR2WC_Client $r2_client ) {
		$this->init_hooks();
	}

	/**
	 * Initialize WooCommerce hooks.
	 */
	private function init_hooks(): void {
		// WooCommerce download integration.
		add_filter( 'woocommerce_download_product_filepath', array( $this, 'filter_download_filepath' ), 10, 5 );
		add_filter( 'woocommerce_file_download_method', array( $this, 'filter_download_method' ), 10, 2 );
	}

	/**
	 * Filter WooCommerce download filepath
	 *
	 * Converts R2 shortcode to pre-signed URL for download
	 * WooCommerce automatically counts downloads when this filter is used
	 *
	 * NOTE: This filter is called when customer clicks the obfuscated download link
	 * (?download_file=123&key=abc), not when displaying the link.
	 * This means the R2 URL is only generated at download time, keeping it hidden.
	 *
	 * @param string      $file_path File path.
	 * @param string      $email Customer email.
	 * @param object|null $order Order object.
	 * @param object|null $product Product object.
	 * @param object      $download Download object.
	 * @return string Pre-signed R2 URL or original path.
	 */
	public function filter_download_filepath( $file_path, $email, $order, $product, $download ) {
		// Check if file path is an R2 shortcode.
		if ( ! CFR2WC_Shortcode::has_shortcode( $file_path ) ) {
			return $file_path;
		}

		// Parse shortcode to get object key.
		$atts = CFR2WC_Shortcode::parse_shortcode( $file_path );

		if ( ! $atts || empty( $atts['object'] ) ) {
			return $file_path;
		}

		// Check permissions if enabled.
		if ( $this->should_check_permissions() && ! $this->check_download_permission( $email, $order ) ) {
			// Log for debugging.
			CFR2WC_Logger::warning( 'Download permission denied', array( 'email' => $email ) );
			// Deny download - WooCommerce will show "Permission denied" error.
			wp_die( esc_html__( 'You do not have permission to download this file.', 'cfr2wc' ), esc_html__( 'Download Error', 'cfr2wc' ), array( 'response' => 403 ) );
		}

		// Check if this is a public file.
		$is_public = isset( $atts['public'] ) && 'true' === $atts['public'];

		// Generate pre-signed URL (only generated when customer clicks download).
		$url = $this->generate_presigned_url( $atts, $is_public );

		if ( ! $url ) {
			CFR2WC_Logger::error( 'Failed to generate presigned URL', array( 'object' => $atts['object'] ) );
			wp_die( esc_html__( 'Failed to generate download URL. Please contact support.', 'cfr2wc' ), esc_html__( 'Download Error', 'cfr2wc' ), array( 'response' => 500 ) );
		}

		// Log successful download initiation.
		CFR2WC_Logger::info(
			'Download initiated',
			array(
				'object'     => $atts['object'],
				'email'      => $email,
				'product_id' => $product ? $product->get_id() : 0,
				'order_id'   => $order ? $order->get_id() : 0,
			)
		);

		return $url;
	}

	/**
	 * Check if permission checking is enabled.
	 */
	private function should_check_permissions(): bool {
		$settings = get_option( 'cfr2wc_settings', array() );
		return isset( $settings['check_permissions'] ) && 'yes' === $settings['check_permissions'];
	}

	/**
	 * Check download permissions.
	 *
	 * @param string      $email Customer email.
	 * @param object|null $order Order object.
	 * @return bool True if allowed, false if denied.
	 */
	private function check_download_permission( $email, $order ): bool {
		// If no order provided, deny.
		if ( ! $order ) {
			return false;
		}

		// Check order status - must be completed or processing.
		$allowed_statuses = apply_filters( 'cfr2wc_allowed_download_statuses', array( 'completed', 'processing' ) );
		if ( ! in_array( $order->get_status(), $allowed_statuses, true ) ) {
			return false;
		}

		// Check if customer email matches.
		if ( $email && $order->get_billing_email() !== $email ) {
			return false;
		}

		// All checks passed.
		return true;
	}

	/**
	 * Filter download method for R2 files
	 *
	 * Always use redirect for R2 files (no PHP streaming)
	 * This ensures WooCommerce uses obfuscated URLs (?download_file=ID&key=hash)
	 *
	 * @param string     $method Download method.
	 * @param int|object $product Product object or ID.
	 * @return string Download method.
	 */
	public function filter_download_method( $method, $product ): string {
		// Get product object if ID passed.
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return $method;
		}

		// Check if product has R2 files.
		$downloads = $product->get_downloads();

		foreach ( $downloads as $download ) {
			if ( CFR2WC_Shortcode::has_shortcode( $download->get_file() ) ) {
				// Force redirect method for R2 files.
				// This makes WooCommerce use obfuscated download URLs.
				return 'redirect';
			}
		}

		return $method;
	}

	/**
	 * Generate pre-signed R2 URL.
	 *
	 * @param array $atts Shortcode attributes.
	 * @param bool  $is_public Whether this is a public file.
	 * @return string|false Pre-signed URL or false on failure.
	 */
	private function generate_presigned_url( array $atts, bool $is_public = false ): string|false {
		if ( ! $this->r2_client->get_client() instanceof \Aws\S3\S3Client ) {
			return false;
		}

		// Get settings.
		$settings = get_option( 'cfr2wc_settings', array() );

		// For public files with custom domain.
		if ( $is_public && ! empty( $settings['custom_domain'] ) ) {
			$custom_domain = trailingslashit( $settings['custom_domain'] );
			$object_key    = ltrim( (string) $atts['object'], '/' );
			return 'https://' . $custom_domain . $object_key;
		}

		// Get expiration time from settings (convert hours to seconds).
		$expiration_hours   = isset( $settings['url_expiration_hours'] ) ? (int) $settings['url_expiration_hours'] : 24;
		$expiration_seconds = $expiration_hours * 3600;

		// Allow override from shortcode.
		if ( isset( $atts['expires'] ) ) {
			$expiration_seconds = (int) $atts['expires'];
		}

		// Generate pre-signed URL.
		return $this->r2_client->get_presigned_url( $atts['object'], $expiration_seconds );
	}
}
