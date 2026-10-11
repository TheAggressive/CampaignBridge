<?php
/**
 * Idempotent remote draft handoff for approved campaigns.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Workflow\Lock\Lock_Manager;
use CampaignBridge\Domain\Campaign\Audit_Context;
use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Audit_Event_Source;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_Snapshot_Source;
use CampaignBridge\Domain\Campaign\Campaign_Source;
use CampaignBridge\Domain\Campaign\Campaign_State;
use CampaignBridge\Domain\Campaign\Campaign_Transaction;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Domain\Campaign\Delivery_Attempt_Source;
use CampaignBridge\Domain\Campaign\Delivery_Attempt_Status;
use CampaignBridge\Domain\Campaign\Delivery_Operation;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference_Source;
use CampaignBridge\Domain\Campaign\Retryability;
use CampaignBridge\Domain\Provider\Action_Outcome;
use CampaignBridge\Domain\Provider\Draft_Content;
use CampaignBridge\Domain\Provider\Draft_Outcome;
use CampaignBridge\Domain\Provider\Provider_Capabilities;
use CampaignBridge\Domain\Provider\Provider_Draft_Gateway;
use CampaignBridge\Domain\Provider\Provider_Operation;
use CampaignBridge\Domain\Provider\Provider_Token_Mapper;
use CampaignBridge\Workflow\Provider\Provider_Discovery_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates exactly one remote draft from an approved campaign artifact.
 *
 * Protocol:
 *
 * 1. One draft per campaign and provider. An existing remote reference is a
 *    replay; nothing new is created.
 * 2. Write-ahead. A `pending` attempt is stored before the remote create, and
 *    the remote draft carries the attempt ID so reconciliation can find it.
 * 3. Never retry blindly. While any create attempt is `pending` or `unknown`,
 *    further creates are refused with `reconciliation_required`.
 * 4. Outcomes are recorded as known: created, definitely failed, or unknown.
 * 5. A draft whose content upload failed is kept as `content_pending`. The
 *    next request re-asserts the approved audience, envelope, and content
 *    with idempotent updates; no second draft is created.
 * 6. An existing draft is never trusted as-is. Approval may have been
 *    revoked and the audience or snapshot changed since it was created, so
 *    it is re-asserted before the campaign relies on it.
 *
 * Content comes only from the approved snapshot and its frozen envelope. A
 * draft is never scheduled or sent here.
 */
final class Campaign_Draft_Handoff {
	/** The remote draft exists with the approved content. */
	public const OBSERVED_DRAFT = 'draft';

	/** The remote draft exists but its content upload has not completed. */
	public const OBSERVED_CONTENT_PENDING = 'content_pending';

	private const ACTION = 'campaign_provider_draft';

	/**
	 * Build the campaign draft handoff.
	 *
	 * @param Campaign_Source                  $campaigns    Campaign storage.
	 * @param Campaign_Snapshot_Source         $snapshots    Snapshot storage.
	 * @param Remote_Campaign_Reference_Source $references   Remote reference storage.
	 * @param Delivery_Attempt_Source          $attempts     Delivery attempt storage.
	 * @param Audit_Event_Source               $audits       Audit event storage.
	 * @param Campaign_Transaction             $transaction  Runs writes atomically.
	 * @param Campaign_Id_Generator            $ids          Identifier generator.
	 * @param Campaign_Clock                   $clock        Source of the current time.
	 * @param Provider_Draft_Gateway           $gateway      Provider delivery gateway.
	 * @param Provider_Capabilities            $capabilities What the provider supports.
	 * @param Provider_Token_Mapper            $tokens       Personalization token mapper.
	 * @param Provider_Discovery_Service       $discovery    Provider discovery service.
	 * @param Lock_Manager|null                $locks        Lock manager; null runs without locking.
	 */
	public function __construct(
		private readonly Campaign_Source $campaigns,
		private readonly Campaign_Snapshot_Source $snapshots,
		private readonly Remote_Campaign_Reference_Source $references,
		private readonly Delivery_Attempt_Source $attempts,
		private readonly Audit_Event_Source $audits,
		private readonly Campaign_Transaction $transaction,
		private readonly Campaign_Id_Generator $ids,
		private readonly Campaign_Clock $clock,
		private readonly Provider_Draft_Gateway $gateway,
		private readonly Provider_Capabilities $capabilities,
		private readonly Provider_Token_Mapper $tokens,
		private readonly Provider_Discovery_Service $discovery,
		private readonly ?Lock_Manager $locks = null
	) {}

