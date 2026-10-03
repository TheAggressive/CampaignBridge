<?php
/**
 * Encryption key sources for CampaignBridge credentials.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the current encryption key and every key that may decrypt.
 *
 * Two sources exist:
 *
 * - External: `CAMPAIGNBRIDGE_ENCRYPTION_KEY`, defined in wp-config.php
 *   (typically from the host's secret store or environment), holds the base64
 *   encoding of 32 random bytes. When defined, it is the only key that
 *   encrypts, and key material never enters the database. Previous external
 *   keys may stay readable through `CAMPAIGNBRIDGE_ENCRYPTION_RETIRED_KEYS`, a
 *   comma-separated list in the same format.
 * - Database fallback: for installations without external secret
 *   infrastructure, a generated key stored in WordPress options. A database-only
 *   compromise exposes it together with the ciphertext it protects. With an
 *   external key configured it is read only to decrypt older values and is
 *   never generated.
 *
 * Envelopes name their key by ID, so every source shares one ciphertext
 * format and a future source (such as a KMS) only has to resolve key IDs.
 *
 * A defined but invalid external configuration fails closed: nothing is
 * encrypted or decrypted, and nothing falls back to the database key.
 *
 * @internal Key material is returned only to Encryption.
 */
final class Encryption_Keyring {
	/** Canonical external current-key constant. */
	public const KEY_CONSTANT = 'CAMPAIGNBRIDGE_ENCRYPTION_KEY';

	/** Optional comma-separated external keys kept only for decryption. */
	public const RETIRED_KEYS_CONSTANT = 'CAMPAIGNBRIDGE_ENCRYPTION_RETIRED_KEYS';

	public const SOURCE_EXTERNAL = 'external';
	public const SOURCE_DATABASE = 'database';

	/** Key length for AES-256. */
	private const KEY_LENGTH = 32;

	/** Option holding the database fallback key. */
	private const MASTER_KEY_OPTION = 'campaignbridge_master_key';

	/** Option holding database fallback rotation metadata. */
	private const KEY_META_OPTION = 'campaignbridge_key_metadata';

	/** Option holding retired database fallback keys. */
	private const RETIRED_KEYS_OPTION = 'campaignbridge_retired_encryption_keys';

	/** Generic refusal; never names or echoes configured values. */
	private const INVALID_CONFIGURATION = 'CampaignBridge encryption key configuration is invalid.';

	/**
	 * Create a keyring.
	 *
	 * @param string|null           $external Canonical external current key, or null when not configured.
	 * @param array<string, string> $retired  Canonical external retired keys by ID.
	 * @param bool                  $valid    Whether the external configuration is usable.
	 */
	private function __construct(
		#[\SensitiveParameter] private readonly ?string $external,
		#[\SensitiveParameter] private readonly array $retired,
		private readonly bool $valid
	) {}

	/** The keyring described by this site's configuration. */
	public static function configured(): self {
		return self::from_configuration(
			defined( self::KEY_CONSTANT ) ? constant( self::KEY_CONSTANT ) : null,
			defined( self::RETIRED_KEYS_CONSTANT ) ? constant( self::RETIRED_KEYS_CONSTANT ) : null
		);
	}

	/**
	 * Build a keyring from external configuration values.
	 *
	 * @param mixed $current Current external key, or null when not configured.
	 * @param mixed $retired Comma-separated retired external keys, or null.
	 */
	public static function from_configuration( #[\SensitiveParameter] mixed $current, #[\SensitiveParameter] mixed $retired ): self {
		$valid    = true;
		$external = null;
		if ( null !== $current ) {
			$external = is_string( $current ) ? self::canonical( $current ) : null;
			$valid    = null !== $external;
		}

		$retired_keys = array();
		if ( null !== $retired ) {
			if ( ! is_string( $retired ) ) {
				$valid = false;
			} else {
				foreach ( array_filter( array_map( 'trim', explode( ',', $retired ) ), static fn ( string $entry ): bool => '' !== $entry ) as $candidate ) {
					$key = self::canonical( $candidate );
					if ( null === $key ) {
						$valid = false;
						continue;
					}
					$retired_keys[ self::key_id( $key ) ] = $key;
				}
			}
		}

		return new self( $valid ? $external : null, $valid ? $retired_keys : array(), $valid );
	}

