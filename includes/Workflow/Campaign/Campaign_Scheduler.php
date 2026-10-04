<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Public operation names and typed signatures form the application contract.
/**
 * Guarded scheduling of approved campaign drafts.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

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
use CampaignBridge\Domain\Campaign\Delivery_Policy_Source;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference_Source;
use CampaignBridge\Domain\Campaign\Retryability;
use CampaignBridge\Domain\Campaign\Schedule_Time;
use CampaignBridge\Domain\Provider\Action_Outcome;
use CampaignBridge\Domain\Provider\Provider_Capabilities;
use CampaignBridge\Domain\Provider\Provider_Delivery_Gateway;
use CampaignBridge\Domain\Provider\Provider_Draft_Gateway;
use CampaignBridge\Domain\Provider\Provider_Operation;
use CampaignBridge\Domain\Provider\Provider_Token_Mapper;
use CampaignBridge\Workflow\Provider\Provider_Discovery_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedules, unschedules, or immediately sends a confirmed remote draft.
 *
 * Protocol:
 *
 * 1. Check everything locally first: delivery authority, the audience
 *    confirmation, state, version, the remote reference, the schedule time,
 *    and that the approved snapshot still reproduces its artifact with a
 *    complete, translatable envelope.
 * 2. Never trust the remote draft as-is. `Campaign_Remote_Draft_Guard`
 *    requires an unsent draft, re-asserts the approved audience, envelope,
 *    and content, and proves it targets exactly the approved audience with no
 *    segment. A draft changed outside CampaignBridge cannot reach the
 *    audience.
 * 3. One delivery operation at a time. While any schedule, unschedule, or
 *    send attempt is `pending` or `unknown`, every new one is refused with
 *    `reconciliation_required`, whatever idempotency key is sent.
 * 4. Claim before delivery. One transaction stores the `pending` attempt and
 *    consumes the campaign version, so concurrent requests holding the same
 *    expected version are refused before any provider call.
 * 5. Record what is known. A definite refusal leaves the campaign where it
 *    was. An unconfirmed outcome moves the campaign to `unknown`, because it
 *    may or may not deliver; it is never retried automatically.
 * 6. A repeated idempotency key returns the recorded outcome and never
 *    contacts the provider again.
 * 7. Governance policy applies. With separate delivery on, the person who
 *    approved the campaign cannot schedule or send it, and an unrecorded
 *    approver fails closed. Unscheduling stops delivery, so it is never
 *    blocked.
 */
final class Campaign_Scheduler {
	/** Observed remote state of a scheduled campaign. */
	public const OBSERVED_SCHEDULED = 'scheduled';

	/** Observed remote state of a campaign the provider has started sending. */
	public const OBSERVED_SENDING = 'sending';

	/** Operations that can reach the audience; one unresolved attempt blocks them all. */
	private const DELIVERY_OPERATIONS = array( Delivery_Operation::SCHEDULE, Delivery_Operation::UNSCHEDULE, Delivery_Operation::SEND );

	public function __construct(
		private readonly Campaign_Source $campaigns,
		private readonly Campaign_Snapshot_Source $snapshots,
		private readonly Remote_Campaign_Reference_Source $references,
		private readonly Delivery_Attempt_Source $attempts,
		private readonly Audit_Event_Source $audits,
		private readonly Campaign_Transaction $transaction,
		private readonly Campaign_Id_Generator $ids,
		private readonly Campaign_Clock $clock,
		private readonly Provider_Delivery_Gateway $gateway,
		private readonly Provider_Capabilities $capabilities,
		private readonly Provider_Draft_Gateway $drafts,
		private readonly Provider_Token_Mapper $tokens,
		private readonly Provider_Discovery_Service $discovery,
		private readonly Delivery_Policy_Source $policies
	) {}