	/**
	 * Create, resume, or replay the remote draft for an approved campaign.
	 *
	 * @param Campaign_Actor       $actor            Who is acting, with their resolved campaign authority.
	 * @param string               $campaign_id      Campaign ID.
	 * @param int                  $expected_version The version the caller last read.
	 * @param string               $idempotency_key  Client retry key; the same key replays the first outcome.
	 * @param array<string, mixed> $settings         Decrypted provider settings for this call only.
	 */
	public function create_draft( Campaign_Actor $actor, string $campaign_id, int $expected_version, string $idempotency_key, array $settings ): Campaign_Draft_Result {
		if ( null === $this->locks ) {
			return $this->create_draft_unlocked( $actor, $campaign_id, $expected_version, $idempotency_key, $settings );
		}

		return $this->locks->campaign(
			$campaign_id,
			Delivery_Operation::CREATE_DRAFT,
			fn (): Campaign_Draft_Result => $this->create_draft_unlocked( $actor, $campaign_id, $expected_version, $idempotency_key, $settings ),
			fn ( Campaign_Workflow_Error $locked ): Campaign_Draft_Result => Campaign_Draft_Result::failure( $locked )
		);
	}

	/**
	 * The operation itself, run while the campaign lock is held.
	 *
	 * @param Campaign_Actor       $actor            Who is acting, with their resolved campaign authority.
	 * @param string               $campaign_id      Campaign ID.
	 * @param int                  $expected_version The version the caller last read.
	 * @param string               $idempotency_key  Client retry key; the same key replays the first outcome.
	 * @param array<string, mixed> $settings         Decrypted provider settings for this call only.
	 */
	private function create_draft_unlocked( Campaign_Actor $actor, string $campaign_id, int $expected_version, string $idempotency_key, array $settings ): Campaign_Draft_Result {
		$campaign = $this->campaigns->get( $campaign_id );
		if ( null === $campaign ) {
			return $this->refuse( Campaign_Workflow_Error::NOT_FOUND, 'Campaign was not found.', $actor, $campaign_id );
		}
		if ( ! $actor->can_approve( $campaign ) ) {
			return $this->refuse( Campaign_Workflow_Error::FORBIDDEN, 'Campaign operation is not allowed.', $actor, $campaign_id, $campaign, 'denied' );
		}
		if ( '' === $idempotency_key || 191 < strlen( $idempotency_key ) ) {
			return $this->refuse( Campaign_Workflow_Error::INVALID_INPUT, 'A bounded idempotency key is required.', $actor, $campaign_id, $campaign );
		}
		$provider = $this->gateway->slug();
		$audience = $campaign->audience_reference();
		if ( ! $this->capabilities->supports( Provider_Operation::CREATE_DRAFT ) || $provider !== $campaign->provider() || null === $audience ) {
			return $this->refuse( Campaign_Workflow_Error::INVALID_INPUT, 'The campaign must target this provider and an audience before a draft can be created.', $actor, $campaign_id, $campaign );
		}

		$reference = $this->references->get( $campaign_id, $provider );
		if ( null !== $reference && self::OBSERVED_DRAFT === $reference->observed_state() && Campaign_State::PROVIDER_DRAFT === $campaign->state() ) {
			return Campaign_Draft_Result::success( $campaign, $reference, null, true );
		}
		if ( null === $reference ) {
			$blocked = $this->unresolved_attempt( $campaign_id, $idempotency_key );
			if ( null !== $blocked ) {
				return $this->refuse( $blocked[0], $blocked[1], $actor, $campaign_id, $campaign, 'failure', $blocked[2] );
			}
		}
		if ( Campaign_State::APPROVED !== $campaign->state() ) {
			return $this->refuse( Campaign_Workflow_Error::INVALID_STATE, 'Only an approved campaign can become a provider draft.', $actor, $campaign_id, $campaign );
		}
		if ( $campaign->version() !== $expected_version ) {
			return $this->refuse( Campaign_Workflow_Error::CONFLICT, 'Campaign version is stale.', $actor, $campaign_id, $campaign );
		}

		$attempt_id = null === $reference ? $this->ids->generate( 'attempt' ) : null;
		$content    = $this->content( $campaign, $audience, $settings, $attempt_id ?? $reference->remote_id() );
		if ( $content instanceof Campaign_Workflow_Error ) {
			return $this->refuse( $content->code(), $content->message(), $actor, $campaign_id, $campaign );
		}

		if ( null !== $reference ) {
			// The draft exists from an earlier request: re-assert the approved audience, envelope, and content.
			$outcome = $this->gateway->sync_draft( $settings, $reference->remote_id(), $content );

			return Action_Outcome::ACCEPTED === $outcome->status()
				? $this->finalize( $actor, $campaign, $expected_version, $reference->remote_id(), null, $reference )
				: $this->provider_failure( $actor, $campaign, $reference, null, $outcome->error(), 'The provider draft exists but could not be brought in line with the approved campaign. Repeat the request to resume.' );
		}

		$attempt = $this->attempt( (string) $attempt_id, $campaign_id, $idempotency_key, Delivery_Attempt_Status::PENDING, Retryability::UNKNOWN, null, null );
		if ( ! $this->attempts->add( $attempt ) ) {
			// A concurrent request claimed this key first; its outcome is not ours to assume.
			return $this->refuse( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'Another draft request is already in progress for this campaign.', $actor, $campaign_id, $campaign );
		}

		$outcome = $this->gateway->create_draft( $settings, $content );

		return match ( $outcome->status() ) {
			Draft_Outcome::CREATED         => $this->finalize( $actor, $campaign, $expected_version, (string) $outcome->remote_id(), $attempt, null ),
			Draft_Outcome::CONTENT_PENDING => $this->content_pending( $actor, $campaign, $attempt, (string) $outcome->remote_id(), $outcome->error() ),
			Draft_Outcome::FAILED          => $this->definite_failure( $actor, $campaign, $attempt, $outcome->error() ),
			default                        => $this->ambiguous( $actor, $campaign, $attempt, $outcome->error() ),
		};
	}

