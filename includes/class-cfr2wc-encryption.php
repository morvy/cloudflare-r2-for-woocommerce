<?php
/**
 * Encryption Class
 *
 * Handles encryption/decryption of sensitive credentials using AES-256-GCM
 *
 * @package CloudflareR2WC
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CloudflareR2WC Encryption Class
 *
 * Provides secure encryption for sensitive credentials like API keys
 */
class CFR2WC_Encryption {
	/**
	 * Version marker for encryption format
	 */
	private const VERSION = 'v1::';

	/**
	 * Encryption algorithm
	 */
	private const ALGORITHM = 'aes-256-gcm';

	/**
	 * Authentication tag length in bytes
	 */
	private const TAG_LENGTH = 16;

	/**
	 * Get encryption key derived from WordPress salt
	 *
	 * Uses HKDF (HMAC-based Key Derivation Function) with:
	 * - Input Key Material: WordPress salt (site-specific)
	 * - Salt: SHA-256 hash of plugin-specific context
	 * - Info: Application identifier for key binding
	 *
	 * @return string 32-byte encryption key
	 */
	private static function get_encryption_key(): string {
		// Plugin-specific salt for additional entropy.
		$salt = hash( 'sha256', 'cfr2wc_encryption_v1', true );

		// Application context to bind key to specific use case.
		$info = 'cloudflare-r2-woocommerce-credentials';

		// Derive key using HKDF with all parameters.
		return hash_hkdf( 'sha256', wp_salt(), 32, $info, $salt );
	}

	/**
	 * Encrypt a value using AES-256-GCM
	 *
	 * @param string $value Value to encrypt.
	 * @return string Encrypted value with version marker
	 * @throws Exception If encryption fails.
	 */
	public static function encrypt( string $value ): string {
		// Return empty string if value is empty.
		if ( in_array( trim( $value ), array( '', '0' ), true ) ) {
			return '';
		}

		// Check if OpenSSL is available.
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			CFR2WC_Logger::error( 'OpenSSL is not available for encryption' );
			throw new Exception( 'OpenSSL is required for credential encryption' );
		}

		try {
			$key       = self::get_encryption_key();
			$iv_length = openssl_cipher_iv_length( self::ALGORITHM );

			// Generate random IV.
			$iv = openssl_random_pseudo_bytes( $iv_length );

			// Encrypt with authentication tag.
			$tag       = '';
			$encrypted = openssl_encrypt(
				$value,
				self::ALGORITHM,
				$key,
				OPENSSL_RAW_DATA,
				$iv,
				$tag,
				'',
				self::TAG_LENGTH
			);

			if ( false === $encrypted ) {
				CFR2WC_Logger::error( 'Failed to encrypt credential' );
				throw new Exception( 'Encryption failed' );
			}

			// Prepend version marker and encode: v1::{base64(iv + tag + encrypted_data)}.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Used for encryption, not obfuscation.
			$result = self::VERSION . base64_encode( $iv . $tag . $encrypted );

			CFR2WC_Logger::debug( 'Credential encrypted successfully' );

			return $result;

		} catch ( Exception $e ) {
			CFR2WC_Logger::error( 'Encryption error: ' . $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * Decrypt a value using AES-256-GCM
	 *
	 * Supports:
	 * - v1:: format (AES-256-GCM with authentication)
	 * - Plain text (for backward compatibility during migration)
	 *
	 * @param string $value Value to decrypt.
	 * @return string Decrypted value or original if not encrypted
	 * @throws Exception If decryption fails.
	 */
	public static function decrypt( string $value ): string {
		// Return empty string if value is empty.
		if ( in_array( trim( $value ), array( '', '0' ), true ) ) {
			return '';
		}

		// Check if value is encrypted (has version marker).
		if ( ! str_starts_with( $value, self::VERSION ) ) {
			// Not encrypted, return as-is (backward compatibility).
			CFR2WC_Logger::debug( 'Credential is not encrypted (plain text)' );
			return $value;
		}

		// Check if OpenSSL is available.
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			CFR2WC_Logger::error( 'OpenSSL is not available for decryption' );
			throw new Exception( 'OpenSSL is required for credential decryption' );
		}

		try {
			// Remove version marker and decode.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Used for decryption, not obfuscation.
			$data = base64_decode( substr( $value, strlen( self::VERSION ) ), true );

			if ( false === $data ) {
				CFR2WC_Logger::warning( 'Failed to decode encrypted credential' );
				return $value; // Return as-is if decode fails.
			}

			$key       = self::get_encryption_key();
			$iv_length = openssl_cipher_iv_length( self::ALGORITHM );

			// Verify minimum data length (IV + tag + data).
			if ( strlen( $data ) < $iv_length + self::TAG_LENGTH ) {
				CFR2WC_Logger::warning( 'Encrypted credential data is malformed' );
				return $value; // Return as-is if malformed.
			}

			// Extract IV, tag, and encrypted data.
			$iv             = substr( $data, 0, $iv_length );
			$tag            = substr( $data, $iv_length, self::TAG_LENGTH );
			$encrypted_data = substr( $data, $iv_length + self::TAG_LENGTH );

			// Decrypt with authentication verification.
			$decrypted = openssl_decrypt(
				$encrypted_data,
				self::ALGORITHM,
				$key,
				OPENSSL_RAW_DATA,
				$iv,
				$tag
			);

			if ( false === $decrypted ) {
				CFR2WC_Logger::error( 'Failed to decrypt credential (authentication failed)' );
				return $value; // Return encrypted value if decryption fails.
			}

			CFR2WC_Logger::debug( 'Credential decrypted successfully' );

			return $decrypted;

		} catch ( Exception $e ) {
			CFR2WC_Logger::error( 'Decryption error: ' . $e->getMessage() );
			return $value; // Return original value on error.
		}
	}

	/**
	 * Check if a value is encrypted
	 *
	 * @param string $value Value to check.
	 * @return bool True if encrypted
	 */
	public static function is_encrypted( string $value ): bool {
		return str_starts_with( $value, self::VERSION );
	}

	/**
	 * Sanitize and encrypt credential on save
	 *
	 * @param string $value Raw credential value.
	 * @return string Encrypted credential
	 */
	public static function sanitize_credential( string $value ): string {
		$value = sanitize_text_field( $value );

		// Don't re-encrypt if already encrypted.
		if ( self::is_encrypted( $value ) ) {
			return $value;
		}

		// Encrypt if not empty.
		if ( ! empty( $value ) ) {
			try {
				return self::encrypt( $value );
			} catch ( Exception ) {
				CFR2WC_Logger::error( 'Failed to encrypt credential during save' );
				return $value; // Return plain if encryption fails.
			}
		}

		return '';
	}
}