	/**
	 * Schedule the campaign's remote draft to send to its audience.
	 *
	 * @param string               $confirm_audience The audience reference the operator confirmed.
	 * @param array<string, mixed> $settings         Decrypted provider settings for this call only.
	 */
	public function schedule( Campaign_Actor $actor, string $campaign_id, int $expected_version, string $scheduled_for, string $confirm_audience, string $idempotency_key, array $settings ): Campaign_Delivery_Result {
		$operation = Delivery_Operation::SCHEDULE;
		$campaign  = $this->authorized( $actor, $campaign_id, $idempotency_key, $operation );
		if ( $campaign instanceof Campaign_Delivery_Result ) {
			return $campaign;
		}
		$refused = $this->audience_delivery_refusal( $actor, $campaign, $confirm_audience, $operation );
		if ( null !== $refused ) {
			return $refused;
		}
		$reference = $this->ready( $actor, $campaign, $expected_version, $idempotency_key, $operation, Campaign_State::PROVIDER_DRAFT, Campaign_Draft_Handoff::OBSERVED_DRAFT );
		if ( $reference instanceof Campaign_Delivery_Result ) {
			return $reference;
		}

		try {
			$time = Schedule_Time::parse( $scheduled_for, $this->clock->now(), $this->gateway->schedule_interval_minutes() );
		} catch ( \InvalidArgumentException $invalid ) {
			return $this->refuse( $operation, Campaign_Workflow_Error::INVALID_INPUT, $invalid->getMessage(), $actor, $campaign_id, $campaign );
		}
		$snapshot = ( new Campaign_Snapshot_Verifier( $this->snapshots ) )->verify( $campaign );
		if ( $snapshot instanceof Campaign_Workflow_Error ) {
			return $this->refuse( $operation, $snapshot->code(), $snapshot->message(), $actor, $campaign_id, $campaign );
		}
		$content = ( new Campaign_Draft_Content_Builder( $this->tokens, $this->discovery ) )->build( $snapshot, (string) $campaign->audience_reference(), $settings, $reference->remote_id() );
		if ( $content instanceof Campaign_Workflow_Error ) {
			return $this->refuse( $operation, $content->code(), $content->message(), $actor, $campaign_id, $campaign );
		}
		$drift = ( new Campaign_Remote_Draft_Guard( $this->drafts ) )->reassert( $settings, $reference->remote_id(), $content, true );
		if ( null !== $drift ) {
			return $this->refuse( $operation, $drift[0], $drift[1], $actor, $campaign_id, $campaign, 'failure', null, $drift[2] );
		}

		return $this->perform(
			$actor,
			$campaign,
			$reference,
			$operation,
			$idempotency_key,
			fn (): Action_Outcome => $this->gateway->schedule( $settings, $reference->remote_id(), $time->utc() ),
			fn ( Campaign $claimed ): Campaign => $claimed->schedule_for( $time->utc(), $this->clock->now() ),
			self::OBSERVED_SCHEDULED,
			array(
				'scheduled_for' => $time->utc(),
				'snapshot_id'   => $snapshot->id(),
				'fingerprint'   => $snapshot->artifact()->fingerprint(),
			)
		);
	}

