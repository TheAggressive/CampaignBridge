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
	 * @param string               $api_key        Encrypted API key.
	 * @param string               $audience_id    Audience reference (empty when unset).
	 * @param int                  $schema_version Schema version stamp.
	 * @param string|null          $last_verified_at ISO 8601 timestamp or null.
	 * @param bool                 $verified       Whether the last verification succeeded.
	 * @param array<string, mixed> $account_details Normalized account details from last verification.
	 */
	private function __construct(
		private readonly string $provider_slug,
		private readonly string $api_key,
		private readonly string $audience_id,
		private readonly int $schema_version,
		private readonly ?string $last_verified_at,
		private readonly bool $verified,
		private readonly array $account_details
	) {}

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
	 * Tolerant reads: missing or legacy fields are normalized to defaults.
	 * Rejects unknown future schema versions.
	 *
	 * @param array<string, mixed> $data Stored connection data.
	 *
	 * @throws \InvalidArgumentException When the schema version is unsupported.
	 */
	public static function from_array( array $data ): self {
		$version = (int) ( $data['schema_version'] ?? 0 );

		if ( $version > self::SCHEMA_VERSION ) {
			throw new \InvalidArgumentException(
				sprintf( 'Unsupported provider connection schema version %d.', $version )
			);
		}

		return new self(
			(string) ( $data['provider_slug'] ?? '' ),
			(string) ( $data['api_key'] ?? '' ),
			(string) ( $data['audience_id'] ?? '' ),
			$version > 0 ? $version : self::SCHEMA_VERSION,
			isset( $data['last_verified_at'] ) && is_string( $data['last_verified_at'] ) ? $data['last_verified_at'] : null,
			(bool) ( $data['verified'] ?? false ),
			is_array( $data['account_details'] ?? null ) ? $data['account_details'] : array()
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
