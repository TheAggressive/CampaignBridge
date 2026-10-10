<?php
/**
 * Provider-neutral remote campaign identity.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Immutable normalized view of one provider-owned campaign reference. */
final class Remote_Campaign_Reference {
	public const SCHEMA_VERSION = 1;

	/**
	 * Build the remote campaign reference.
	 *
	 * @param string      $campaign_id    Campaign ID.
	 * @param string      $provider       Provider slug.
	 * @param string      $remote_id      The provider's campaign ID.
	 * @param string      $observed_state State the provider reported.
	 * @param string|null $cursor         Provider pagination cursor, when there is one.
	 * @param string      $observed_at    UTC time of the observation.
	 * @param string|null $reconciled_at  UTC time of the last reconciliation, or null.
	 */
	private function __construct(
		private readonly string $campaign_id,
		private readonly string $provider,
		private readonly string $remote_id,
		private readonly string $observed_state,
		private readonly ?string $cursor,
		private readonly string $observed_at,
		private readonly ?string $reconciled_at
	) {}

	/**
	 * Rebuild a remote reference from its stored values.
	 *
	 * @param array<string, mixed> $data Persisted values.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function from_array( array $data ): self {
		Record_Validation::known_keys(
			$data,
			array( 'schema_version', 'campaign_id', 'provider', 'remote_id', 'observed_state', 'cursor', 'observed_at', 'reconciled_at' )
		);
		if ( self::SCHEMA_VERSION !== ( $data['schema_version'] ?? null ) ) {
			throw new \InvalidArgumentException( 'Unsupported remote-reference schema version.' );
		}

		$state = Record_Validation::string( $data['observed_state'] ?? null, 'Observed state', 32 );
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,31}$/', $state ) ) {
			throw new \InvalidArgumentException( 'Observed state must be normalized.' );
		}

		$cursor = $data['cursor'] ?? null;
		if ( null !== $cursor ) {
			$cursor = Record_Validation::string( $cursor, 'Provider cursor', 191 );
		}
		$observed_at   = Record_Validation::timestamp( $data['observed_at'] ?? null, 'Observed timestamp' );
		$reconciled_at = $data['reconciled_at'] ?? null;
		if ( null !== $reconciled_at ) {
			$reconciled_at = Record_Validation::timestamp( $reconciled_at, 'Reconciled timestamp' );
			if ( $reconciled_at < $observed_at ) {
				throw new \InvalidArgumentException( 'Remote reconciliation cannot precede its observation.' );
			}
		}

		return new self(
			Record_Validation::identifier( $data['campaign_id'] ?? null, 'Campaign ID' ),
			Record_Validation::identifier( $data['provider'] ?? null, 'Provider' ),
			Record_Validation::string( $data['remote_id'] ?? null, 'Remote ID', 191 ),
			$state,
			$cursor,
			$observed_at,
			$reconciled_at
		);
	}

	/**
	 * The reference's campaign ID.
	 */
	public function campaign_id(): string {
		return $this->campaign_id;
	}

	/**
	 * The reference's provider.
	 */
	public function provider(): string {
		return $this->provider;
	}

	/**
	 * The reference's remote ID.
	 */
	public function remote_id(): string {
		return $this->remote_id;
	}

	/**
	 * The reference's observed state.
	 */
	public function observed_state(): string {
		return $this->observed_state;
	}

	/**
	 * The reference's cursor.
	 */
	public function cursor(): ?string {
		return $this->cursor;
	}

	/**
	 * The reference's observed at.
	 */
	public function observed_at(): string {
		return $this->observed_at;
	}

	/**
	 * The reference's reconciled at.
	 */
	public function reconciled_at(): ?string {
		return $this->reconciled_at;
	}

	/**
	 * The reference's stored values.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'campaign_id'    => $this->campaign_id,
			'provider'       => $this->provider,
			'remote_id'      => $this->remote_id,
			'observed_state' => $this->observed_state,
			'cursor'         => $this->cursor,
			'observed_at'    => $this->observed_at,
			'reconciled_at'  => $this->reconciled_at,
		);
	}
}