	/**
	 * Send the campaign's remote draft to its audience now.
	 *
	 * The most irreversible operation, so it uses every protection scheduling
	 * does: delivery authority, separation of duties, the confirmed audience,
	 * the single-unresolved-attempt rule, the version claim, and the remote
	 * draft guard. An accepted send leaves the campaign `sending`; reconcile
	 * to record `sent`. An unconfirmed send leaves it `unknown`.
	 *
	 * @param string               $confirm_audience The audience reference the operator confirmed.
	 * @param array<string, mixed> $settings         Decrypted provider settings for this call only.
	 */
	public function send( Campaign_Actor $actor, string $campaign_id, int $expected_version, string $confirm_audience, string $idempotency_key, array $settings ): Campaign_Delivery_Result {
		$operation = Delivery_Operation::SEND;
		$campaign  = $this->authorized( $actor, $campaign_id, $idempotency_key, $operation );
		if ( $campaign instanceof Campaign_Delivery_Result ) {
			return $campaign;
		}
		$refused = $this->audience_delivery_refusal( $actor, $campaign, $confirm_audience, $operation );
		if ( null !== $refused ) {
			return $refused;
		}
		$reference = $this->ready( $actor, $campaign, $expected_version, $idempotency_key, $operation, Campaign_State::PROVIDER_DRAFT, Campaign_Draft_Handoff::OBSERVED_DRAFT );
		if ( $reference instanceof Campaign_Delivery_Result ) {
			return $reference;
		}

		$snapshot = ( new Campaign_Snapshot_Verifier( $this->snapshots ) )->verify( $campaign );
		if ( $snapshot instanceof Campaign_Workflow_Error ) {
			return $this->refuse( $operation, $snapshot->code(), $snapshot->message(), $actor, $campaign_id, $campaign );
		}
		$content = ( new Campaign_Draft_Content_Builder( $this->tokens, $this->discovery ) )->build( $snapshot, (string) $campaign->audience_reference(), $settings, $reference->remote_id() );
		if ( $content instanceof Campaign_Workflow_Error ) {
			return $this->refuse( $operation, $content->code(), $content->message(), $actor, $campaign_id, $campaign );
		}
		$drift = ( new Campaign_Remote_Draft_Guard( $this->drafts ) )->reassert( $settings, $reference->remote_id(), $content, true );
		if ( null !== $drift ) {
			return $this->refuse( $operation, $drift[0], $drift[1], $actor, $campaign_id, $campaign, 'failure', null, $drift[2] );
		}

		return $this->perform(
			$actor,
			$campaign,
			$reference,
			$operation,
			$idempotency_key,
			fn (): Action_Outcome => $this->gateway->send( $settings, $reference->remote_id() ),
			fn ( Campaign $claimed ): Campaign => $claimed->transition_to( Campaign_State::SENDING, $this->clock->now() ),
			self::OBSERVED_SENDING,
			array(
				'snapshot_id' => $snapshot->id(),
				'fingerprint' => $snapshot->artifact()->fingerprint(),
			)
		);
	}

	/**
	 * Return a scheduled campaign to its unscheduled provider draft.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings for this call only.
	 */
	public function unschedule( Campaign_Actor $actor, string $campaign_id, int $expected_version, string $idempotency_key, array $settings ): Campaign_Delivery_Result {
		$operation = Delivery_Operation::UNSCHEDULE;
		$campaign  = $this->authorized( $actor, $campaign_id, $idempotency_key, $operation );
		if ( $campaign instanceof Campaign_Delivery_Result ) {
			return $campaign;
		}
		$reference = $this->ready( $actor, $campaign, $expected_version, $idempotency_key, $operation, Campaign_State::SCHEDULED, self::OBSERVED_SCHEDULED );
		if ( $reference instanceof Campaign_Delivery_Result ) {
			return $reference;
		}
		if ( null === $campaign->scheduled_for() || $campaign->scheduled_for() <= $this->clock->now() ) {
			// Unscheduling cannot pretend to stop a send that may already have started.
			return $this->refuse( $operation, Campaign_Workflow_Error::INVALID_STATE, 'The scheduled delivery time has passed, so the campaign may already be sending. Reconcile its status instead.', $actor, $campaign_id, $campaign );
		}

		return $this->perform(
			$actor,
			$campaign,
			$reference,
			$operation,
			$idempotency_key,
			fn (): Action_Outcome => $this->gateway->unschedule( $settings, $reference->remote_id() ),
			fn ( Campaign $claimed ): Campaign => $claimed->transition_to( Campaign_State::PROVIDER_DRAFT, $this->clock->now() ),
			Campaign_Draft_Handoff::OBSERVED_DRAFT,
			array( 'scheduled_for' => $campaign->scheduled_for() )
		);
	}

