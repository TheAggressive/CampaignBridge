<?php
/**
 * Provider connection value object.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One versioned provider connection record.
 *
 * Encapsulates encrypted credentials, audience reference, verification
 * state, and schema version. Immutable: all mutations produce a new
 * instance.
 */
final class Provider_Connection {
	/** Current schema version. */
	public const SCHEMA_VERSION = 1;

	/**
	 * Create a provider connection value.
	 *
	 * @param string               $provider_slug  Provider slug identifier.
	 * @param string               $api_key        Encrypted API key (opaque).
	 * @param string               $audience_id    Audience reference (empty when unset).
	 * @param int                  $schema_version Schema version stamp.
	 * @param string|null          $last_verified_at ISO 8601 timestamp or null.
	 * @param bool                 $verified       Whether the last verification succeeded.
	 * @param array<string, mixed> $account_details Normalized account details from last verification.
	 *
	 * @throws \InvalidArgumentException When any field is invalid.
	 */
	private function __construct(
		private readonly string $provider_slug,
		private readonly string $api_key,
		private readonly string $audience_id,
		private readonly int $schema_version,
		private readonly ?string $last_verified_at,
		private readonly bool $verified,
		private readonly array $account_details
	) {
		if ( '' === $provider_slug ) {
			throw new \InvalidArgumentException( 'Provider slug must not be empty.' );
		}
		if ( '' === $api_key ) {
			throw new \InvalidArgumentException( 'API key must not be empty.' );
		}
		if ( $schema_version < 1 || $schema_version > self::SCHEMA_VERSION ) {
			throw new \InvalidArgumentException( 'Unsupported schema version.' );
		}
	}

	/**
	 * Create a new connection with unverified state.
	 *
	 * @param string $provider_slug Provider slug identifier.
	 * @param string $api_key       Encrypted API key.
	 * @param string $audience_id   Audience reference (empty when unset).
	 */
	public static function create( string $provider_slug, string $api_key, string $audience_id = '' ): self {
		return new self(
			$provider_slug,
			$api_key,
			$audience_id,
			self::SCHEMA_VERSION,
			null,
			false,
			array()
		);
	}

	/**
	 * Reconstitute a connection from a stored array.
	 *
	 * Strict validation: rejects missing, empty, or malformed required fields.
	 * Rejects unsupported future schema versions.
	 *
	 * @param array<string, mixed> $data Stored connection data.
	 *
	 * @throws \InvalidArgumentException When the record is malformed.
	 */
	public static function from_array( array $data ): self {
		$version = $data['schema_version'] ?? null;
		if ( ! is_int( $version ) || $version < 1 ) {
			throw new \InvalidArgumentException( 'Invalid schema version.' );
		}
		if ( $version > self::SCHEMA_VERSION ) {
			throw new \InvalidArgumentException(
				sprintf( 'Unsupported provider connection schema version %d.', $version )
			);
		}

		$provider_slug = $data['provider_slug'] ?? null;
		if ( ! is_string( $provider_slug ) || '' === $provider_slug ) {
			throw new \InvalidArgumentException( 'Invalid provider slug.' );
		}

		$api_key = $data['api_key'] ?? null;
		if ( ! is_string( $api_key ) || '' === $api_key ) {
			throw new \InvalidArgumentException( 'Invalid API key.' );
		}

		$audience_id = $data['audience_id'] ?? '';
		if ( ! is_string( $audience_id ) ) {
			throw new \InvalidArgumentException( 'Invalid audience ID.' );
		}

		$last_verified_at = $data['last_verified_at'] ?? null;
		if ( null !== $last_verified_at && ! is_string( $last_verified_at ) ) {
			throw new \InvalidArgumentException( 'Invalid last_verified_at.' );
		}

		$verified = $data['verified'] ?? false;
		if ( ! is_bool( $verified ) ) {
			throw new \InvalidArgumentException( 'Invalid verified flag.' );
		}

		$account_details = $data['account_details'] ?? array();
		if ( ! is_array( $account_details ) ) {
			throw new \InvalidArgumentException( 'Invalid account details.' );
		}

		return new self(
			$provider_slug,
			$api_key,
			$audience_id,
			$version,
			$last_verified_at,
			$verified,
			$account_details
		);
	}

	/** Get the provider slug. */
	public function provider_slug(): string {
		return $this->provider_slug;
	}

	/** Get the encrypted API key. */
	public function api_key(): string {
		return $this->api_key;
	}

	/** Get the audience reference. */
	public function audience_id(): string {
		return $this->audience_id;
	}

	/** Get the schema version. */
	public function schema_version(): int {
		return $this->schema_version;
	}

	/** Get the last verification timestamp, or null. */
	public function last_verified_at(): ?string {
		return $this->last_verified_at;
	}

	/** Determine whether the last verification succeeded. */
	public function is_verified(): bool {
		return $this->verified;
	}

	/**
	 * Get normalized account details from last verification.
	 *
	 * @return array<string, mixed> Account details map.
	 */
	public function account_details(): array {
		return $this->account_details;
	}

	/**
	 * Return a new instance with updated verification state.
	 *
	 * @param bool                 $verified  Whether verification succeeded.
	 * @param string               $timestamp ISO 8601 timestamp.
	 * @param array<string, mixed> $details   Normalized account details.
	 */
	public function with_verification( bool $verified, string $timestamp, array $details = array() ): self {
		return new self(
			$this->provider_slug,
			$this->api_key,
			$this->audience_id,
			$this->schema_version,
			$timestamp,
			$verified,
			$details
		);
	}

	/**
	 * Return a new instance with a different audience reference.
	 *
	 * @param string $audience_id Audience reference.
	 */
	public function with_audience( string $audience_id ): self {
		return new self(
			$this->provider_slug,
			$this->api_key,
			$audience_id,
			$this->schema_version,
			$this->last_verified_at,
			$this->verified,
			$this->account_details
		);
	}

	/**
	 * Serialize for storage.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'schema_version'   => $this->schema_version,
			'provider_slug'    => $this->provider_slug,
			'api_key'          => $this->api_key,
			'audience_id'      => $this->audience_id,
			'last_verified_at' => $this->last_verified_at,
			'verified'         => $this->verified,
			'account_details'  => $this->account_details,
		);
	}
}
