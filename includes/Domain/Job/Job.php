<?php
/**
 * One unit of durable background work.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Job;

use CampaignBridge\Domain\Campaign\Record_Validation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable storage value for one background job.
 *
 * The payload is a small flat map of scalars that names what to do, never
 * how to authenticate: keys that look like credentials are refused, so a job
 * row can never hold a secret.
 */
final class Job {
	public const SCHEMA_VERSION = 1;

	private const MAX_PAYLOAD_KEYS  = 16;
	private const MAX_PAYLOAD_BYTES = 2048;
	private const SECRET_KEY        = '/key|secret|token|password|authorization|credential/i';

	/**
	 * Build the job.
	 *
	 * @param string                              $id               Record ID.
	 * @param string                              $type             Job type: a lowercase identifier.
	 * @param string                              $target_type      Kind of record the event is about.
	 * @param string                              $target_id        ID of the record the event is about.
	 * @param string|null                         $dedupe_key       Key held by one active job for the same work.
	 * @param array<string, string|int|bool|null> $payload          What the handler needs; never credentials.
	 * @param string                              $state            Job state.
	 * @param int                                 $attempts         Delivery attempt storage.
	 * @param int                                 $max_attempts     Maximum number of runs.
	 * @param string                              $run_after        Earliest UTC time the job may run.
	 * @param string|null                         $lease_owner      Lease owner, or null.
	 * @param string|null                         $lease_expires_at Lease expiry, or null.
	 * @param string|null                         $last_error       Last error code, or null.
	 * @param string                              $created_at       UTC timestamp of creation, or null for now.
	 * @param string                              $updated_at       UTC timestamp of this change.
	 */
	private function __construct(
		private readonly string $id,
		private readonly string $type,
		private readonly string $target_type,
		private readonly string $target_id,
		private readonly ?string $dedupe_key,
		private readonly array $payload,
		private readonly string $state,
		private readonly int $attempts,
		private readonly int $max_attempts,
		private readonly string $run_after,
		private readonly ?string $lease_owner,
		private readonly ?string $lease_expires_at,
		private readonly ?string $last_error,
		private readonly string $created_at,
		private readonly string $updated_at
	) {}

	/**
	 * A new queued job.
	 *
	 * @param string                              $id           Record ID.
	 * @param string                              $type         Job type: a lowercase identifier.
	 * @param string                              $target_type  Kind of record the event is about.
	 * @param string                              $target_id    ID of the record the event is about.
	 * @param array<string, string|int|bool|null> $payload      What the handler needs.
	 * @param int                                 $max_attempts Maximum number of runs.
	 * @param string                              $run_after    Earliest UTC time the job may run.
	 * @param string                              $now          Current UTC timestamp.
	 * @param string|null                         $dedupe_key   Key held by one active job for the same work.
	 */
	public static function create(
		string $id,
		string $type,
		string $target_type,
		string $target_id,
		array $payload,
		int $max_attempts,
		string $run_after,
		string $now,
		?string $dedupe_key = null
	): self {
		return self::from_array(
			array(
				'schema_version'   => self::SCHEMA_VERSION,
				'id'               => $id,
				'type'             => $type,
				'target_type'      => $target_type,
				'target_id'        => $target_id,
				'dedupe_key'       => $dedupe_key,
				'payload'          => $payload,
				'state'            => Job_State::QUEUED,
				'attempts'         => 0,
				'max_attempts'     => $max_attempts,
				'run_after'        => $run_after,
				'lease_owner'      => null,
				'lease_expires_at' => null,
				'last_error'       => null,
				'created_at'       => $now,
				'updated_at'       => $now,
			)
		);
	}