	/** Load the campaign and check authority, the key, and provider support. */
	private function authorized( Campaign_Actor $actor, string $campaign_id, string $idempotency_key, string $operation ): Campaign|Campaign_Delivery_Result {
		$campaign = $this->campaigns->get( $campaign_id );
		if ( null === $campaign ) {
			return $this->refuse( $operation, Campaign_Workflow_Error::NOT_FOUND, 'Campaign was not found.', $actor, $campaign_id );
		}
		if ( ! $actor->can_deliver( $campaign ) ) {
			return $this->refuse( $operation, Campaign_Workflow_Error::FORBIDDEN, 'Campaign operation is not allowed.', $actor, $campaign_id, $campaign, 'denied' );
		}
		if ( '' === $idempotency_key || 191 < strlen( $idempotency_key ) ) {
			return $this->refuse( $operation, Campaign_Workflow_Error::INVALID_INPUT, 'A bounded idempotency key is required.', $actor, $campaign_id, $campaign );
		}
		$supported = match ( $operation ) {
			Delivery_Operation::SCHEDULE => Provider_Operation::SCHEDULE,
			Delivery_Operation::SEND     => Provider_Operation::SEND,
			default                      => Provider_Operation::UNSCHEDULE,
		};
		if ( ! $this->capabilities->supports( $supported ) || $this->gateway->slug() !== $campaign->provider() ) {
			return $this->refuse( $operation, Campaign_Workflow_Error::INVALID_INPUT, 'The campaign must target a provider that supports this delivery operation.', $actor, $campaign_id, $campaign );
		}

		return $campaign;
	}

	/**
	 * Refuse audience delivery by the approver under separate delivery, or to an unconfirmed audience.
	 *
	 * Unscheduling is exempt: it stops delivery rather than starting it.
	 */
	private function audience_delivery_refusal( Campaign_Actor $actor, Campaign $campaign, string $confirm_audience, string $operation ): ?Campaign_Delivery_Result {
		if ( ! $this->policies->current()->allows_delivery_by( $actor->user_id(), $campaign->approved_by_user_id() ) ) {
			return $this->refuse(
				$operation,
				Campaign_Workflow_Error::FORBIDDEN,
				null === $campaign->approved_by_user_id()
					? 'Separation of duties is required, but this campaign was approved before approvers were recorded, so it cannot be verified. A manager must recreate and approve the campaign, or turn the policy off to deliver it.'
					: sprintf( 'Separation of duties is required: the person who approved this campaign cannot %s it. Ask another person with delivery authority.', $operation ),
				$actor,
				$campaign->id(),
				$campaign,
				'denied',
				null,
				null,
				array( 'policy' => 'separate_delivery' )
			);
		}
		if ( null === $campaign->audience_reference() || ! hash_equals( $campaign->audience_reference(), $confirm_audience ) ) {
			return $this->refuse( $operation, Campaign_Workflow_Error::INVALID_INPUT, sprintf( 'The confirmed audience does not match the campaign\'s audience. Nothing was %s.', self::past( $operation ) ), $actor, $campaign->id(), $campaign );
		}

		return null;
	}

	/** Past participle of an operation, for messages. */
	private static function past( string $operation ): string {
		return Delivery_Operation::SEND === $operation ? 'sent' : $operation . 'd';
	}

	/** Replay a recorded key, or check that nothing blocks a new operation from this state. */
	private function ready( Campaign_Actor $actor, Campaign $campaign, int $expected_version, string $idempotency_key, string $operation, string $state, string $observed ): Remote_Campaign_Reference|Campaign_Delivery_Result {
		$reference = $this->references->get( $campaign->id(), $this->gateway->slug() );
		$recorded  = $this->attempts->find_idempotency( $campaign->id(), $operation, $idempotency_key );
		if ( null !== $recorded ) {
			return $this->replay( $actor, $campaign, $reference, $recorded, $operation );
		}
		foreach ( $this->attempts->for_campaign( $campaign->id(), 100 ) as $attempt ) {
			if ( in_array( $attempt->operation(), self::DELIVERY_OPERATIONS, true ) && in_array( $attempt->status(), array( Delivery_Attempt_Status::PENDING, Delivery_Attempt_Status::UNKNOWN ), true ) ) {
				return $this->refuse( $operation, Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'An earlier delivery request for this campaign has an unconfirmed outcome and must be reconciled first.', $actor, $campaign->id(), $campaign, 'failure', $attempt );
			}
		}
		if ( $state !== $campaign->state() || null === $reference || $observed !== $reference->observed_state() ) {
			return $this->refuse( $operation, Campaign_Workflow_Error::INVALID_STATE, sprintf( 'Only a %s campaign can be %s.', str_replace( '_', ' ', $state ), self::past( $operation ) ), $actor, $campaign->id(), $campaign );
		}
		if ( $campaign->version() !== $expected_version ) {
			return $this->refuse( $operation, Campaign_Workflow_Error::CONFLICT, 'Campaign version is stale.', $actor, $campaign->id(), $campaign );
		}

		return $reference;
	}

