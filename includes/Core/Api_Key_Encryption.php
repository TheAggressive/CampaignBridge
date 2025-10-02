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

		// Combine IV, tag, and ciphertext for storage
		$encrypted = $iv . $tag . $ciphertext;

		// Return base64 encoded for safe storage
		return base64_encode( $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_base64_encode -- Used for encrypted data storage.
	}

	/**
	 * Decrypt an API key.
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

		try {
			$decoded = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_base64_decode -- Used for encrypted data decryption.
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
			// Log the error for debugging but don't expose details
			error_log(
				sprintf(
					'CampaignBridge API key decryption failed: %s',
					$e->getMessage()
				)
			);

			throw new \RuntimeException( 'API key decryption failed' );
		}
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

		// Check if rotation is needed (30 days max age)
		$should_rotate = $force ||
			! isset( $metadata['created'] ) ||
			( time() - $metadata['created'] ) > ( 30 * DAY_IN_SECONDS );

		if ( ! $should_rotate ) {
			return false;
		}

		// Generate new master key
		$new_key = self::generate_master_key();

		// Store the new key
		$success = update_option( self::MASTER_KEY_OPTION, $new_key, false );

		if ( $success ) {
			// Update metadata
			$metadata['created'] = time();
			$metadata['version'] = ( $metadata['version'] ?? 0 ) + 1;
			update_option( self::KEY_META_OPTION, $metadata, false );

			// Log the rotation (without exposing the key)
			error_log(
				sprintf(
					'CampaignBridge master encryption key rotated. New version: %d',
					$metadata['version']
				)
			);
		}

		return $success;
	}

	/**
	 * Get the current master encryption key.
	 *
	 * @return string The master key for encryption/decryption.
	 */
	private static function get_master_key(): string {
		$stored_key = get_option( self::MASTER_KEY_OPTION );

		if ( ! $stored_key ) {
			// Generate and store new master key
			$stored_key = self::generate_master_key();
			add_option( self::MASTER_KEY_OPTION, $stored_key, '', 'no' );

			// Initialize metadata
			$metadata = array(
				'created' => time(),
				'version' => 1,
			);
			add_option( self::KEY_META_OPTION, $metadata, '', 'no' );

			error_log( 'CampaignBridge master encryption key generated' );
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
		return base64_encode( $random_bytes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_base64_encode -- Used for secure key encoding.
	}

	/**
	 * Get key rotation metadata.
	 *
	 * @return array Key metadata including creation time and version.
	 */
	private static function get_key_metadata(): array {
		return get_option( self::KEY_META_OPTION, array() );
	}

	/**
	 * Validate that PHP version supports required cryptography features.
	 *
	 * @throws \RuntimeException If PHP version is insufficient.
	 */
	private static function validate_php_version(): void {
		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			throw new \RuntimeException(
				sprintf( 'PHP %s or higher required for secure encryption. Please update your PHP version for enhanced security.', self::MIN_PHP_VERSION )
			);
		}
	}

	/**
	 * Check if the current encryption setup is secure.
	 *
	 * @return array Array with 'secure' boolean and 'issues' array.
	 */
	public static function security_check(): array {
		$issues = array();
		$secure = true;

		// Check PHP version
		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			$issues[] = sprintf( 'PHP version %s is below minimum required %s for secure encryption', PHP_VERSION, self::MIN_PHP_VERSION );
			$secure   = false;
		}

		// Check if OpenSSL is available
		if ( ! extension_loaded( 'openssl' ) ) {
			$issues[] = 'OpenSSL extension not available';
			$secure   = false;
		}

		// Check if AES-256-GCM is supported
		if ( ! in_array( self::ALGORITHM, openssl_get_cipher_methods(), true ) ) {
			$issues[] = 'AES-256-GCM cipher not supported';
			$secure   = false;
		}

		// Check key age
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
	 * Migrate existing plaintext API keys to encrypted storage.
	 *
	 * This method scans the plugin settings for plaintext API keys and encrypts them.
	 * It should be called during plugin updates or when encryption is first implemented.
	 *
	 * @param string $option_name The WordPress option name for plugin settings.
	 * @param string $provider_pattern Optional regex pattern for API key validation.
	 * @return array Migration results with success count and any errors.
	 */
	public static function migrate_plaintext_keys( string $option_name = 'campaignbridge_settings', string $provider_pattern = '' ): array {
		$result = array(
			'success'         => 0,
			'errors'          => array(),
			'migrated_fields' => array(),
		);

		$settings = get_option( $option_name, array() );
		if ( empty( $settings ) ) {
			return $result;
		}

		$updated_settings = $settings;
		$sensitive_fields = array( 'api_key', 'secret', 'password', 'token' );

		foreach ( $sensitive_fields as $field ) {
			if ( isset( $settings[ $field ] ) && ! empty( $settings[ $field ] ) ) {
				$value = $settings[ $field ];

				// Check if this appears to be plaintext (not already encrypted)
				if ( self::is_plaintext_value( $value ) ) {
					try {
						// Validate that this looks like a valid API key before encrypting
						if ( 'api_key' === $field && ! self::is_valid_api_key_format( $value, $provider_pattern ) ) {
							$result['errors'][] = sprintf( 'Invalid API key format for field "%s"', $field );
							continue;
						}

						$encrypted_value = self::encrypt( $value );
						if ( ! empty( $encrypted_value ) ) {
							$updated_settings[ $field ]  = $encrypted_value;
							$result['migrated_fields'][] = $field;
							++$result['success'];
						}
					} catch ( \Throwable $e ) {
						$result['errors'][] = sprintf(
							'Failed to encrypt field "%s": %s',
							$field,
							$e->getMessage()
						);
					}
				}
			}
		}

		// Update the settings if any fields were migrated
		if ( ! empty( $result['migrated_fields'] ) ) {
			// Set a flag to indicate we're in migration mode
			$GLOBALS['campaignbridge_migration_mode'] = true;

			$update_success = update_option( $option_name, $updated_settings );

			// Clear the migration flag
			unset( $GLOBALS['campaignbridge_migration_mode'] );

			if ( ! $update_success ) {
				$result['errors'][] = 'Failed to save updated settings to database';
			} else {
				error_log(
					sprintf(
						'CampaignBridge: Successfully migrated %d sensitive fields to encrypted storage',
						$result['success']
					)
				);
			}
		}

		return $result;
	}

	/**
	 * Check if a value appears to be plaintext (not encrypted).
	 *
	 * @param string $value The value to check.
	 * @return bool True if value appears to be plaintext.
	 */
	private static function is_plaintext_value( string $value ): bool {
		// If it's base64 encoded and looks like encrypted data, it's probably already encrypted
		if ( self::is_encrypted_value( $value ) ) {
			return false;
		}

		// If it contains only printable characters and is reasonably long, it might be plaintext
		return strlen( $value ) > 8 && ctype_print( $value );
	}

	/**
	 * Check if a value appears to be encrypted data.
	 *
	 * @param string $value The value to check.
	 * @return bool True if value appears to be encrypted.
	 */
	private static function is_encrypted_value( string $value ): bool {
		// Check if it's base64 encoded (encrypted data is base64 encoded)
		if ( ! preg_match( '/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $value ) ) {
			return false;
		}

		// Try to decode and check if it looks like encrypted binary data
		$decoded = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_base64_decode -- Used for encrypted data validation.
		if ( false === $decoded ) {
			return false;
		}

		// Encrypted data should be at least 28 bytes (IV + tag + minimal ciphertext)
		return strlen( $decoded ) >= 28;
	}

	/**
	 * Validate API key format using provider-specific pattern.
	 *
	 * @param string $value The API key to validate.
	 * @param string $pattern Optional regex pattern. If not provided, uses generic validation.
	 * @return bool True if valid API key format.
	 */
	public static function is_valid_api_key_format( string $value, string $pattern = '' ): bool {
		// If no pattern provided, use generic validation
		if ( empty( $pattern ) ) {
			// Generic: 20+ character alphanumeric with optional separators
			$pattern = '/^[a-zA-Z0-9_-]{20,}$/';
		}

		return (bool) preg_match( $pattern, $value );
	}
}
