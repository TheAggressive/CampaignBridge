<?php
/**
 * One provider webhook registered for one audience.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Webhook;

use CampaignBridge\Domain\Campaign\Record_Validation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A webhook the provider holds for one audience, and the secret it signs
 * deliveries with.
 *
 * The signing secret is kept in its encrypted (opaque) form. This record
 * never encrypts or decrypts it; the intake decrypts it only to check one
 * delivery.
 */
final class Webhook_Subscription {
	public const SCHEMA_VERSION = 1;

	private const KEYS = array( 'schema_version', 'provider', 'audience_id', 'remote_id', 'signing_secret', 'created_at' );

	/**
	 * Build the subscription.
	 *
	 * @param string $provider       Provider slug.
	 * @param string $audience_id    The provider's audience ID.
	 * @param string $remote_id      The provider's webhook ID.
	 * @param string $signing_secret Encrypted signing secret.
	 * @param string $created_at     UTC time the provider created the webhook.
	 */
	private function __construct(
		private readonly string $provider,
		private readonly string $audience_id,
		private readonly string $remote_id,
		private readonly string $signing_secret,
		private readonly string $created_at
	) {}

	/**
	 * A webhook the provider just created.
	 *
	 * @param string $provider       Provider slug.
	 * @param string $audience_id    The provider's audience ID.
	 * @param string $remote_id      The provider's webhook ID.
	 * @param string $signing_secret Encrypted signing secret.
	 * @param string $created_at     UTC time the provider created the webhook.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function create( string $provider, string $audience_id, string $remote_id, string $signing_secret, string $created_at ): self {
		return new self(
			Record_Validation::identifier( $provider, 'Provider' ),
			Record_Validation::string( $audience_id, 'Audience ID', 191 ),
			Record_Validation::string( $remote_id, 'Webhook ID', 191 ),
			Record_Validation::string( $signing_secret, 'Signing secret', 4096 ),
			Record_Validation::timestamp( $created_at, 'Created at' )
		);
	}

	/**
	 * Reconstitute a stored subscription.
	 *
	 * @param array<string, mixed> $data Stored subscription.
	 * @throws \InvalidArgumentException When the record is malformed.
	 */
	public static function from_array( array $data ): self {
		Record_Validation::known_keys( $data, self::KEYS );
		if ( self::SCHEMA_VERSION !== ( $data['schema_version'] ?? null ) ) {
			throw new \InvalidArgumentException( 'Unsupported webhook subscription schema version.' );
		}

		return self::create(
			Record_Validation::identifier( $data['provider'] ?? null, 'Provider' ),
			Record_Validation::string( $data['audience_id'] ?? null, 'Audience ID', 191 ),
			Record_Validation::string( $data['remote_id'] ?? null, 'Webhook ID', 191 ),
			Record_Validation::string( $data['signing_secret'] ?? null, 'Signing secret', 4096 ),
			Record_Validation::timestamp( $data['created_at'] ?? null, 'Created at' )
		);
	}

	/**
	 * The provider slug.
	 */
	public function provider(): string {
		return $this->provider;
	}

	/**
	 * The provider's audience ID.
	 */
	public function audience_id(): string {
		return $this->audience_id;
	}

	/**
	 * The provider's webhook ID.
	 */
	public function remote_id(): string {
		return $this->remote_id;
	}

	/**
	 * The encrypted signing secret.
	 */
	public function signing_secret(): string {
		return $this->signing_secret;
	}

	/**
	 * UTC time the provider created the webhook.
	 */
	public function created_at(): string {
		return $this->created_at;
	}

	/**
	 * The stored form.
	 *
	 * @return array<string, int|string>
	 */
	public function to_array(): array {
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'provider'       => $this->provider,
			'audience_id'    => $this->audience_id,
			'remote_id'      => $this->remote_id,
			'signing_secret' => $this->signing_secret,
			'created_at'     => $this->created_at,
		);
	}
}
