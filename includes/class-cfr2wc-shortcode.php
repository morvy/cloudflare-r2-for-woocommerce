<?php
/**
 * Shortcode Handler
 *
 * @package CloudflareR2WC
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CloudflareR2WC Shortcode Class
 *
 * Handles [cloudflare_r2] and [amazon_s3] shortcodes
 */
class CFR2WC_Shortcode {
	/**
	 * Primary shortcode name
	 */
	const SHORTCODE_NAME = 'cloudflare_r2';

	/**
	 * Alias shortcode name (backward compatibility)
	 */
	const SHORTCODE_ALIAS = 'amazon_s3';

	/**
	 * URL cache (per request)
	 *
	 * @var array
	 */
	private static array $url_cache = array();

	/**
	 * R2 Client
	 *
	 * @var CFR2WC_Client
	 */
	private $r2_client;

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor
	 *
	 * @param CFR2WC_Client $r2_client R2 client instance.
	 * @param array         $settings Plugin settings.
	 */
	public function __construct( $r2_client, $settings ) {
		$this->r2_client = $r2_client;
		$this->settings  = $settings;
		// Register both shortcodes.
		add_shortcode( self::SHORTCODE_NAME, array( $this, 'handle_shortcode' ) );
		add_shortcode( self::SHORTCODE_ALIAS, array( $this, 'handle_shortcode' ) );
	}

	/**
	 * Handle shortcode
	 *
	 * Attributes:
	 * - bucket: Bucket name (optional, defaults to configured bucket)
	 * - region: Region (optional, defaults to 'auto')
	 * - object: Object key/path (required)
	 * - filename: Display filename (optional)
	 * - expires: Custom expiration time in seconds (optional)
	 * - public: Whether file is public (optional)
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @param string $tag Shortcode tag name.
	 * @return string Generated URL or error message
	 */
	public function handle_shortcode( $atts, $content = null, $tag = '' ) {
		// Parse attributes.
		$atts = shortcode_atts(
			array(
				'bucket'   => $this->settings['bucket_name'] ?? '',
				'region'   => 'auto',
				'object'   => '',
				'filename' => '',
				'expires'  => null,
				'public'   => 'false',
				'return'   => 'url',
			),
			$atts,
			$tag
		);

		// Validate required attributes.
		if ( empty( $atts['object'] ) ) {
			return $this->error_message( __( 'Missing required "object" attribute', 'cfr2wc' ) );
		}

		// Decode HTML entities.
		$atts['object'] = html_entity_decode( (string) $atts['object'], ENT_QUOTES, 'UTF-8' );

		// Return URL or filename based on 'return' parameter.
		if ( 'name' === $atts['return'] ) {
			// Check if generic download name is enabled.
			$use_generic_name = isset( $this->settings['use_generic_download_name'] ) && 'yes' === $this->settings['use_generic_download_name'];

			if ( $use_generic_name ) {
				return __( 'Download', 'cfr2wc' );
			}

			return empty( $atts['filename'] ) ? basename( $atts['object'] ) : $atts['filename'];
		}

		// Generate and return the download URL.
		return $this->generate_download_url( $atts );
	}

	/**
	 * Generate download URL
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string URL
	 */
	private function generate_download_url( array $atts ) {
		// Check cache.
		$cache_key = md5( maybe_serialize( $atts ) );
		if ( isset( self::$url_cache[ $cache_key ] ) ) {
			return self::$url_cache[ $cache_key ];
		}

		// Check if this is a public file.
		$is_public = isset( $atts['public'] ) && 'true' === $atts['public'];

		// For public files with custom domain.
		if ( $is_public && ! empty( $this->settings['custom_domain'] ) ) {
			$custom_domain = trailingslashit( $this->settings['custom_domain'] );
			$object_key    = ltrim( (string) $atts['object'], '/' );
			$url           = 'https://' . $custom_domain . $object_key;
		} else {
			// Generate pre-signed URL.
			// Get expiration from settings (convert hours to seconds).
			$expiration_hours   = isset( $this->settings['url_expiration_hours'] ) ? (int) $this->settings['url_expiration_hours'] : 24;
			$expiration_seconds = $expiration_hours * 3600;

			// Allow override from shortcode.
			if ( isset( $atts['expires'] ) ) {
				$expiration_seconds = (int) $atts['expires'];
			}

			$url = $this->r2_client->get_presigned_url( $atts['object'], $expiration_seconds );
		}

		if ( ! $url ) {
			return $this->error_message( __( 'Failed to generate download URL', 'cfr2wc' ) );
		}

		// Cache the URL.
		self::$url_cache[ $cache_key ] = $url;

		return $url;
	}

	/**
	 * Generate error message
	 *
	 * @param string $message Error message.
	 * @return string Formatted error
	 */
	private function error_message( $message ): string {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return '<span class="cfr2wc-error" style="color: red;">[R2 Error: ' . esc_html( $message ) . ']</span>';
		}
		return '';
	}

	/**
	 * Parse shortcode from string
	 *
	 * @param string $shortcode Shortcode string.
	 * @return array|false Parsed attributes or false
	 */
	public static function parse_shortcode( $shortcode ): false|array {
		// Match both shortcode formats.
		$pattern = '/\[(?:' . self::SHORTCODE_NAME . '|' . self::SHORTCODE_ALIAS . ')\s+([^\]]+)\]/';

		if ( ! preg_match( $pattern, $shortcode, $matches ) ) {
			return false;
		}

		// Parse attributes.
		$atts_string = $matches[1];
		$atts        = array();

		// Match attribute="value" patterns.
		if ( preg_match_all( '/(\w+)="([^"]*)"/', $atts_string, $attr_matches, PREG_SET_ORDER ) ) {
			foreach ( $attr_matches as $attr ) {
				$atts[ $attr[1] ] = $attr[2];
			}
		}

		return $atts;
	}

	/**
	 * Check if string contains R2 shortcode
	 *
	 * @param string $content Content to check.
	 */
	public static function has_shortcode( $content ): bool {
		return has_shortcode( $content, self::SHORTCODE_NAME ) ||
			has_shortcode( $content, self::SHORTCODE_ALIAS );
	}
}
