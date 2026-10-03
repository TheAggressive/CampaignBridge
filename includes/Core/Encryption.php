<?php
/**
 * Authenticated encryption for CampaignBridge.
 *
 * Provides authenticated encryption using AES-256-GCM with context-aware
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
 * Encryption class.
 *
 * Handles encryption/decryption of sensitive data with:
 * - AES-256-GCM authenticated encryption in versioned `cbenc:v1:<key-id>:`
 *   envelopes, so every key source shares one ciphertext format
 * - Context-aware permission levels (api_key, sensitive, personal, public)
 * - Keys resolved by Encryption_Keyring: an external key from wp-config.php
 *   when configured, otherwise the database fallback key
 * - Deliberate re-encryption under the current key; never automatic
 */
class Encryption {

	/**
	 * Encryption algorithm constant.
	 */
	private const ALGORITHM = 'aes-256-gcm';


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
	 * Minimum PHP version required for GCM support and security features.
	 */
	private const MIN_PHP_VERSION = '8.2.0';

	/**
	 * Encrypt an API key with authenticated encryption.
	 *
	 * @param string                  $plaintext The API key to encrypt.
	 * @param Encryption_Keyring|null $keyring   Key sources; the site configuration by default.
	 * @return string The encrypted API key with metadata.
	 * @throws \RuntimeException If encryption fails, no valid key is configured, or PHP version is insufficient.
	 */
	public static function encrypt( #[\SensitiveParameter] string $plaintext, ?Encryption_Keyring $keyring = null ): string {
		self::validate_php_version();

		if ( empty( $plaintext ) ) {
			return '';
		}

		$current    = ( $keyring ?? Encryption_Keyring::configured() )->current();
		$key        = $current['material'];
		$key_id     = $current['id'];
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
	 * @param string                  $encrypted The encrypted API key from storage.
	 * @param Encryption_Keyring|null $keyring   Key sources; the site configuration by default.
	 * @return string The decrypted API key.
	 * @throws \RuntimeException If decryption fails or data is corrupted.
	 */
	public static function decrypt( string $encrypted, ?Encryption_Keyring $keyring = null ): string {
		self::validate_php_version();

		if ( empty( $encrypted ) ) {
			return '';
		}

		try {
			if ( ! str_starts_with( $encrypted, self::ENVELOPE_PREFIX ) ) {
				throw new \RuntimeException( 'Refusing to decrypt plaintext or an unknown ciphertext format' );
			}

			return self::decrypt_envelope( $encrypted, $keyring ?? Encryption_Keyring::configured() );

		} catch ( \Throwable $e ) {
			// Log only the fixed internal message. A stack trace can carry
			// argument values, which here are ciphertext and key material.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\CampaignBridge\Core\Error_Handler::error(
					'CampaignBridge API key decryption failed',
					array( 'error' => $e->getMessage() )
				);
			}

			throw new \RuntimeException( 'Invalid encrypted data' );
		}
	}


	/**
	 * Decrypt data with context-aware permission checking.
	 *
	 * Different contexts have different permission requirements:
	 * - 'api_key': Users who can manage provider connections
	 * - 'sensitive': Users with general CampaignBridge management access
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
	 * Requires CampaignBridge management permissions.
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
				return \current_user_can( Capabilities::MANAGE_CONNECTIONS );

			case 'sensitive':
				return \current_user_can( Capabilities::MANAGE );

			case 'personal':
				return is_user_logged_in();

			case 'public':
				return true; // No restrictions for public data.

			default:
				// Unknown context - require admin.
				return \current_user_can( Capabilities::MANAGE );
		}
	}

	/**
	 * Rotate the database fallback key, keeping the old key for decryption.
	 *
	 * Existing values stay decryptable and are moved to the new key only by
	 * a deliberate re-encryption. External keys are rotated by the operator in
	 * wp-config.php, so this refuses when one is configured.
	 *
	 * @param bool $force Force rotation even if not scheduled.
	 * @return bool True if rotation was performed.
	 */
	public static function rotate_master_key( bool $force = false ): bool {
		$metadata = Encryption_Keyring::database_metadata();

		// Check if rotation is needed (30 days max age).
		$should_rotate = $force ||
			! isset( $metadata['created'] ) ||
			( time() - $metadata['created'] ) > ( 30 * DAY_IN_SECONDS );

		if ( ! $should_rotate || ! Encryption_Keyring::configured()->rotate_database_key() ) {
			return false;
		}

		// Log the rotation (without exposing the key).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\CampaignBridge\Core\Error_Handler::info(
				'CampaignBridge master encryption key rotated',
				array( 'version' => Encryption_Keyring::database_metadata()['version'] ?? null )
			);
		}

		return true;
	}

	/**
	 * Re-encrypt a value under the current key.
	 *
	 * A value already under the current key is returned unchanged. The new
	 * envelope is decrypted and compared before it is returned, so a caller
	 * that stores it never replaces a readable value with an unreadable one.
	 *
	 * @param string                  $encrypted Stored envelope.
	 * @param Encryption_Keyring|null $keyring   Key sources; the site configuration by default.
	 * @return string Envelope under the current key.
	 * @throws \RuntimeException When the value cannot be decrypted or verified.
	 */
	public static function reencrypt( string $encrypted, ?Encryption_Keyring $keyring = null ): string {
		$keyring = $keyring ?? Encryption_Keyring::configured();
		if ( null !== $keyring->current_id() && self::key_id_of( $encrypted ) === $keyring->current_id() ) {
			return $encrypted;
		}

		$plaintext = self::decrypt( $encrypted, $keyring );
		$replaced  = self::encrypt( $plaintext, $keyring );
		if ( ! hash_equals( $plaintext, self::decrypt( $replaced, $keyring ) ) ) {
			throw new \RuntimeException( 'Re-encrypted value could not be verified' );
		}

		return $replaced;
	}

	/**
	 * Which key protects a stored value, without decrypting it.
	 *
	 * @param string                  $encrypted Stored envelope.
	 * @param Encryption_Keyring|null $keyring   Key sources; the site configuration by default.
	 * @return string `current` when under the key that encrypts new values,
	 *                `previous` when readable only through a retired or
	 *                database fallback key, or `unavailable`.
	 */
	public static function key_status( string $encrypted, ?Encryption_Keyring $keyring = null ): string {
		$keyring = $keyring ?? Encryption_Keyring::configured();
		$key_id  = self::key_id_of( $encrypted );
		if ( null === $key_id || null === $keyring->source_of( $key_id ) ) {
			return 'unavailable';
		}

		return $key_id === $keyring->current_id() ? 'current' : 'previous';
	}

	/**
	 * Key ID named by a versioned envelope.
	 *
	 * @param string $encrypted Stored envelope.
	 * @return string|null Key ID, or null when the value is not an envelope.
	 */
	public static function key_id_of( string $encrypted ): ?string {
		return self::is_encrypted_value( $encrypted ) ? explode( ':', $encrypted, 4 )[2] : null;
	}

	/**
	 * Decrypt a versioned envelope.
	 *
	 * @param string             $encrypted Versioned ciphertext.
	 * @param Encryption_Keyring $keyring   Key sources.
	 * @return string Plaintext.
	 * @throws \RuntimeException When the envelope or key is invalid.
	 */
	private static function decrypt_envelope( string $encrypted, Encryption_Keyring $keyring ): string {
		$parts = explode( ':', $encrypted, 4 );
		if ( 4 !== count( $parts ) || 'cbenc' !== $parts[0] || 'v1' !== $parts[1] ) {
			throw new \RuntimeException( 'Invalid encrypted data format' );
		}

		$key = $keyring->material_for( $parts[2] );
		if ( null === $key ) {
			throw new \RuntimeException( 'Encryption key is unavailable' );
		}

		return self::decrypt_payload( $parts[3], $key );
	}

	/**
	 * Decrypt an IV/tag/ciphertext payload.
	 *
	 * @param string $payload Base64 payload.
	 * @param string $key     OpenSSL key material.
	 * @return string Plaintext.
	 * @throws \RuntimeException When authentication or payload validation fails.
	 */
	private static function decrypt_payload( string $payload, #[\SensitiveParameter] string $key ): string {
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

		$keyring = Encryption_Keyring::configured();
		if ( ! $keyring->is_valid() ) {
			$issues[] = sprintf( '%s is defined but is not the base64 encoding of 32 bytes; credentials cannot be encrypted or decrypted', Encryption_Keyring::KEY_CONSTANT );
			$secure   = false;
		} elseif ( Encryption_Keyring::SOURCE_DATABASE === $keyring->source() ) {
			$issues[] = sprintf( 'The encryption key is stored in the database; define %s in wp-config.php to keep it outside the database', Encryption_Keyring::KEY_CONSTANT );
		}

		// Check database fallback key age.
		$metadata = Encryption_Keyring::database_metadata();
		if ( Encryption_Keyring::SOURCE_DATABASE === $keyring->source() && isset( $metadata['created'] ) ) {
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
			'key_source'            => $keyring->source(),
			'key_configuration_ok'  => $keyring->is_valid(),
			'master_key_exists'     => Encryption_Keyring::database_key_exists(),
			'key_rotation_due'      => Encryption_Keyring::SOURCE_DATABASE === $keyring->source() && isset( $metadata['created'] ) && ( time() - $metadata['created'] ) > ( 90 * DAY_IN_SECONDS ),
		);
	}

	/**
	 * Check if a value appears to be encrypted data.
	 *
	 * @param string $value The value to check.
	 * @return bool True if value appears to be encrypted.
	 */
	public static function is_encrypted_value( string $value ): bool {
		if ( ! str_starts_with( $value, self::ENVELOPE_PREFIX ) ) {
			return false;
		}

		$parts = explode( ':', $value, 4 );
		return 4 === count( $parts ) && 16 === strlen( $parts[2] ) && self::has_encrypted_payload_shape( $parts[3] );
	}

	/**
	 * Check if a base64 payload has the shape of encrypted binary data.
	 *
	 * @param string $value Candidate value.
	 * @return bool Whether the value has the ciphertext payload shape.
	 */
	private static function has_encrypted_payload_shape( string $value ): bool {
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
