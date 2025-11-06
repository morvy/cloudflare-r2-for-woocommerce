<?php
/**
 * Logger Class
 *
 * PSR-3 compatible logger using WooCommerce logger
 *
 * @package CloudflareR2WC
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CloudflareR2WC Logger Class
 *
 * Provides PSR-3 compatible logging using WooCommerce logger
 */
class CFR2WC_Logger {
	/**
	 * WooCommerce logger instance
	 *
	 * @var WC_Logger_Interface|null
	 */
	private static $wc_logger;

	/**
	 * Log source name
	 */
	private const LOG_SOURCE = 'cfr2wc';

	/**
	 * PSR-3 log level priorities
	 */
	private const LEVEL_PRIORITIES = array(
		'debug'     => 0,
		'info'      => 1,
		'notice'    => 2,
		'warning'   => 3,
		'error'     => 4,
		'critical'  => 5,
		'alert'     => 6,
		'emergency' => 7,
	);

	/**
	 * Get WooCommerce logger instance
	 *
	 * @return WC_Logger_Interface|null
	 */
	private static function get_logger() {
		if ( null === self::$wc_logger && function_exists( 'wc_get_logger' ) ) {
			self::$wc_logger = wc_get_logger();
		}
		return self::$wc_logger;
	}

	/**
	 * Check if logging is enabled and if message should be logged
	 *
	 * @param string $level Log level.
	 */
	private static function should_log( string $level ): bool {
		$settings   = get_option( 'cfr2wc_settings', array() );
		$debug_mode = $settings['debug_mode'] ?? 'no';

		// If debug mode is off, don't log.
		if ( 'yes' !== $debug_mode ) {
			return false;
		}

		$debug_level = $settings['debug_level'] ?? 'error';

		// Get priorities.
		$current_priority = self::LEVEL_PRIORITIES[ $level ] ?? 0;
		$min_priority     = self::LEVEL_PRIORITIES[ $debug_level ] ?? 4;

		// Only log if current message priority >= minimum priority.
		return $current_priority >= $min_priority;
	}

	/**
	 * Log a message
	 *
	 * @param string $level Log level.
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	private static function log( string $level, string $message, array $context = array() ): void {
		if ( ! self::should_log( $level ) ) {
			return;
		}

		$logger = self::get_logger();
		if ( ! $logger ) {
			return;
		}

		// Sanitize context to remove sensitive data.
		if ( array() !== $context ) {
			$context  = self::sanitize_context( $context );
			$message .= ' | Context: ' . wp_json_encode( $context );
		}

		// Log using WooCommerce logger.
		$logger->log( $level, $message, array( 'source' => self::LOG_SOURCE ) );
	}

	/**
	 * Sanitize context array to redact sensitive information
	 *
	 * @param array $context Context data.
	 * @return array Sanitized context
	 */
	private static function sanitize_context( array $context ): array {
		$sensitive_keys = array( 'password', 'secret', 'key', 'token', 'access_key', 'api_key', 'credential' );

		foreach ( $context as $key => $value ) {
			$lower_key = strtolower( $key );

			// Check if key contains sensitive terms.
			foreach ( $sensitive_keys as $sensitive ) {
				if ( str_contains( $lower_key, $sensitive ) ) {
					$context[ $key ] = '[REDACTED]';
					break;
				}
			}

			// Recursively sanitize nested arrays.
			if ( is_array( $value ) && '[REDACTED]' !== $context[ $key ] ) {
				$context[ $key ] = self::sanitize_context( $value );
			}
		}

		return $context;
	}

	/**
	 * System is unusable.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public static function emergency( string $message, array $context = array() ): void {
		self::log( 'emergency', $message, $context );
	}

	/**
	 * Action must be taken immediately.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public static function alert( string $message, array $context = array() ): void {
		self::log( 'alert', $message, $context );
	}

	/**
	 * Critical conditions.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public static function critical( string $message, array $context = array() ): void {
		self::log( 'critical', $message, $context );
	}

	/**
	 * Runtime errors that do not require immediate action.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public static function notice( string $message, array $context = array() ): void {
		self::log( 'notice', $message, $context );
	}

	/**
	 * Interesting events.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public static function debug( string $message, array $context = array() ): void {
		self::log( 'debug', $message, $context );
	}
}