	private function replay( Campaign_Actor $actor, Campaign $campaign, ?Remote_Campaign_Reference $reference, Delivery_Attempt $recorded, string $operation ): Campaign_Delivery_Result {
		if ( Delivery_Attempt_Status::SUCCEEDED === $recorded->status() && null !== $reference ) {
			return Campaign_Delivery_Result::success( $campaign, $reference, $recorded, true );
		}

		return Delivery_Attempt_Status::FAILED === $recorded->status()
			? $this->refuse( $operation, Campaign_Workflow_Error::PROVIDER_FAILED, 'This idempotency key belongs to a request the provider refused. Use a new key to try again.', $actor, $campaign->id(), $campaign, 'failure', $recorded )
			: $this->refuse( $operation, Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'This idempotency key belongs to a request whose outcome was not confirmed. It must be reconciled first.', $actor, $campaign->id(), $campaign, 'failure', $recorded );
	}

	/**
	 * Claim the campaign, contact the provider once, and record what is known.
	 *
	 * @param callable(): Action_Outcome     $call    The one provider call.
	 * @param callable(Campaign): Campaign   $finish  The campaign after an accepted call.
	 * @param array<string, string|null>     $context Operation-specific audit context.
	 */
	private function perform( Campaign_Actor $actor, Campaign $campaign, Remote_Campaign_Reference $reference, string $operation, string $idempotency_key, callable $call, callable $finish, string $observed, array $context ): Campaign_Delivery_Result {
		$claimed = $campaign->claim( $this->clock->now() );
		$attempt = $this->attempt( $this->ids->generate( 'attempt' ), $campaign->id(), $operation, $idempotency_key, Delivery_Attempt_Status::PENDING, Retryability::UNKNOWN, $reference->remote_id(), null );
		if ( ! $this->transaction->run( fn (): bool => $this->attempts->add( $attempt ) && $this->campaigns->compare_and_swap( $claimed, $campaign->version() ) ) ) {
			// A concurrent request claimed the campaign or this key first; it alone may contact the provider.
			$current = $this->campaigns->get( $campaign->id() ) ?? $campaign;

			return $current->version() !== $campaign->version()
				? $this->refuse( $operation, Campaign_Workflow_Error::CONFLICT, 'Campaign version is stale.', $actor, $campaign->id(), $current )
				: $this->refuse( $operation, Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'Another delivery request for this campaign is already in progress.', $actor, $campaign->id(), $current );
		}

		$outcome = $call();

		return match ( $outcome->status() ) {
			Action_Outcome::ACCEPTED => $this->accepted( $actor, $claimed, $reference, $attempt, $operation, $finish, $observed, $context ),
			Action_Outcome::FAILED   => $this->definite_failure( $actor, $claimed, $reference, $attempt, $operation, $context, $outcome->error() ),
			default                  => $this->ambiguous( $actor, $claimed, $reference, $attempt, $operation, $context, $outcome->error() ),
		};
	}