	/** Whether the configuration can encrypt and decrypt. */
	public function is_valid(): bool {
		return $this->valid;
	}

	/** Which source encrypts new values. */
	public function source(): string {
		return null !== $this->external || ! $this->valid ? self::SOURCE_EXTERNAL : self::SOURCE_DATABASE;
	}

	/**
	 * The key that encrypts new values.
	 *
	 * @return array{id: string, material: string}
	 * @throws \RuntimeException When the configuration is invalid or no key can be established.
	 */
	public function current(): array {
		$this->assert_valid();
		$stored = $this->external ?? $this->database_current( true );
		if ( null === $stored ) {
			throw new \RuntimeException( 'CampaignBridge encryption key is unavailable.' );
		}

		return array(
			'id'       => self::key_id( $stored ),
			'material' => self::material( $stored ),
		);
	}

	/** ID of the key that encrypts new values, without creating one. */
	public function current_id(): ?string {
		if ( ! $this->valid ) {
			return null;
		}
		$stored = $this->external ?? $this->database_current( false );

		return null === $stored ? null : self::key_id( $stored );
	}

	/**
	 * Key material for one envelope key ID.
	 *
	 * @param string $key_id Envelope key ID.
	 * @return string|null Binary key, or null when no source holds the ID.
	 * @throws \RuntimeException When the configuration is invalid.
	 */
	public function material_for( string $key_id ): ?string {
		$this->assert_valid();
		$stored = $this->decryption_keys()[ $key_id ] ?? null;

		return null === $stored ? null : self::material( $stored );
	}

	/**
	 * Which source holds a key ID.
	 *
	 * @param string $key_id Envelope key ID.
	 * @return string|null Source, or null when the key is unavailable.
	 */
	public function source_of( string $key_id ): ?string {
		if ( ! $this->valid ) {
			return null;
		}
		if ( ( null !== $this->external && self::key_id( $this->external ) === $key_id ) || isset( $this->retired[ $key_id ] ) ) {
			return self::SOURCE_EXTERNAL;
		}

		return isset( $this->database_keys()[ $key_id ] ) ? self::SOURCE_DATABASE : null;
	}

	/**
	 * Rotate the database fallback key, retaining the old one for decryption.
	 *
	 * External keys are rotated by the operator, so this refuses when one is
	 * configured.
	 */
	public function rotate_database_key(): bool {
		if ( ! $this->valid || null !== $this->external ) {
			return false;
		}

		$current = $this->database_current( true );
		if ( null === $current ) {
			return false;
		}
		$retired                             = self::database_retired();
		$retired[ self::key_id( $current ) ] = $current;

		$retired_saved = Storage::update_option( self::RETIRED_KEYS_OPTION, $retired );
		if ( ! $retired_saved || ! Storage::update_option( self::MASTER_KEY_OPTION, self::generate() ) ) {
			return false;
		}

		$metadata            = self::database_metadata();
		$metadata['created'] = time();
		$metadata['version'] = (int) ( $metadata['version'] ?? 0 ) + 1;
		Storage::update_option( self::KEY_META_OPTION, $metadata );

		return true;
	}

	/**
	 * Database fallback rotation metadata.
	 *
	 * @return array<string, mixed>
	 */
	public static function database_metadata(): array {
		$metadata = Storage::get_option( self::KEY_META_OPTION, array() );

		return is_array( $metadata ) ? $metadata : array();
	}

	/** Whether a database fallback key has been generated. */
	public static function database_key_exists(): bool {
		return is_string( Storage::get_option( self::MASTER_KEY_OPTION ) );
	}