	/**
	 * Build provider content from the verified, approved snapshot only.
	 *
	 * @param Campaign             $campaign       The campaign as read.
	 * @param string               $audience       Audience reference the draft targets.
	 * @param array<string, mixed> $settings       Decrypted provider settings.
	 * @param string               $correlation_id The provider's correlation ID, when known.
	 */
	private function content( Campaign $campaign, string $audience, array $settings, string $correlation_id ): Draft_Content|Campaign_Workflow_Error {
		$snapshot = ( new Campaign_Snapshot_Verifier( $this->snapshots ) )->verify( $campaign );

		return $snapshot instanceof Campaign_Workflow_Error
			? $snapshot
			: ( new Campaign_Draft_Content_Builder( $this->tokens, $this->discovery ) )->build( $snapshot, $audience, $settings, $correlation_id );
	}

	/**
	 * Refuse a new create while an earlier one is unresolved or already settled for this key.
	 *
	 * @param string $campaign_id     Campaign ID.
	 * @param string $idempotency_key Client retry key; the same key replays the first outcome.
	 * @return array{0: string, 1: string, 2: Delivery_Attempt}|null
	 */
	private function unresolved_attempt( string $campaign_id, string $idempotency_key ): ?array {
		$candidates = $this->attempts->for_campaign( $campaign_id, 50 );
		$same_key   = $this->attempts->find_idempotency( $campaign_id, Delivery_Operation::CREATE_DRAFT, $idempotency_key );
		if ( null !== $same_key ) {
			$candidates[] = $same_key;
		}
		foreach ( $candidates as $attempt ) {
			if ( Delivery_Operation::CREATE_DRAFT !== $attempt->operation() ) {
				continue;
			}
			if ( in_array( $attempt->status(), array( Delivery_Attempt_Status::PENDING, Delivery_Attempt_Status::UNKNOWN, Delivery_Attempt_Status::SUCCEEDED ), true ) ) {
				return array( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'An earlier draft request has an unconfirmed outcome and must be reconciled before another draft is created.', $attempt );
			}
		}

		return null === $same_key
			? null
			: array( Campaign_Workflow_Error::PROVIDER_FAILED, 'This idempotency key belongs to a draft request that failed. Use a new key to try again.', $same_key );
	}

