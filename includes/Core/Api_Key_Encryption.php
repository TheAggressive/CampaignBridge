<?php
/**
 * Ultra-secure API Key Encryption for CampaignBridge.
 *
 * Provides military-grade encryption for API keys using AES-256-GCM with
 * proper key management, rotation, and WordPress security best practices.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ultra-secure API Key Encryption Class
 *
 * Handles encryption/decryption of sensitive API keys with:
 * - AES-256-GCM encryption for authenticated encryption
 * - Secure key derivation and management
 * - Key rotation capabilities
 * - Comprehensive error handling
 * - WordPress security best practices
 */
class Api_Key_Encryption {

	/**
	 * Encryption algorithm constant.
	 */
	private const ALGORITHM = 'aes-256-gcm';


	/**
	 * Key length for AES-256.
	 */
	private const KEY_LENGTH = 32;

	/**
	 * IV length for GCM mode.
	 */
	private const IV_LENGTH = 12;

	/**
	 * Authentication tag length for GCM.
	 */
	private const TAG_LENGTH = 16;

	/**
	 * Option name for storing the master encryption key.
	 */
	private const MASTER_KEY_OPTION = 'campaignbridge_master_key';

	/**
	 * Option name for storing key rotation metadata.
	 */
	private const KEY_META_OPTION = 'campaignbridge_key_metadata';

	/**
	 * Minimum PHP version required for GCM support and security features.
	 */
	private const MIN_PHP_VERSION = '8.2.0';

	/**
	 * Encrypt an API key with authenticated encryption.
	 *
	 * @param string $plaintext The API key to encrypt.
	 * @return string The encrypted API key with metadata.
	 * @throws \RuntimeException If encryption fails or PHP version is insufficient.
	 */
	public static function encrypt( string $plaintext ): string {
		self::validate_php_version();

		if ( empty( $plaintext ) ) {
			return '';
		}

		$key        = self::get_master_key();
		$iv         = random_bytes( self::IV_LENGTH );
		$tag        = '';
		$ciphertext = openssl_encrypt(
			$plaintext,
			self::ALGORITHM,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ( false === $ciphertext ) {
			throw new \RuntimeException( 'API key encryption failed' );
		}

		// Combine IV, tag, and ciphertext for storage.
		$encrypted = $iv . $tag . $ciphertext;

		// Return base64 encoded for safe storage.
		return base64_encode( $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/**
	 * Decrypt an API key for operational use (API calls, processing).
	 *
	 * This method is unrestricted and can be called in any context where
	 * decrypted API keys are needed for functionality.
	 *
	 * @param string $encrypted The encrypted API key from storage.
	 * @return string The decrypted API key.
	 * @throws \RuntimeException If decryption fails or data is corrupted.
	 */
	public static function decrypt( string $encrypted ): string {
		self::validate_php_version();

		if ( empty( $encrypted ) ) {
			return '';
		}

		// Check if the data appears to be encrypted (base64 encoded).
		if ( ! self::is_encrypted_value( $encrypted ) ) {
			// Data is not encrypted, return as-is (plain text).
			return $encrypted;
		}

		try {
			$decoded = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			if ( false === $decoded ) {
				throw new \RuntimeException( 'Invalid encrypted data format' );
			}

			if ( strlen( $decoded ) < self::IV_LENGTH + self::TAG_LENGTH ) {
				throw new \RuntimeException( 'Encrypted data too short' );
			}

			$key        = self::get_master_key();
			$iv         = substr( $decoded, 0, self::IV_LENGTH );
			$tag        = substr( $decoded, self::IV_LENGTH, self::TAG_LENGTH );
			$ciphertext = substr( $decoded, self::IV_LENGTH + self::TAG_LENGTH );

			$plaintext = openssl_decrypt(
				$ciphertext,
				self::ALGORITHM,
				$key,
				OPENSSL_RAW_DATA,
				$iv,
				$tag
			);

			if ( false === $plaintext ) {
				throw new \RuntimeException( 'API key decryption failed' );
			}

			return $plaintext;

		} catch ( \Throwable $e ) {
			// Log the error for debugging but don't expose details.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security event logging.
					sprintf(
						'CampaignBridge API key decryption failed: %s',
						$e->getMessage()
					)
				);
			}

			throw new \RuntimeException( 'API key decryption failed' );
		}
	}

	/**
	 * Decrypt an API key for admin display purposes.
	 *
	 * This method includes security checks to ensure only administrators
	 * can view decrypted API keys in forms and interfaces.
	 *
	 * @param string $encrypted The encrypted API key from storage.
	 * @return string The decrypted API key.
	 * @throws \RuntimeException If decryption fails, data is corrupted, or user lacks permission.
	 */
	public static function decrypt_for_display( string $encrypted ): string {
		// Only allow admin users to view decrypted API keys.
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \RuntimeException(
				'Unauthorized attempt to view decrypted sensitive data. Admin access required.'
			);
		}

		return self::decrypt( $encrypted );
	}

