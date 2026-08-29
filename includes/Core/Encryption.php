<?php
/**
 * Ultra-secure Encryption for CampaignBridge.
 *
 * Provides military-grade encryption using AES-256-GCM with context-aware
 * permission levels for different types of sensitive data.
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
 * Ultra-secure Encryption Class
 *
 * Handles encryption/decryption of sensitive data with:
 * - AES-256-GCM encryption for authenticated encryption
 * - Context-aware permission levels (api_key, sensitive, personal, public)
 * - Secure key derivation and management
 * - Key rotation capabilities
 * - Comprehensive error handling
 * - WordPress security best practices
 */
class Encryption {

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
	 * Versioned ciphertext envelope prefix.
	 */
	private const ENVELOPE_PREFIX = 'cbenc:v1:';

	/**
	 * Option name for storing the master encryption key.
	 */
	private const MASTER_KEY_OPTION = 'campaignbridge_master_key';

	/**
	 * Option name for storing key rotation metadata.
	 */
	private const KEY_META_OPTION = 'campaignbridge_key_metadata';

	/**
	 * Retired keys retained for decrypting data written before rotation.
	 */
	private const RETIRED_KEYS_OPTION = 'campaignbridge_retired_encryption_keys';

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

		$stored_key = self::get_master_key();
		$key        = self::key_material( $stored_key );
		$key_id     = self::key_id( $stored_key );
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
		return self::ENVELOPE_PREFIX . $key_id . ':' . base64_encode( $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
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

		try {
			if ( str_starts_with( $encrypted, self::ENVELOPE_PREFIX ) ) {
				return self::decrypt_envelope( $encrypted );
			}

			if ( self::is_legacy_encrypted_value( $encrypted ) ) {
				return self::decrypt_legacy( $encrypted );
			}

			throw new \RuntimeException( 'Refusing to decrypt plaintext or an unknown ciphertext format' );

		} catch ( \Throwable $e ) {
			// Log the error for debugging but don't expose details.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\CampaignBridge\Core\Error_Handler::error(
					'CampaignBridge API key decryption failed',
					array(
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					)
				);
			}