	/**
	 * Non-secret identifier of a stored key.
	 *
	 * @param string $stored_key Base64 key.
	 */
	public static function key_id( #[\SensitiveParameter] string $stored_key ): string {
		return substr( hash( 'sha256', $stored_key ), 0, 16 );
	}

	/** Prevent key material from appearing in var_dump() or print_r(). */
	public function __debugInfo(): array {
		return array(
			'source' => $this->source(),
			'valid'  => $this->valid,
		);
	}

	/**
	 * Keyrings hold key material and are never serialized.
	 *
	 * @throws \LogicException Always.
	 */
	public function __serialize(): array {
		throw new \LogicException( 'An encryption keyring cannot be serialized.' );
	}

	/**
	 * Refuse every key operation for an invalid configuration.
	 *
	 * @throws \RuntimeException When the configuration is invalid.
	 */
	private function assert_valid(): void {
		if ( ! $this->valid ) {
			throw new \RuntimeException( self::INVALID_CONFIGURATION ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed internal message.
		}
	}

	/**
	 * Every key that may decrypt, by ID.
	 *
	 * @return array<string, string>
	 */
	private function decryption_keys(): array {
		$keys = array();
		if ( null !== $this->external ) {
			$keys[ self::key_id( $this->external ) ] = $this->external;
		}

		return $keys + $this->retired + $this->database_keys();
	}

	/**
	 * Database fallback keys, current and retired, by ID.
	 *
	 * @return array<string, string>
	 */
	private function database_keys(): array {
		$keys    = array();
		$current = $this->database_current( false );
		if ( null !== $current ) {
			$keys[ self::key_id( $current ) ] = $current;
		}

		return $keys + self::database_retired();
	}

	/**
	 * The database fallback key, generated on first use only without an external key.
	 *
	 * @param bool $create Whether a missing key may be generated.
	 */
	private function database_current( bool $create ): ?string {
		$stored = Storage::get_option( self::MASTER_KEY_OPTION );
		if ( is_string( $stored ) && '' !== $stored ) {
			return $stored;
		}
		if ( ! $create || null !== $this->external ) {
			return null;
		}

		$stored = self::generate();
		if ( ! Storage::add_option( self::MASTER_KEY_OPTION, $stored ) ) {
			// Another request created it first; use the stored key, never ours.
			$existing = Storage::get_option( self::MASTER_KEY_OPTION );

			return is_string( $existing ) && '' !== $existing ? $existing : null;
		}
		Storage::add_option(
			self::KEY_META_OPTION,
			array(
				'created' => time(),
				'version' => 1,
			)
		);

		return $stored;
	}

	/**
	 * Retired database fallback keys by ID.
	 *
	 * @return array<string, string>
	 */
	private static function database_retired(): array {
		$keys = Storage::get_option( self::RETIRED_KEYS_OPTION, array() );

		return is_array( $keys ) ? array_filter( $keys, 'is_string' ) : array();
	}

	/**
	 * Canonical base64 form of a configured key, or null when it is not 32 bytes.
	 *
	 * @param string $configured Configured base64 key.
	 */
	private static function canonical( #[\SensitiveParameter] string $configured ): ?string {
		$decoded = base64_decode( trim( $configured ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $decoded || self::KEY_LENGTH !== strlen( $decoded ) ) {
			return null;
		}

		return base64_encode( $decoded ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/**
	 * Binary key material from a stored key.
	 *
	 * @param string $stored_key Base64 key.
	 * @throws \RuntimeException When the stored key is malformed.
	 */
	private static function material( #[\SensitiveParameter] string $stored_key ): string {
		$decoded = base64_decode( $stored_key, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $decoded || self::KEY_LENGTH !== strlen( $decoded ) ) {
			throw new \RuntimeException( 'Invalid encryption key material' );
		}

		return $decoded;
	}

	/** Generate a new base64 database fallback key. */
	private static function generate(): string {
		return base64_encode( random_bytes( self::KEY_LENGTH ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}
}