	/**
	 * Rotate the master encryption key for enhanced security.
	 *
	 * This should be called periodically or when security incidents occur.
	 * All existing encrypted API keys will need to be re-encrypted with the new key.
	 *
	 * @param bool $force Force rotation even if not scheduled.
	 * @return bool True if rotation was performed.
	 */
	public static function rotate_master_key( bool $force = false ): bool {
		$metadata = self::get_key_metadata();

		// Check if rotation is needed (30 days max age).
		$should_rotate = $force ||
			! isset( $metadata['created'] ) ||
			( time() - $metadata['created'] ) > ( 30 * DAY_IN_SECONDS );

		if ( ! $should_rotate ) {
			return false;
		}

		// Generate new master key.
		$new_key = self::generate_master_key();

		// Store the new key.
		$success = \CampaignBridge\Core\Storage::update_option( self::MASTER_KEY_OPTION, $new_key );

		if ( $success ) {
			// Update metadata.
			$metadata['created'] = time();
			$metadata['version'] = ( $metadata['version'] ?? 0 ) + 1;
			\CampaignBridge\Core\Storage::update_option( self::KEY_META_OPTION, $metadata );

			// Log the rotation (without exposing the key).
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security event logging.
					sprintf(
						'CampaignBridge master encryption key rotated. New version: %d',
						$metadata['version']
					)
				);
			}
		}

		return $success;
	}

	/**
	 * Get the current master encryption key.
	 *
	 * @return string The master key for encryption/decryption.
	 */
	private static function get_master_key(): string {
		$stored_key = \CampaignBridge\Core\Storage::get_option( self::MASTER_KEY_OPTION );

		if ( ! $stored_key ) {
			// Generate and store new master key.
			$stored_key = self::generate_master_key();
			\CampaignBridge\Core\Storage::add_option( self::MASTER_KEY_OPTION, $stored_key );

			// Initialize metadata.
			$metadata = array(
				'created' => time(),
				'version' => 1,
			);
			\CampaignBridge\Core\Storage::add_option( self::KEY_META_OPTION, $metadata );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'CampaignBridge master encryption key generated' );
			}
		}

		return $stored_key;
	}

	/**
	 * Generate a new master key using cryptographically secure methods.
	 *
	 * @return string Base64-encoded master key.
	 */
	private static function generate_master_key(): string {
		$random_bytes = random_bytes( self::KEY_LENGTH );
		return base64_encode( $random_bytes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/**
	 * Get key rotation metadata.
	 *
	 * @return array<string, mixed> Key metadata including creation time and version.
	 */
	private static function get_key_metadata(): array {
		return \CampaignBridge\Core\Storage::get_option( self::KEY_META_OPTION, array() );
	}

	/**
	 * Validate that PHP version supports required cryptography features.
	 *
	 * @throws \RuntimeException If PHP version is insufficient.
	 */
	private static function validate_php_version(): void {
		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			throw new \RuntimeException(
				sprintf( 'PHP %s or higher required for secure encryption. Please update your PHP version for enhanced security.', self::MIN_PHP_VERSION ) // phpcs:ignore WordPress.Security.EscapeOutput
			);
		}
	}

	/**
	 * Check if the current encryption setup is secure.
	 *
	 * @return array<string, mixed> Array with 'secure' boolean and 'issues' array.
	 */
	public static function security_check(): array {
		$issues = array();
		$secure = true;

		// Check PHP version.
		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			$issues[] = sprintf( 'PHP version %s is below minimum required %s for secure encryption', PHP_VERSION, self::MIN_PHP_VERSION );
			$secure   = false;
		}

		// Check if OpenSSL is available.
		if ( ! extension_loaded( 'openssl' ) ) {
			$issues[] = 'OpenSSL extension not available';
			$secure   = false;
		}

		// Check if AES-256-GCM is supported.
		if ( ! in_array( self::ALGORITHM, openssl_get_cipher_methods(), true ) ) {
			$issues[] = 'AES-256-GCM cipher not supported';
			$secure   = false;
		}

		// Check key age.
		$metadata = self::get_key_metadata();
		if ( isset( $metadata['created'] ) ) {
			$key_age_days = ( time() - $metadata['created'] ) / DAY_IN_SECONDS;
			if ( $key_age_days > 90 ) {
				$issues[] = sprintf( 'Master key is %d days old, consider rotation', (int) $key_age_days );
			}
		}

		return array(
			'secure'            => $secure,
			'issues'            => $issues,
			'php_version'       => PHP_VERSION,
			'openssl_available' => extension_loaded( 'openssl' ),
			'cipher_supported'  => in_array( self::ALGORITHM, openssl_get_cipher_methods(), true ),
			'key_version'       => $metadata['version'] ?? 0,
			'key_age_days'      => isset( $metadata['created'] ) ? (int) ( ( time() - $metadata['created'] ) / DAY_IN_SECONDS ) : 0,
		);
	}


	/**
	 * Check if a value appears to be encrypted data.
	 *
	 * @param string $value The value to check.
	 * @return bool True if value appears to be encrypted.
	 */
	public static function is_encrypted_value( string $value ): bool {
		// Basic security: only accept reasonable length values.
		if ( strlen( $value ) < 20 || strlen( $value ) > 1000 ) {
			return false;
		}

		// Check if it's valid base64 (encrypted data is base64 encoded).
		if ( ! preg_match( '/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $value ) ) {
			return false;
		}

		// Try to decode and validate the structure.
		$decoded = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $decoded ) {
			return false;
		}

		// Must be at least IV (12) + tag (16) + minimal ciphertext (8) = 36 bytes.
		if ( strlen( $decoded ) < 36 ) {
			return false;
		}

		// Additional validation: check that decoded data looks like binary (not just valid base64)
		// Valid encrypted data should have high entropy (not just printable characters).
		$printable_chars = 0;
		$total_chars     = strlen( $decoded );
		$check_length    = min( $total_chars, 100 );
		for ( $i = 0; $i < $check_length; $i++ ) {
			if ( ctype_print( $decoded[ $i ] ) ) {
				++$printable_chars;
			}
		}

		// If more than 80% of the data is printable, it's likely not encrypted binary data.
		return ( $printable_chars / min( $total_chars, 100 ) ) < 0.8;
	}

	/**
	 * Validate API key format using provider-specific pattern.
	 *
	 * @param string $value The API key to validate.
	 * @param string $pattern Optional regex pattern. If not provided, uses generic validation.
	 * @return bool True if valid API key format.
	 */
	public static function is_valid_api_key_format( string $value, string $pattern = '' ): bool {
		// If no pattern provided, use generic validation.
		if ( empty( $pattern ) ) {
			// Generic: 20+ character alphanumeric with optional separators.
			$pattern = '/^[a-zA-Z0-9_-]{20,}$/';
		}

		return (bool) preg_match( $pattern, $value );
	}
}
