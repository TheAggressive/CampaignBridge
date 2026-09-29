<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed immutable persistence values use explicit signatures and class-level invariant documentation.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Fail-closed validation exceptions are part of this value contract.
/**
 * Provider-neutral durable campaign record.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Immutable campaign persistence value. */
final class Campaign {
	public const SCHEMA_VERSION = 1;

	private function __construct(
		private readonly string $id,
		private readonly string $state,
		private readonly int $version,
		private readonly int $owner_user_id,
		private readonly int $template_id,
		private readonly ?string $provider,
		private readonly ?string $audience_reference,
		private readonly ?string $active_snapshot_id,
		private readonly string $created_at,
		private readonly string $updated_at
	) {}

	/** Create a new draft campaign. */
	public static function create(
		string $id,
		int $owner_user_id,
		int $template_id,
		?string $provider,
		?string $audience_reference,
		string $created_at
	): self {
		return self::from_array(
			array(
				'schema_version'     => self::SCHEMA_VERSION,
				'id'                 => $id,
				'state'              => Campaign_State::DRAFT,
				'version'            => 1,
				'owner_user_id'      => $owner_user_id,
				'template_id'        => $template_id,
				'provider'           => $provider,
				'audience_reference' => $audience_reference,
				'active_snapshot_id' => null,
				'created_at'         => $created_at,
				'updated_at'         => $created_at,
			)
		);
	}

	/**
	 * Restore a strictly versioned campaign representation.
	 *
	 * @param array<string, mixed> $data Persisted values.
	 */
	public static function from_array( array $data ): self {
		Record_Validation::known_keys(
			$data,
			array(
				'schema_version',
				'id',
				'state',
				'version',
				'owner_user_id',
				'template_id',
				'provider',
				'audience_reference',
				'active_snapshot_id',
				'created_at',
				'updated_at',
			)
		);
		if ( self::SCHEMA_VERSION !== ( $data['schema_version'] ?? null ) ) {
			throw new \InvalidArgumentException( 'Unsupported campaign schema version.' );
		}

		$state = $data['state'] ?? null;
		if ( ! is_string( $state ) || ! Campaign_State::is_valid( $state ) ) {
			throw new \InvalidArgumentException( 'Campaign state is invalid.' );
		}

		$version       = $data['version'] ?? null;
		$owner_user_id = $data['owner_user_id'] ?? null;
		$template_id   = $data['template_id'] ?? null;
		if ( ! is_int( $version ) || 1 > $version || ! is_int( $owner_user_id ) || 1 > $owner_user_id || ! is_int( $template_id ) || 1 > $template_id ) {
			throw new \InvalidArgumentException( 'Campaign version, owner, and template identifiers must be positive integers.' );
		}

		$provider = $data['provider'] ?? null;
		if ( null !== $provider ) {
			$provider = Record_Validation::identifier( $provider, 'Provider' );
		}
		$audience = $data['audience_reference'] ?? null;
		if ( null !== $audience ) {
			$audience = Record_Validation::string( $audience, 'Audience reference', 191 );
		}
		$snapshot_id = $data['active_snapshot_id'] ?? null;
		if ( null !== $snapshot_id ) {
			$snapshot_id = Record_Validation::identifier( $snapshot_id, 'Active snapshot ID' );
		}

		$created_at = Record_Validation::timestamp( $data['created_at'] ?? null, 'Created timestamp' );
		$updated_at = Record_Validation::timestamp( $data['updated_at'] ?? null, 'Updated timestamp' );
		if ( $updated_at < $created_at ) {
			throw new \InvalidArgumentException( 'Campaign updated timestamp cannot precede creation.' );
		}

		return new self(
			Record_Validation::identifier( $data['id'] ?? null, 'Campaign ID' ),
			$state,
			$version,
			$owner_user_id,
			$template_id,
			$provider,
			$audience,
			$snapshot_id,
			$created_at,
			$updated_at
		);
	}

	public function id(): string {
		return $this->id;
	}

	public function state(): string {
		return $this->state;
	}

	public function version(): int {
		return $this->version;
	}

	public function owner_user_id(): int {
		return $this->owner_user_id;
	}

	public function template_id(): int {
		return $this->template_id;
	}

	public function provider(): ?string {
		return $this->provider;
	}

	public function audience_reference(): ?string {
		return $this->audience_reference;
	}

	public function active_snapshot_id(): ?string {
		return $this->active_snapshot_id;
	}

	public function created_at(): string {
		return $this->created_at;
	}

	public function updated_at(): string {
		return $this->updated_at;
	}

	/** Change the selected template and invalidate any previously frozen artifact. */
	public function edit_template( int $template_id, string $updated_at ): self {
		if ( 1 > $template_id ) {
			throw new \InvalidArgumentException( 'Campaign template identifier must be positive.' );
		}

		return $this->replacement(
			Campaign_State_Machine::after_artifact_invalidation( $this->state ),
			$template_id,
			$this->provider,
			$this->audience_reference,
			null,
			$updated_at
		);
	}

	/** Select normalized provider/audience references without provider traffic or PII. */
	public function select_audience( ?string $provider, ?string $audience_reference, string $updated_at ): self {
		return $this->replacement(
			Campaign_State_Machine::after_approval_invalidation( $this->state ),
			$this->template_id,
			$provider,
			$audience_reference,
			$this->active_snapshot_id,
			$updated_at
		);
	}

	/** Select one immutable snapshot, revoking approval when it replaces an approved artifact. */
	public function select_snapshot( string $snapshot_id, string $updated_at ): self {
		return $this->replacement(
			Campaign_State_Machine::after_approval_invalidation( $this->state ),
			$this->template_id,
			$this->provider,
			$this->audience_reference,
			$snapshot_id,
			$updated_at
		);
	}

	/** Apply one legal lifecycle transition. */
	public function transition_to( string $state, string $updated_at ): self {
		Campaign_State_Machine::assert_transition( $this->state, $state );

		return $this->replacement(
			$state,
			$this->template_id,
			$this->provider,
			$this->audience_reference,
			$this->active_snapshot_id,
			$updated_at
		);
	}

	/** Build the next immutable version after validating the complete record. */
	private function replacement(
		string $state,
		int $template_id,
		?string $provider,
		?string $audience_reference,
		?string $active_snapshot_id,
		string $updated_at
	): self {
		return self::from_array(
			array(
				'schema_version'     => self::SCHEMA_VERSION,
				'id'                 => $this->id,
				'state'              => $state,
				'version'            => $this->version + 1,
				'owner_user_id'      => $this->owner_user_id,
				'template_id'        => $template_id,
				'provider'           => $provider,
				'audience_reference' => $audience_reference,
				'active_snapshot_id' => $active_snapshot_id,
				'created_at'         => $this->created_at,
				'updated_at'         => $updated_at,
			)
		);
	}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return array(
			'schema_version'     => self::SCHEMA_VERSION,
			'id'                 => $this->id,
			'state'              => $this->state,
			'version'            => $this->version,
			'owner_user_id'      => $this->owner_user_id,
			'template_id'        => $this->template_id,
			'provider'           => $this->provider,
			'audience_reference' => $this->audience_reference,
			'active_snapshot_id' => $this->active_snapshot_id,
			'created_at'         => $this->created_at,
			'updated_at'         => $this->updated_at,
		);
	}
}