	/**
	 * @param callable(Campaign): Campaign $finish  The campaign after an accepted call.
	 * @param array<string, string|null>   $context Operation-specific audit context.
	 */
	private function accepted( Campaign_Actor $actor, Campaign $claimed, Remote_Campaign_Reference $reference, Delivery_Attempt $attempt, string $operation, callable $finish, string $observed, array $context ): Campaign_Delivery_Result {
		$after       = $finish( $claimed );
		$observation = $this->observe( $reference, $observed );
		$succeeded   = $this->settled( $attempt, Delivery_Attempt_Status::SUCCEEDED, Retryability::NOT_RETRYABLE );
		$written     = $this->transaction->run(
			fn (): bool => $this->attempts->update_result( $succeeded )
				&& $this->references->update_observation( $observation )
				&& $this->campaigns->compare_and_swap( $after, $claimed->version() )
				&& $this->audits->add( $this->event( $actor, $operation, $claimed->id(), 'success', $this->context( $claimed, $after, $reference, $succeeded, $context, null ) ) )
		);
		if ( $written ) {
			return Campaign_Delivery_Result::success( $after, $observation, $succeeded, false );
		}

		// The provider accepted, but it could not be recorded; the pending attempt keeps further delivery blocked.
		$this->audit( $actor, $operation, $claimed, 'unknown', $this->context( $claimed, $claimed, $reference, $attempt, $context, null ) );

		return Campaign_Delivery_Result::failure(
			new Campaign_Workflow_Error( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, sprintf( 'The provider accepted the %s request, but it could not be recorded. Reconcile before any further delivery request.', $operation ) ),
			$this->campaigns->get( $claimed->id() ) ?? $claimed,
			$reference,
			$attempt
		);
	}