	/**
	 * Record the created draft and move the campaign to provider_draft.
	 *
	 * @param Campaign_Actor                 $actor            Who is acting, with their resolved campaign authority.
	 * @param Campaign                       $campaign         The campaign as read.
	 * @param int                            $expected_version The version the caller last read.
	 * @param string                         $remote_id        The provider's campaign ID.
	 * @param Delivery_Attempt|null          $attempt          The delivery attempt.
	 * @param Remote_Campaign_Reference|null $existing         The existing remote reference, when there is one.
	 */
	private function finalize( Campaign_Actor $actor, Campaign $campaign, int $expected_version, string $remote_id, ?Delivery_Attempt $attempt, ?Remote_Campaign_Reference $existing ): Campaign_Draft_Result {
		$reference = $this->reference( $campaign->id(), $remote_id, self::OBSERVED_DRAFT );
		$succeeded = null === $attempt ? null : $this->attempt( $attempt->id(), $campaign->id(), (string) $attempt->idempotency_key(), Delivery_Attempt_Status::SUCCEEDED, Retryability::NOT_RETRYABLE, $remote_id, $attempt->created_at() );
		$after     = $campaign->transition_to( Campaign_State::PROVIDER_DRAFT, $this->clock->now() );
		$record    = fn (): bool => ( null === $existing ? $this->references->add( $reference ) : $this->references->update_observation( $reference ) )
			&& ( null === $succeeded || $this->attempts->update_result( $succeeded ) );

		$written = $this->transaction->run(
			fn (): bool => $record()
				&& $this->campaigns->compare_and_swap( $after, $expected_version )
				&& $this->audits->add( $this->event( $actor, $campaign->id(), 'success', $this->audit_context( $campaign, $after, $remote_id, $attempt ) ) )
		);
		if ( $written ) {
			return Campaign_Draft_Result::success( $after, $reference, $succeeded, false );
		}

		// The remote draft exists. Its identity must survive even when the campaign changed concurrently.
		$kept    = $this->transaction->run(
			fn (): bool => $record()
				&& $this->audits->add( $this->event( $actor, $campaign->id(), 'failure', array_merge( $this->audit_context( $campaign, $campaign, $remote_id, $attempt ), array( 'error_code' => Campaign_Workflow_Error::CONFLICT ) ) ) )
		);
		$current = $this->campaigns->get( $campaign->id() ) ?? $campaign;

		return $kept
			? Campaign_Draft_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::CONFLICT, 'The provider draft was created, but the campaign changed concurrently. Repeat the request with the current version to finish.' ), $current, $reference, $succeeded )
			: Campaign_Draft_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'The provider draft was created but could not be recorded. Reconcile before creating another draft.' ), $current, null, $attempt );
	}

	/**
	 * Record a draft whose content upload failed, so the next attempt resumes it.
	 *
	 * @param Campaign_Actor      $actor     Who is acting, with their resolved campaign authority.
	 * @param Campaign            $campaign  The campaign as read.
	 * @param Delivery_Attempt    $attempt   The delivery attempt.
	 * @param string              $remote_id The provider's campaign ID.
	 * @param Provider_Error|null $error     Normalized provider error, when there is one.
	 */
	private function content_pending( Campaign_Actor $actor, Campaign $campaign, Delivery_Attempt $attempt, string $remote_id, ?Provider_Error $error ): Campaign_Draft_Result {
		$reference = $this->reference( $campaign->id(), $remote_id, self::OBSERVED_CONTENT_PENDING );
		$failed    = $this->attempt( $attempt->id(), $campaign->id(), (string) $attempt->idempotency_key(), Delivery_Attempt_Status::FAILED, Retryability::RETRYABLE, $remote_id, $attempt->created_at() );
		$kept      = $this->transaction->run( fn (): bool => $this->references->add( $reference ) && $this->attempts->update_result( $failed ) );
		if ( ! $kept ) {
			$this->audit( $actor, $campaign, 'unknown', $error, $attempt );

			return Campaign_Draft_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'The provider draft was created but could not be recorded. Reconcile before creating another draft.' ), $campaign, null, $attempt, $error );
		}

		return $this->provider_failure( $actor, $campaign, $reference, $failed, $error, 'The provider draft was created but its content could not be uploaded. Repeat the request to resume.' );
	}

	/**
	 * Record a draft creation the provider definitely refused.
	 *
	 * @param Campaign_Actor      $actor    Who is acting, with their resolved campaign authority.
	 * @param Campaign            $campaign The campaign as read.
	 * @param Delivery_Attempt    $attempt  The delivery attempt.
	 * @param Provider_Error|null $error    Normalized provider error, when there is one.
	 */
	private function definite_failure( Campaign_Actor $actor, Campaign $campaign, Delivery_Attempt $attempt, ?Provider_Error $error ): Campaign_Draft_Result {
		$failed = $this->attempt(
			$attempt->id(),
			$campaign->id(),
			(string) $attempt->idempotency_key(),
			Delivery_Attempt_Status::FAILED,
			null !== $error && $error->is_retryable() ? Retryability::RETRYABLE : Retryability::NOT_RETRYABLE,
			null,
			$attempt->created_at()
		);
		if ( ! $this->attempts->update_result( $failed ) ) {
			// The provider refused, but that could not be recorded: the pending attempt blocks further creates.
			$this->audit( $actor, $campaign, 'unknown', $error, $attempt );

			return Campaign_Draft_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'The draft request outcome could not be recorded. Reconcile before creating another draft.' ), $campaign, null, $attempt, $error );
		}

		return $this->provider_failure( $actor, $campaign, null, $failed, $error, 'The provider refused the draft. No draft was created.' );
	}

	/**
	 * Record a draft creation the provider did not confirm, so it must be reconciled.
	 *
	 * @param Campaign_Actor      $actor    Who is acting, with their resolved campaign authority.
	 * @param Campaign            $campaign The campaign as read.
	 * @param Delivery_Attempt    $attempt  The delivery attempt.
	 * @param Provider_Error|null $error    Normalized provider error, when there is one.
	 */
	private function ambiguous( Campaign_Actor $actor, Campaign $campaign, Delivery_Attempt $attempt, ?Provider_Error $error ): Campaign_Draft_Result {
		$unknown = $this->attempt( $attempt->id(), $campaign->id(), (string) $attempt->idempotency_key(), Delivery_Attempt_Status::UNKNOWN, Retryability::UNKNOWN, null, $attempt->created_at() );
		$stored  = $this->attempts->update_result( $unknown );
		$this->audit( $actor, $campaign, 'unknown', $error, $attempt );

		return Campaign_Draft_Result::failure(
			new Campaign_Workflow_Error( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'The provider did not confirm whether the draft was created. It will not be retried automatically; reconcile before creating another draft.' ),
			$campaign,
			null,
			$stored ? $unknown : $attempt,
			$error
		);
	}

	/**
	 * Audit a provider failure and return it as a refusal.
	 *
	 * @param Campaign_Actor                 $actor     Who is acting, with their resolved campaign authority.
	 * @param Campaign                       $campaign  The campaign as read.
	 * @param Remote_Campaign_Reference|null $reference The campaign's remote reference.
	 * @param Delivery_Attempt|null          $attempt   The delivery attempt.
	 * @param Provider_Error|null            $error     Normalized provider error, when there is one.
	 * @param string                         $message   Operator-safe message.
	 */
	private function provider_failure( Campaign_Actor $actor, Campaign $campaign, ?Remote_Campaign_Reference $reference, ?Delivery_Attempt $attempt, ?Provider_Error $error, string $message ): Campaign_Draft_Result {
		$this->audit( $actor, $campaign, 'failure', $error, $attempt );

		return Campaign_Draft_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::PROVIDER_FAILED, $message ), $campaign, $reference, $attempt, $error );
	}

	/**
	 * Audit a refusal and return it.
	 *
	 * @param string                $code        Stable error code.
	 * @param string                $message     Operator-safe message.
	 * @param Campaign_Actor        $actor       Who is acting, with their resolved campaign authority.
	 * @param string                $campaign_id Campaign ID.
	 * @param Campaign|null         $campaign    The campaign as read.
	 * @param string                $result      Audit result: success, failure, denied, or unknown.
	 * @param Delivery_Attempt|null $attempt     The delivery attempt.
	 */
	private function refuse( string $code, string $message, Campaign_Actor $actor, string $campaign_id, ?Campaign $campaign = null, string $result = 'failure', ?Delivery_Attempt $attempt = null ): Campaign_Draft_Result {
		try {
			$this->audits->add( $this->event( $actor, $campaign_id, $result, array( 'error_code' => $code ) ) );
		} catch ( \InvalidArgumentException ) {
			// A malformed external identifier cannot become an unsafe audit record.
			unset( $result );
		}

		return Campaign_Draft_Result::failure( new Campaign_Workflow_Error( $code, $message ), $campaign, null, $attempt );
	}

	/**
	 * Append the handoff's audit event.
	 *
	 * @param Campaign_Actor        $actor    Who is acting, with their resolved campaign authority.
	 * @param Campaign              $campaign The campaign as read.
	 * @param string                $result   Audit result: success, failure, denied, or unknown.
	 * @param Provider_Error|null   $error    Normalized provider error, when there is one.
	 * @param Delivery_Attempt|null $attempt  The delivery attempt.
	 */
	private function audit( Campaign_Actor $actor, Campaign $campaign, string $result, ?Provider_Error $error, ?Delivery_Attempt $attempt ): void {
		$this->audits->add(
			$this->event(
				$actor,
				$campaign->id(),
				$result,
				array(
					'provider'       => $this->gateway->slug(),
					'attempt_id'     => $attempt?->id(),
					'error_category' => $error?->category(),
					'error_code'     => $error?->code(),
				)
			)
		);
	}

	/**
	 * Audit context describing a completed handoff.
	 *
	 * @param Campaign              $before    The campaign before the change.
	 * @param Campaign              $after     The campaign after the change.
	 * @param string                $remote_id The provider's campaign ID.
	 * @param Delivery_Attempt|null $attempt   The delivery attempt.
	 * @return array<string, mixed>
	 */
	private function audit_context( Campaign $before, Campaign $after, string $remote_id, ?Delivery_Attempt $attempt ): array {
		return array(
			'provider'    => $this->gateway->slug(),
			'remote_id'   => $remote_id,
			'attempt_id'  => $attempt?->id(),
			'snapshot_id' => $before->active_snapshot_id(),
			'from_state'  => $before->state(),
			'to_state'    => $after->state(),
		);
	}

	/**
	 * Build one handoff audit event.
	 *
	 * @param Campaign_Actor       $actor       Who is acting, with their resolved campaign authority.
	 * @param string               $campaign_id Campaign ID.
	 * @param string               $result      Audit result: success, failure, denied, or unknown.
	 * @param array<string, mixed> $context     Safe audit context.
	 */
	private function event( Campaign_Actor $actor, string $campaign_id, string $result, array $context ): Audit_Event {
		return Audit_Event::from_array(
			array(
				'schema_version' => Audit_Event::SCHEMA_VERSION,
				'id'             => $this->ids->generate( 'audit' ),
				'actor_user_id'  => $actor->user_id(),
				'action'         => self::ACTION,
				'target_type'    => 'campaign',
				'target_id'      => $campaign_id,
				'result'         => $result,
				'context'        => Audit_Context::from_array( $context )->to_array(),
				'created_at'     => $this->clock->now(),
			)
		);
	}

	/**
	 * Build the campaign's remote reference in one observed state.
	 *
	 * @param string $campaign_id Campaign ID.
	 * @param string $remote_id   The provider's campaign ID.
	 * @param string $state       Observed remote state.
	 */
	private function reference( string $campaign_id, string $remote_id, string $state ): Remote_Campaign_Reference {
		return Remote_Campaign_Reference::from_array(
			array(
				'schema_version' => Remote_Campaign_Reference::SCHEMA_VERSION,
				'campaign_id'    => $campaign_id,
				'provider'       => $this->gateway->slug(),
				'remote_id'      => $remote_id,
				'observed_state' => $state,
				'cursor'         => null,
				'observed_at'    => $this->clock->now(),
				'reconciled_at'  => null,
			)
		);
	}

	/**
	 * Build one draft-creation attempt record.
	 *
	 * @param string      $id           Record ID.
	 * @param string      $campaign_id  Campaign ID.
	 * @param string      $key          Idempotency key.
	 * @param string      $status       Attempt status.
	 * @param string      $retryability Whether a new attempt may be made.
	 * @param string|null $correlation  The provider's correlation ID, when known.
	 * @param string|null $created_at   UTC timestamp of creation, or null for now.
	 */
	private function attempt( string $id, string $campaign_id, string $key, string $status, string $retryability, ?string $correlation, ?string $created_at ): Delivery_Attempt {
		$now = $this->clock->now();

		return Delivery_Attempt::from_array(
			array(
				'schema_version'     => Delivery_Attempt::SCHEMA_VERSION,
				'id'                 => $id,
				'campaign_id'        => $campaign_id,
				'operation'          => Delivery_Operation::CREATE_DRAFT,
				'idempotency_key'    => $key,
				'status'             => $status,
				'retryability'       => $retryability,
				'remote_correlation' => $correlation,
				'created_at'         => $created_at ?? $now,
				// A later request may run on a machine whose clock is a moment behind.
				'updated_at'         => null === $created_at ? $now : max( $now, $created_at ),
			)
		);
	}
}
