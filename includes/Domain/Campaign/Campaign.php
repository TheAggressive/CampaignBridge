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

	/** States in which a campaign may carry a delivery time. */
	private const SCHEDULE_STATES = array(
		Campaign_State::SCHEDULED,
		Campaign_State::SENDING,
		Campaign_State::SENT,
		Campaign_State::FAILED,
		Campaign_State::CANCELLED,
		Campaign_State::UNKNOWN,
	);

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
		private readonly string $updated_at,
		private readonly ?string $scheduled_for,
		private readonly ?int $approved_by_user_id
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
				'scheduled_for',
				'approved_by_user_id',
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
		$scheduled_for = $data['scheduled_for'] ?? null;
		if ( null !== $scheduled_for ) {
			$scheduled_for = Record_Validation::timestamp( $scheduled_for, 'Scheduled delivery time' );
			if ( ! in_array( $state, self::SCHEDULE_STATES, true ) ) {
				throw new \InvalidArgumentException( 'Only a campaign at or past scheduling may carry a delivery time.' );
			}
		}
		$approved_by = $data['approved_by_user_id'] ?? null;
		if ( null !== $approved_by ) {
			if ( ! is_int( $approved_by ) || 1 > $approved_by ) {
				throw new \InvalidArgumentException( 'Campaign approver must be a positive user ID.' );
			}
			if ( ! self::retains_approval( $state ) ) {
				throw new \InvalidArgumentException( 'Only an approved campaign may record its approver.' );
			}
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
			$updated_at,
			$scheduled_for,
			$approved_by
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

	/** The user whose approval the campaign carries; null before approval or for unrecorded legacy approvals. */
	public function approved_by_user_id(): ?int {
		return $this->approved_by_user_id;
	}

	/** The UTC time the campaign was scheduled to send, if it was scheduled. */
	public function scheduled_for(): ?string {
		return $this->scheduled_for;
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

	/**
	 * Apply one legal lifecycle transition.
	 *
	 * A delivery time survives into states that follow scheduling and is
	 * cleared when the campaign returns to an unscheduled state.
	 */
	public function transition_to( string $state, string $updated_at ): self {
		Campaign_State_Machine::assert_transition( $this->state, $state );

		return $this->replacement(
			$state,
			$this->template_id,
			$this->provider,
			$this->audience_reference,
			$this->active_snapshot_id,
			$updated_at,
			in_array( $state, self::SCHEDULE_STATES, true ) ? $this->scheduled_for : null
		);
	}

	/** Approve the reviewed campaign and record who approved it. */
	public function approve_by( int $user_id, string $updated_at ): self {
		Campaign_State_Machine::assert_transition( $this->state, Campaign_State::APPROVED );
		if ( 1 > $user_id ) {
			throw new \InvalidArgumentException( 'Campaign approver must be a positive user ID.' );
		}

		return $this->replacement(
			Campaign_State::APPROVED,
			$this->template_id,
			$this->provider,
			$this->audience_reference,
			$this->active_snapshot_id,
			$updated_at,
			null,
			$user_id
		);
	}

	/** Move a provider draft to `scheduled` for one UTC delivery time. */
	public function schedule_for( string $scheduled_for, string $updated_at ): self {
		Campaign_State_Machine::assert_transition( $this->state, Campaign_State::SCHEDULED );

		return $this->replacement(
			Campaign_State::SCHEDULED,
			$this->template_id,
			$this->provider,
			$this->audience_reference,
			$this->active_snapshot_id,
			$updated_at,
			$scheduled_for
		);
	}

	/**
	 * Claim the next version without changing anything else.
	 *
	 * A remote delivery operation claims the campaign before contacting the
	 * provider, so concurrent requests holding the same expected version are
	 * refused instead of reaching the provider twice.
	 */
	public function claim( string $updated_at ): self {
		return $this->replacement(
			$this->state,
			$this->template_id,
			$this->provider,
			$this->audience_reference,
			$this->active_snapshot_id,
			$updated_at,
			$this->scheduled_for
		);
	}

	/** Build the next immutable version after validating the complete record. */
	private function replacement(
		string $state,
		int $template_id,
		?string $provider,
		?string $audience_reference,
		?string $active_snapshot_id,
		string $updated_at,
		?string $scheduled_for = null,
		?int $approved_by_user_id = null
	): self {
		return self::from_array(
			array(
				'schema_version'      => self::SCHEMA_VERSION,
				'id'                  => $this->id,
				'state'               => $state,
				'version'             => $this->version + 1,
				'owner_user_id'       => $this->owner_user_id,
				'template_id'         => $template_id,
				'provider'            => $provider,
				'audience_reference'  => $audience_reference,
				'active_snapshot_id'  => $active_snapshot_id,
				'created_at'          => $this->created_at,
				'updated_at'          => $updated_at,
				'scheduled_for'       => $scheduled_for,
				'approved_by_user_id' => self::retains_approval( $state ) ? ( $approved_by_user_id ?? $this->approved_by_user_id ) : null,
			)
		);
	}

	/**
	 * Whether a state still carries the approval it passed through.
	 *
	 * Returning to draft or review invalidates approval, so it also clears
	 * the recorded approver.
	 */
	private static function retains_approval( string $state ): bool {
		return ! in_array( $state, array( Campaign_State::DRAFT, Campaign_State::READY_FOR_REVIEW ), true );
	}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return array(
			'schema_version'      => self::SCHEMA_VERSION,
			'id'                  => $this->id,
			'state'               => $this->state,
			'version'             => $this->version,
			'owner_user_id'       => $this->owner_user_id,
			'template_id'         => $this->template_id,
			'provider'            => $this->provider,
			'audience_reference'  => $this->audience_reference,
			'active_snapshot_id'  => $this->active_snapshot_id,
			'created_at'          => $this->created_at,
			'updated_at'          => $this->updated_at,
			'scheduled_for'       => $this->scheduled_for,
			'approved_by_user_id' => $this->approved_by_user_id,
		);
	}
}