	/** @param array<string, string|null> $context Operation-specific audit context. */
	private function definite_failure( Campaign_Actor $actor, Campaign $claimed, Remote_Campaign_Reference $reference, Delivery_Attempt $attempt, string $operation, array $context, ?Provider_Error $error ): Campaign_Delivery_Result {
		$failed = $this->settled( $attempt, Delivery_Attempt_Status::FAILED, null !== $error && $error->is_retryable() ? Retryability::RETRYABLE : Retryability::NOT_RETRYABLE );
		if ( ! $this->attempts->update_result( $failed ) ) {
			// The refusal could not be recorded: the pending attempt keeps further delivery blocked.
			$this->audit( $actor, $operation, $claimed, 'unknown', $this->context( $claimed, $claimed, $reference, $attempt, $context, $error ) );

			return Campaign_Delivery_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, sprintf( 'The %s outcome could not be recorded. Reconcile before any further delivery request.', $operation ) ), $claimed, $reference, $attempt, $error );
		}
		$this->audit( $actor, $operation, $claimed, 'failure', $this->context( $claimed, $claimed, $reference, $failed, $context, $error ) );

		return Campaign_Delivery_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::PROVIDER_FAILED, sprintf( 'The provider refused the %s request. Nothing changed.', $operation ) ), $claimed, $reference, $failed, $error );
	}

	/** @param array<string, string|null> $context Operation-specific audit context. */
	private function ambiguous( Campaign_Actor $actor, Campaign $claimed, Remote_Campaign_Reference $reference, Delivery_Attempt $attempt, string $operation, array $context, ?Provider_Error $error ): Campaign_Delivery_Result {
		$unknown = $this->settled( $attempt, Delivery_Attempt_Status::UNKNOWN, Retryability::UNKNOWN );
		$after   = $claimed->transition_to( Campaign_State::UNKNOWN, $this->clock->now() );
		$written = $this->transaction->run(
			fn (): bool => $this->attempts->update_result( $unknown )
				&& $this->campaigns->compare_and_swap( $after, $claimed->version() )
		);
		if ( ! $written ) {
			$this->attempts->update_result( $unknown );
		}
		$current = $written ? $after : ( $this->campaigns->get( $claimed->id() ) ?? $claimed );
		$this->audit( $actor, $operation, $claimed, 'unknown', $this->context( $claimed, $current, $reference, $unknown, $context, $error ) );

		return Campaign_Delivery_Result::failure(
			new Campaign_Workflow_Error( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, sprintf( 'The provider did not confirm the %s request. The campaign may or may not send; it will not be retried automatically and must be reconciled.', $operation ) ),
			$current,
			$reference,
			$unknown,
			$error
		);
	}

	/** @param array<string, string> $context Additional safe audit context. */
	private function refuse( string $operation, string $code, string $message, Campaign_Actor $actor, string $campaign_id, ?Campaign $campaign = null, string $result = 'failure', ?Delivery_Attempt $attempt = null, ?Provider_Error $error = null, array $context = array() ): Campaign_Delivery_Result {
		try {
			$this->audits->add(
				$this->event(
					$actor,
					$operation,
					$campaign_id,
					$result,
					array_merge(
						array(
							'error_code'          => $code,
							'provider_error_code' => $error?->code(),
						),
						$context
					)
				)
			);
		} catch ( \InvalidArgumentException ) {
			// A malformed external identifier cannot become an unsafe audit record.
			unset( $result );
		}

		return Campaign_Delivery_Result::failure( new Campaign_Workflow_Error( $code, $message ), $campaign, null, $attempt, $error );
	}

	/** @param array<string, mixed> $context Safe audit context. */
	private function audit( Campaign_Actor $actor, string $operation, Campaign $campaign, string $result, array $context ): void {
		$this->audits->add( $this->event( $actor, $operation, $campaign->id(), $result, $context ) );
	}

	/**
	 * Identify the actor's operation, artifact, remote reference, and normalized result.
	 *
	 * @param array<string, string|null> $extra Operation-specific context.
	 * @return array<string, mixed>
	 */
	private function context( Campaign $before, Campaign $after, Remote_Campaign_Reference $reference, Delivery_Attempt $attempt, array $extra, ?Provider_Error $error ): array {
		return array_merge(
			array(
				'provider'       => $reference->provider(),
				'remote_id'      => $reference->remote_id(),
				'attempt_id'     => $attempt->id(),
				'attempt_status' => $attempt->status(),
				'from_state'     => $before->state(),
				'to_state'       => $after->state(),
				'error_category' => $error?->category(),
				'error_code'     => $error?->code(),
			),
			$extra
		);
	}

	/** @param array<string, mixed> $context Safe audit context. */
	private function event( Campaign_Actor $actor, string $operation, string $campaign_id, string $result, array $context ): Audit_Event {
		return Audit_Event::from_array(
			array(
				'schema_version' => Audit_Event::SCHEMA_VERSION,
				'id'             => $this->ids->generate( 'audit' ),
				'actor_user_id'  => $actor->user_id(),
				'action'         => 'campaign_' . $operation,
				'target_type'    => 'campaign',
				'target_id'      => $campaign_id,
				'result'         => $result,
				'context'        => Audit_Context::from_array( $context )->to_array(),
				'created_at'     => $this->clock->now(),
			)
		);
	}

	private function observe( Remote_Campaign_Reference $reference, string $state ): Remote_Campaign_Reference {
		return Remote_Campaign_Reference::from_array(
			array_merge(
				$reference->to_array(),
				array(
					'observed_state' => $state,
					'observed_at'    => $this->clock->now(),
				)
			)
		);
	}

	private function settled( Delivery_Attempt $attempt, string $status, string $retryability ): Delivery_Attempt {
		return $this->attempt( $attempt->id(), $attempt->campaign_id(), $attempt->operation(), (string) $attempt->idempotency_key(), $status, $retryability, $attempt->remote_correlation(), $attempt->created_at() );
	}

	private function attempt( string $id, string $campaign_id, string $operation, string $key, string $status, string $retryability, ?string $correlation, ?string $created_at ): Delivery_Attempt {
		$now = $this->clock->now();

		return Delivery_Attempt::from_array(
			array(
				'schema_version'     => Delivery_Attempt::SCHEMA_VERSION,
				'id'                 => $id,
				'campaign_id'        => $campaign_id,
				'operation'          => $operation,
				'idempotency_key'    => $key,
				'status'             => $status,
				'retryability'       => $retryability,
				'remote_correlation' => $correlation,
				'created_at'         => $created_at ?? $now,
				'updated_at'         => $now,
			)
		);
	}
}