			throw new \RuntimeException( 'Invalid encrypted data' );
		}
	}


	/**
	 * Decrypt data with context-aware permission checking.
	 *
	 * Different contexts have different permission requirements:
	 * - 'api_key': Only administrators (manage_options)
	 * - 'sensitive': Only administrators (manage_options)
	 * - 'personal': Logged-in users can access their own data
	 * - 'public': No restrictions (for encrypted but non-sensitive data)
	 *
	 * @param string $encrypted The encrypted data from storage.
	 * @param string $context The security context ('api_key', 'sensitive', 'personal', 'public').
	 * @return string The decrypted data.
	 * @throws \RuntimeException If decryption fails, data is corrupted, or user lacks permission.
	 */
	public static function decrypt_for_context( string $encrypted, string $context = 'sensitive' ): string {
		// Check permissions based on context.
		if ( ! self::check_context_permissions( $context ) ) {
			throw new \RuntimeException(
				sprintf( 'Unauthorized attempt to view decrypted data in context: %s', esc_html( $context ) )
			);
		}

		return self::decrypt( $encrypted );
	}

	/**
	 * Decrypt data for display purposes (admin interface, settings pages).
	 *
	 * This is a convenience method that decrypts data for display in admin interfaces.
	 * Requires administrator permissions (manage_options).
	 *
	 * @param string $encrypted The encrypted data from storage.
	 * @return string The decrypted data for display.
	 * @throws \RuntimeException If decryption fails, data is corrupted, or user lacks permission.
	 */
	public static function decrypt_for_display( string $encrypted ): string {
		return self::decrypt_for_context( $encrypted, 'sensitive' );
	}

	/**
	 * Check if current user has permission for the given context.
	 *
	 * @param string $context The security context.
	 * @return bool True if user has permission.
	 */
	private static function check_context_permissions( string $context ): bool {
		// Operational code uses decrypt() directly. Context-aware display access
		// must fail closed when WordPress cannot establish an authenticated user.
		$has_user_context = function_exists( 'wp_get_current_user' ) && function_exists( 'current_user_can' ) && function_exists( 'is_user_logged_in' );

		if ( ! $has_user_context ) {
			return 'public' === $context;
		}

		// Try to get current user safely.
		$current_user = \wp_get_current_user();
		$user_loaded  = $current_user->exists();

		if ( ! $user_loaded ) {
			return 'public' === $context;
		}

		// Normal permission checking.
		switch ( $context ) {
			case 'api_key':
			case 'sensitive':
				return \current_user_can( 'manage_options' );

			case 'personal':
				return is_user_logged_in();

			case 'public':
				return true; // No restrictions for public data.

			default:
				// Unknown context - require admin.
				return \current_user_can( 'manage_options' );
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

		// Check if rotation is needed (30 days max age).
		$should_rotate = $force ||
			! isset( $metadata['created'] ) ||
			( time() - $metadata['created'] ) > ( 30 * DAY_IN_SECONDS );

		if ( ! $should_rotate ) {
			return false;
		}

		// Retain the current key so existing envelopes remain decryptable.
		$current_key = self::get_master_key();
		$retired     = self::get_retired_keys();

		$retired[ self::key_id( $current_key ) ] = $current_key;

		// Generate new master key.
		$new_key = self::generate_master_key();

		// Store the new key.
		// phpcs:ignore CampaignBridge.Standard.Sniffs.Security.SecurityValidation.MissingNonceVerification -- Internal key rotation, no user request context available
		$retired_saved = \CampaignBridge\Core\Storage::update_option( self::RETIRED_KEYS_OPTION, $retired );
		$success       = $retired_saved && \CampaignBridge\Core\Storage::update_option( self::MASTER_KEY_OPTION, $new_key );

		if ( $success ) {
			// Update metadata.
			$metadata['created'] = time();
			$metadata['version'] = ( $metadata['version'] ?? 0 ) + 1;
			// phpcs:ignore CampaignBridge.Standard.Sniffs.Security.SecurityValidation.MissingNonceVerification -- Internal key rotation, no user request context available
			\CampaignBridge\Core\Storage::update_option( self::KEY_META_OPTION, $metadata );

			// Log the rotation (without exposing the key).
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\CampaignBridge\Core\Error_Handler::info(
					'CampaignBridge master encryption key rotated',
					array( 'version' => $metadata['version'] )
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
			// phpcs:ignore CampaignBridge.Standard.Sniffs.Security.SecurityValidation.MissingNonceVerification -- Internal key generation, no user request context available
			\CampaignBridge\Core\Storage::add_option( self::MASTER_KEY_OPTION, $stored_key );

			// Initialize metadata.
			$metadata = array(
				'created' => time(),
				'version' => 1,
			);
			// phpcs:ignore CampaignBridge.Standard.Sniffs.Security.SecurityValidation.MissingNonceVerification -- Internal key generation, no user request context available
			\CampaignBridge\Core\Storage::add_option( self::KEY_META_OPTION, $metadata );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\CampaignBridge\Core\Error_Handler::info(
					'CampaignBridge master encryption key generated'
				);
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
	 * Convert legacy plaintext or unversioned ciphertext to the current envelope.
	 *
	 * This method is intentionally explicit; normal decryption never accepts
	 * plaintext as though it were protected.
	 *
	 * @param string $value Stored credential value.
	 * @return string Current encrypted envelope.
	 */
	public static function migrate_legacy_value( string $value ): string {
		if ( '' === $value || str_starts_with( $value, self::ENVELOPE_PREFIX ) ) {
			return $value;
		}

		$plaintext = self::is_legacy_encrypted_value( $value ) ? self::decrypt_legacy( $value ) : $value;
		return self::encrypt( $plaintext );
	}

	/**
	 * Decrypt a versioned envelope.
	 *
	 * @param string $encrypted Versioned ciphertext.
	 * @return string Plaintext.
	 * @throws \RuntimeException When the envelope or key is invalid.
	 */
	private static function decrypt_envelope( string $encrypted ): string {
		$parts = explode( ':', $encrypted, 4 );
		if ( 4 !== count( $parts ) || 'cbenc' !== $parts[0] || 'v1' !== $parts[1] ) {
			throw new \RuntimeException( 'Invalid encrypted data format' );
		}

		$key_id = $parts[2];
		$keys   = self::get_decryption_keys();
		if ( ! isset( $keys[ $key_id ] ) ) {
			throw new \RuntimeException( 'Encryption key is unavailable' );
		}

		return self::decrypt_payload( $parts[3], self::key_material( $keys[ $key_id ] ) );
	}

	/**
	 * Decrypt ciphertext written before versioned envelopes were introduced.
	 *
	 * @param string $encrypted Legacy base64 ciphertext.
	 * @return string Plaintext.
	 * @throws \RuntimeException When no retained key can decrypt the value.
	 */
	private static function decrypt_legacy( string $encrypted ): string {
		foreach ( self::get_decryption_keys() as $stored_key ) {
			try {
				// Legacy code passed the base64 representation directly to OpenSSL.
				return self::decrypt_payload( $encrypted, $stored_key );
			} catch ( \RuntimeException $e ) {
				continue;
			}
		}

		throw new \RuntimeException( 'Invalid encrypted data' );
	}

	/**
	 * Decrypt an IV/tag/ciphertext payload.
	 *
	 * @param string $payload Base64 payload.
	 * @param string $key     OpenSSL key material.
	 * @return string Plaintext.
	 * @throws \RuntimeException When authentication or payload validation fails.
	 */
	private static function decrypt_payload( string $payload, string $key ): string {
		$decoded = base64_decode( $payload, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $decoded || strlen( $decoded ) < self::IV_LENGTH + self::TAG_LENGTH ) {
			throw new \RuntimeException( 'Invalid encrypted data format' );
		}

		$iv         = substr( $decoded, 0, self::IV_LENGTH );
		$tag        = substr( $decoded, self::IV_LENGTH, self::TAG_LENGTH );
		$ciphertext = substr( $decoded, self::IV_LENGTH + self::TAG_LENGTH );
		$plaintext  = openssl_decrypt( $ciphertext, self::ALGORITHM, $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $plaintext ) {
			throw new \RuntimeException( 'Invalid encrypted data' );
		}

		return $plaintext;
	}

	/**
	 * Return current and retired keys indexed by stable key ID.
	 *
	 * @return array<string, string>
	 */
	private static function get_decryption_keys(): array {
		$current = self::get_master_key();
		return array( self::key_id( $current ) => $current ) + self::get_retired_keys();
	}

	/**
	 * Get retired encryption keys.
	 *
	 * @return array<string, string>
	 */
	private static function get_retired_keys(): array {
		$keys = \CampaignBridge\Core\Storage::get_option( self::RETIRED_KEYS_OPTION, array() );
		return is_array( $keys ) ? array_filter( $keys, 'is_string' ) : array();
	}

	/**
	 * Derive binary AES key material from a stored key.
	 *
	 * @param string $stored_key Base64 stored key.
	 * @return string Binary key.
	 * @throws \RuntimeException When stored key material is invalid.
	 */
	private static function key_material( string $stored_key ): string {
		$decoded = base64_decode( $stored_key, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $decoded || self::KEY_LENGTH !== strlen( $decoded ) ) {
			throw new \RuntimeException( 'Invalid encryption key material' );
		}

		return $decoded;
	}

	/**
	 * Generate a non-secret identifier for an encryption key.
	 *
	 * @param string $stored_key Stored key.
	 * @return string Key identifier.
	 */
	private static function key_id( string $stored_key ): string {
		return substr( hash( 'sha256', $stored_key ), 0, 16 );
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
			'secure'                => $secure,
			'issues'                => $issues,
			'php_version_supported' => version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '>=' ),
			'openssl_available'     => extension_loaded( 'openssl' ),
			'gcm_supported'         => in_array( self::ALGORITHM, openssl_get_cipher_methods(), true ),
			'master_key_exists'     => \CampaignBridge\Core\Storage::get_option( self::MASTER_KEY_OPTION ) !== false,
			'key_rotation_due'      => isset( $metadata['created'] ) && ( time() - $metadata['created'] ) > ( 90 * DAY_IN_SECONDS ),
		);
	}

	/**
	 * Check if a value appears to be encrypted data.
	 *
	 * @param string $value The value to check.
	 * @return bool True if value appears to be encrypted.
	 */
	public static function is_encrypted_value( string $value ): bool {
		if ( str_starts_with( $value, self::ENVELOPE_PREFIX ) ) {
			$parts = explode( ':', $value, 4 );
			return 4 === count( $parts ) && 16 === strlen( $parts[2] ) && self::is_legacy_encrypted_value( $parts[3] );
		}

		return self::is_legacy_encrypted_value( $value );
	}

	/**
	 * Detect the pre-envelope base64 format.
	 *
	 * @param string $value Candidate value.
	 * @return bool Whether the value has the legacy ciphertext shape.
	 */
	private static function is_legacy_encrypted_value( string $value ): bool {
		// Basic security: only accept reasonable length values.
		// Allow up to ~10KB for encrypted data (handles large strings with base64 overhead).
		if ( strlen( $value ) < 20 || strlen( $value ) > 10000 ) {
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
			// Generic: 8-100 character alphanumeric with optional separators.
			$pattern = '/^[a-zA-Z0-9_-]{8,100}$/';
		}

		return (bool) preg_match( $pattern, $value );
	}
}
