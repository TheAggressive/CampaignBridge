<?php
/**
 * Provider-neutral delivery mutation attempt.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Immutable storage value for one remote operation attempt. */
final class Delivery_Attempt {
	public const SCHEMA_VERSION = 1;

	/**
	 * Build the delivery attempt.
	 *
	 * @param string      $id                 Record ID.
	 * @param string      $campaign_id        Campaign ID.
	 * @param string      $operation          Delivery operation.
	 * @param string|null $idempotency_key    Client retry key; the same key replays the first outcome.
	 * @param string      $status             Attempt status.
	 * @param string      $retryability       Whether a new attempt may be made.
	 * @param string|null $remote_correlation The provider's correlation ID, when known.
	 * @param string      $created_at         UTC timestamp of creation, or null for now.
	 * @param string      $updated_at         UTC timestamp of this change.
	 */
	private function __construct(
		private readonly string $id,
		private readonly string $campaign_id,
		private readonly string $operation,
		private readonly ?string $idempotency_key,
		private readonly string $status,
		private readonly string $retryability,
		private readonly ?string $remote_correlation,
		private readonly string $created_at,
		private readonly string $updated_at
	) {}

	/**
	 * Rebuild a delivery attempt from its stored values.
	 *
	 * @param array<string, mixed> $data Persisted values.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function from_array( array $data ): self {
		Record_Validation::known_keys(
			$data,
			array(
				'schema_version',
				'id',
				'campaign_id',
				'operation',
				'idempotency_key',
				'status',
				'retryability',
				'remote_correlation',
				'created_at',
				'updated_at',
			)
		);
		if ( self::SCHEMA_VERSION !== ( $data['schema_version'] ?? null ) ) {
			throw new \InvalidArgumentException( 'Unsupported delivery-attempt schema version.' );
		}

		$operation = $data['operation'] ?? null;
		$status    = $data['status'] ?? null;
		$retry     = $data['retryability'] ?? null;
		if ( ! is_string( $operation ) || ! in_array( $operation, Delivery_Operation::all(), true ) ) {
			throw new \InvalidArgumentException( 'Delivery operation is invalid.' );
		}
		if ( ! is_string( $status ) || ! in_array( $status, Delivery_Attempt_Status::all(), true ) ) {
			throw new \InvalidArgumentException( 'Delivery attempt status is invalid.' );
		}
		if ( ! is_string( $retry ) || ! in_array( $retry, Retryability::all(), true ) ) {
			throw new \InvalidArgumentException( 'Delivery retryability is invalid.' );
		}

		$idempotency_key = $data['idempotency_key'] ?? null;
		if ( null !== $idempotency_key ) {
			$idempotency_key = Record_Validation::string( $idempotency_key, 'Idempotency key', 191 );
		}
		$correlation = $data['remote_correlation'] ?? null;
		if ( null !== $correlation ) {
			$correlation = Record_Validation::string( $correlation, 'Remote correlation', 191 );
		}

		$created_at = Record_Validation::timestamp( $data['created_at'] ?? null, 'Created timestamp' );
		$updated_at = Record_Validation::timestamp( $data['updated_at'] ?? null, 'Updated timestamp' );
		if ( $updated_at < $created_at ) {
			throw new \InvalidArgumentException( 'Delivery-attempt updated timestamp cannot precede creation.' );
		}

		return new self(
			Record_Validation::identifier( $data['id'] ?? null, 'Attempt ID' ),
			Record_Validation::identifier( $data['campaign_id'] ?? null, 'Campaign ID' ),
			$operation,
			$idempotency_key,
			$status,
			$retry,
			$correlation,
			$created_at,
			$updated_at
		);
	}

	/**
	 * The attempt's ID.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * The attempt's campaign ID.
	 */
	public function campaign_id(): string {
		return $this->campaign_id;
	}

	/**
	 * The attempt's operation.
	 */
	public function operation(): string {
		return $this->operation;
	}

	/**
	 * The attempt's idempotency key.
	 */
	public function idempotency_key(): ?string {
		return $this->idempotency_key;
	}

	/**
	 * The attempt's status.
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * The attempt's retryability.
	 */
	public function retryability(): string {
		return $this->retryability;
	}

	/**
	 * The attempt's remote correlation.
	 */
	public function remote_correlation(): ?string {
		return $this->remote_correlation;
	}

	/**
	 * The attempt's created at.
	 */
	public function created_at(): string {
		return $this->created_at;
	}

	/**
	 * The attempt's updated at.
	 */
	public function updated_at(): string {
		return $this->updated_at;
	}

	/**
	 * The attempt's stored values.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'schema_version'     => self::SCHEMA_VERSION,
			'id'                 => $this->id,
			'campaign_id'        => $this->campaign_id,
			'operation'          => $this->operation,
			'idempotency_key'    => $this->idempotency_key,
			'status'             => $this->status,
			'retryability'       => $this->retryability,
			'remote_correlation' => $this->remote_correlation,
			'created_at'         => $this->created_at,
			'updated_at'         => $this->updated_at,
		);
	}
}