	/**
	 * Rebuild a job from its stored values.
	 *
	 * @param array<string, mixed> $data Persisted values.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function from_array( array $data ): self {
		Record_Validation::known_keys(
			$data,
			array( 'schema_version', 'id', 'type', 'target_type', 'target_id', 'dedupe_key', 'payload', 'state', 'attempts', 'max_attempts', 'run_after', 'lease_owner', 'lease_expires_at', 'last_error', 'created_at', 'updated_at' )
		);
		if ( self::SCHEMA_VERSION !== ( $data['schema_version'] ?? null ) ) {
			throw new \InvalidArgumentException( 'Unsupported job schema version.' );
		}

		$state = $data['state'] ?? null;
		if ( ! is_string( $state ) || ! in_array( $state, Job_State::all(), true ) ) {
			throw new \InvalidArgumentException( 'Job state is invalid.' );
		}
		$attempts     = $data['attempts'] ?? null;
		$max_attempts = $data['max_attempts'] ?? null;
		if ( ! is_int( $max_attempts ) || $max_attempts < 1 || $max_attempts > 50 ) {
			throw new \InvalidArgumentException( 'Job attempts must allow between 1 and 50 runs.' );
		}
		if ( ! is_int( $attempts ) || $attempts < 0 || $attempts > $max_attempts ) {
			throw new \InvalidArgumentException( 'Job attempt count is invalid.' );
		}

		$lease_owner = $data['lease_owner'] ?? null;
		$lease_until = $data['lease_expires_at'] ?? null;
		if ( ( null === $lease_owner ) !== ( null === $lease_until ) ) {
			throw new \InvalidArgumentException( 'A job lease needs both an owner and an expiry.' );
		}
		if ( Job_State::CLAIMED === $state && null === $lease_owner ) {
			throw new \InvalidArgumentException( 'A claimed job must hold a lease.' );
		}
		if ( Job_State::CLAIMED !== $state && null !== $lease_owner ) {
			throw new \InvalidArgumentException( 'Only a claimed job holds a lease.' );
		}

		$dedupe_key = $data['dedupe_key'] ?? null;
		if ( null !== $dedupe_key ) {
			$dedupe_key = Record_Validation::string( $dedupe_key, 'Job dedupe key', 191 );
		}
		$last_error = $data['last_error'] ?? null;
		if ( null !== $last_error ) {
			$last_error = Record_Validation::identifier( $last_error, 'Job error code' );
		}

		$created_at = Record_Validation::timestamp( $data['created_at'] ?? null, 'Job created timestamp' );
		$updated_at = Record_Validation::timestamp( $data['updated_at'] ?? null, 'Job updated timestamp' );

		return new self(
			Record_Validation::identifier( $data['id'] ?? null, 'Job ID' ),
			Record_Validation::identifier( $data['type'] ?? null, 'Job type' ),
			Record_Validation::identifier( $data['target_type'] ?? null, 'Job target type' ),
			Record_Validation::string( $data['target_id'] ?? null, 'Job target ID', 64 ),
			$dedupe_key,
			self::valid_payload( $data['payload'] ?? null ),
			$state,
			$attempts,
			$max_attempts,
			Record_Validation::timestamp( $data['run_after'] ?? null, 'Job run-after timestamp' ),
			null === $lease_owner ? null : Record_Validation::identifier( $lease_owner, 'Job lease owner' ),
			null === $lease_until ? null : Record_Validation::timestamp( $lease_until, 'Job lease expiry' ),
			$last_error,
			$created_at,
			max( $created_at, $updated_at )
		);
	}

	/**
	 * Validate a flat, bounded, credential-free payload.
	 *
	 * @param mixed $value Value to validate.
	 * @return array<string, string|int|bool|null>
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	private static function valid_payload( mixed $value ): array {
		if ( ! is_array( $value ) || ( array() !== $value && array_is_list( $value ) ) || count( $value ) > self::MAX_PAYLOAD_KEYS ) {
			throw new \InvalidArgumentException( 'Job payload must be a small named map.' );
		}
		foreach ( $value as $key => $item ) {
			if ( ! is_string( $key ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{0,63}$/', $key ) ) {
				throw new \InvalidArgumentException( 'Job payload keys must be lowercase identifiers.' );
			}
			if ( 1 === preg_match( self::SECRET_KEY, $key ) ) {
				throw new \InvalidArgumentException( 'Job payloads must not carry credentials.' );
			}
			if ( ! ( is_string( $item ) || is_int( $item ) || is_bool( $item ) || null === $item ) || ( is_string( $item ) && strlen( $item ) > 191 ) ) {
				throw new \InvalidArgumentException( 'Job payload values must be short scalars.' );
			}
		}
		$encoded = json_encode( $value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure domain validation requires throwing, deterministic JSON.
		if ( strlen( $encoded ) > self::MAX_PAYLOAD_BYTES ) {
			throw new \InvalidArgumentException( 'Job payload is too large.' );
		}

		return $value;
	}

	/**
	 * The job's stored values.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'schema_version'   => self::SCHEMA_VERSION,
			'id'               => $this->id,
			'type'             => $this->type,
			'target_type'      => $this->target_type,
			'target_id'        => $this->target_id,
			'dedupe_key'       => $this->dedupe_key,
			'payload'          => $this->payload,
			'state'            => $this->state,
			'attempts'         => $this->attempts,
			'max_attempts'     => $this->max_attempts,
			'run_after'        => $this->run_after,
			'lease_owner'      => $this->lease_owner,
			'lease_expires_at' => $this->lease_expires_at,
			'last_error'       => $this->last_error,
			'created_at'       => $this->created_at,
			'updated_at'       => $this->updated_at,
		);
	}

	/**
	 * The job's ID.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * The job's type.
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * The job's target type.
	 */
	public function target_type(): string {
		return $this->target_type;
	}

	/**
	 * The job's target ID.
	 */
	public function target_id(): string {
		return $this->target_id;
	}

	/**
	 * The job's dedupe key.
	 */
	public function dedupe_key(): ?string {
		return $this->dedupe_key;
	}

	/**
	 * The job's payload.
	 *
	 * @return array<string, string|int|bool|null>
	 */
	public function payload(): array {
		return $this->payload;
	}

	/**
	 * The job's state.
	 */
	public function state(): string {
		return $this->state;
	}

	/**
	 * The job's attempts.
	 */
	public function attempts(): int {
		return $this->attempts;
	}

	/**
	 * The job's max attempts.
	 */
	public function max_attempts(): int {
		return $this->max_attempts;
	}

	/**
	 * The job's run after.
	 */
	public function run_after(): string {
		return $this->run_after;
	}

	/**
	 * The job's lease owner.
	 */
	public function lease_owner(): ?string {
		return $this->lease_owner;
	}

	/**
	 * The job's lease expires at.
	 */
	public function lease_expires_at(): ?string {
		return $this->lease_expires_at;
	}

	/**
	 * The job's last error.
	 */
	public function last_error(): ?string {
		return $this->last_error;
	}

	/**
	 * The job's created at.
	 */
	public function created_at(): string {
		return $this->created_at;
	}

	/**
	 * The job's updated at.
	 */
	public function updated_at(): string {
		return $this->updated_at;
	}

	/** Whether another run may follow a failed attempt. */
	public function has_attempts_left(): bool {
		return $this->attempts < $this->max_attempts;
	}
}
