<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Public operation names and typed signatures form the application contract.
/**
 * Reconcile a campaign with what its provider reports.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Audit_Context;
use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Audit_Event_Source;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_Source;
use CampaignBridge\Domain\Campaign\Campaign_State;
use CampaignBridge\Domain\Campaign\Campaign_Transaction;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Domain\Campaign\Delivery_Attempt_Source;
use CampaignBridge\Domain\Campaign\Delivery_Attempt_Status;
use CampaignBridge\Domain\Campaign\Delivery_Operation;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference_Source;
use CampaignBridge\Domain\Campaign\Retryability;
use CampaignBridge\Domain\Provider\Provider_Capabilities;
use CampaignBridge\Domain\Provider\Provider_Draft_Gateway;
use CampaignBridge\Domain\Provider\Provider_Operation;
use CampaignBridge\Domain\Provider\Remote_Draft_State;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settles unconfirmed outcomes and follows the provider's campaign state.
 *
 * Protocol:
 *
 * 1. Read only. The provider is inspected; nothing is created, scheduled,
 *    sent, or changed there, so repeating reconciliation is always safe.
 * 2. Evidence only. An attempt is settled as succeeded or failed only from
 *    what the provider reports, never from a timeout. A `pending` attempt
 *    younger than SETTLE_SECONDS may still be in flight and is left alone.
 *    Evidence that a request took effect settles it at once, but "not
 *    applied" is accepted only once the attempt is SETTLE_SECONDS old: a
 *    provider may still be applying a request whose response was lost.
 * 3. A create whose outcome was not confirmed is found by its correlation
 *    title. One match records the draft; none, when the search was complete
 *    and the settle time has passed, proves it was not created. Several
 *    matches, or an incomplete search, stay unresolved.
 * 4. With a remote reference, the campaign follows the provider's status
 *    (draft, scheduled with its send time, sending, sent, canceled), and
 *    each unresolved schedule, unschedule, or send is settled by whether
 *    that status shows it took effect.
 * 5. Contradictions are never papered over: a missing remote campaign, an
 *    untracked status, or a status the local state cannot follow is
 *    recorded and returned as `reconciliation_required`.
 * 6. Writes claim the campaign version in the same transaction, so they
 *    serialize with delivery requests and with each other.
 */
final class Campaign_Reconciler {
	/** Seconds a pending request may still be in flight, and a missing draft must stay missing. */
	public const SETTLE_SECONDS = 300;

	/** How far before the attempt the draft search starts, allowing for clock skew. */
	private const SEARCH_MARGIN_SECONDS = 3600;

	/** Observed state when the provider no longer has the campaign. */
	public const OBSERVED_MISSING = 'missing';

	/** Observed state for a provider status CampaignBridge does not track. */
	public const OBSERVED_OTHER = 'other';

	private const ACTION = 'campaign_reconcile';

	/** Operations whose outcome the provider's campaign state can prove. Test sends cannot be observed. */
	private const RECONCILABLE = array( Delivery_Operation::CREATE_DRAFT, Delivery_Operation::SCHEDULE, Delivery_Operation::UNSCHEDULE, Delivery_Operation::SEND );

	/**
	 * Provider progress that follows from a request that already succeeded: a
	 * scheduled campaign starting or finishing its send, and a send finishing.
	 * These are expected, not changes made outside CampaignBridge.
	 */
	private const PROGRESSIONS = array(
		Campaign_State::SCHEDULED => array( Campaign_State::SENDING, Campaign_State::SENT ),
		Campaign_State::SENDING   => array( Campaign_State::SENT ),
	);

	/** Local states that follow the provider's delivery lifecycle. */
	private const DELIVERY_STATES = array( Campaign_State::PROVIDER_DRAFT, Campaign_State::SCHEDULED, Campaign_State::SENDING, Campaign_State::UNKNOWN );

	/** Local state, and recorded observation, for each provider status. */
	private const EVIDENCE = array(
		Remote_Draft_State::DRAFT     => array( Campaign_State::PROVIDER_DRAFT, Campaign_Draft_Handoff::OBSERVED_DRAFT ),
		Remote_Draft_State::SCHEDULED => array( Campaign_State::SCHEDULED, Campaign_Scheduler::OBSERVED_SCHEDULED ),
		Remote_Draft_State::SENDING   => array( Campaign_State::SENDING, Campaign_Scheduler::OBSERVED_SENDING ),
		Remote_Draft_State::SENT      => array( Campaign_State::SENT, 'sent' ),
		Remote_Draft_State::CANCELED  => array( Campaign_State::CANCELLED, 'canceled' ),
	);

	public function __construct(
		private readonly Campaign_Source $campaigns,
		private readonly Remote_Campaign_Reference_Source $references,
		private readonly Delivery_Attempt_Source $attempts,
		private readonly Audit_Event_Source $audits,
		private readonly Campaign_Transaction $transaction,
		private readonly Campaign_Id_Generator $ids,
		private readonly Campaign_Clock $clock,
		private readonly Provider_Draft_Gateway $drafts,
		private readonly Provider_Capabilities $capabilities
	) {}

	/**
	 * Reconcile one campaign with its provider.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings for this call only.
	 */
	public function reconcile( Campaign_Actor $actor, string $campaign_id, array $settings ): Campaign_Reconcile_Result {
		$campaign = $this->campaigns->get( $campaign_id );
		if ( null === $campaign ) {
			return $this->refuse( Campaign_Workflow_Error::NOT_FOUND, 'Campaign was not found.', $actor, $campaign_id );
		}
		if ( ! $actor->can_deliver( $campaign ) ) {
			return $this->refuse( Campaign_Workflow_Error::FORBIDDEN, 'Campaign operation is not allowed.', $actor, $campaign_id, $campaign, 'denied' );
		}
		if ( ! $this->capabilities->supports( Provider_Operation::RECONCILE ) || $this->drafts->slug() !== $campaign->provider() ) {
			return $this->refuse( Campaign_Workflow_Error::INVALID_INPUT, 'The campaign must target a provider that supports reconciliation.', $actor, $campaign_id, $campaign );
		}

		$unresolved = $this->unresolved( $campaign_id );
		foreach ( $unresolved as $attempt ) {
			$age = $this->age( $attempt );
			if ( Delivery_Attempt_Status::PENDING === $attempt->status() && $age < self::SETTLE_SECONDS ) {
				return $this->refuse(
					Campaign_Workflow_Error::RECONCILIATION_REQUIRED,
					sprintf( 'A %s request is still in progress. Reconcile again in %d seconds.', str_replace( '_', ' ', $attempt->operation() ), self::SETTLE_SECONDS - $age ),
					$actor,
					$campaign_id,
					$campaign,
					'failure',
					$attempt,
					null,
					Campaign_Workflow_Error::REASON_IN_PROGRESS,
					self::SETTLE_SECONDS - $age
				);
			}
		}

		$reference = $this->references->get( $campaign_id, $this->drafts->slug() );
		if ( null !== $reference ) {
			return $this->follow_provider( $actor, $campaign, $reference, $unresolved, $settings );
		}
		foreach ( $unresolved as $attempt ) {
			if ( Delivery_Operation::CREATE_DRAFT === $attempt->operation() ) {
				return $this->recover_draft( $actor, $campaign, $attempt, $settings );
			}
		}

		// Nothing has reached the provider, so there is nothing to reconcile.
		return Campaign_Reconcile_Result::success( $campaign, null, array() );
	}

	/**
	 * Find the draft an unconfirmed create may have made, by its correlation title.
	 *
	 * @param array<string, mixed> $settings Decrypted provider settings.
	 */
	private function recover_draft( Campaign_Actor $actor, Campaign $campaign, Delivery_Attempt $attempt, array $settings ): Campaign_Reconcile_Result {
		$since = gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $attempt->created_at() ) - self::SEARCH_MARGIN_SECONDS );
		$found = $this->drafts->find_drafts( $settings, Campaign_Draft_Content_Builder::title( $attempt->id() ), $since );
		if ( $found instanceof Provider_Error ) {
			return $this->refuse( Campaign_Workflow_Error::PROVIDER_FAILED, 'The provider could not be searched for the draft. Nothing changed.', $actor, $campaign->id(), $campaign, 'failure', $attempt, $found );
		}

		$remote_ids = $found->remote_ids();
		if ( 1 < count( $remote_ids ) ) {
			return $this->refuse( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, sprintf( 'The provider holds %d drafts for this request. Delete all but one in the provider, then reconcile again.', count( $remote_ids ) ), $actor, $campaign->id(), $campaign, 'unknown', $attempt, null, Campaign_Workflow_Error::REASON_DUPLICATE_DRAFTS );
		}

		if ( 1 === count( $remote_ids ) ) {
			// The draft exists; its content may not have been uploaded, so the next draft request re-asserts it.
			$reference = Remote_Campaign_Reference::from_array(
				array(
					'schema_version' => Remote_Campaign_Reference::SCHEMA_VERSION,
					'campaign_id'    => $campaign->id(),
					'provider'       => $this->drafts->slug(),
					'remote_id'      => $remote_ids[0],
					'observed_state' => Campaign_Draft_Handoff::OBSERVED_CONTENT_PENDING,
					'cursor'         => null,
					'observed_at'    => $this->clock->now(),
					'reconciled_at'  => $this->clock->now(),
				)
			);
			$succeeded = $this->settled( $attempt, Delivery_Attempt_Status::SUCCEEDED, Retryability::NOT_RETRYABLE, $remote_ids[0] );
			$written   = $this->transaction->run(
				fn (): bool => $this->references->add( $reference )
					&& $this->attempts->update_result( $succeeded )
					&& $this->audits->add( $this->event( $actor, $campaign->id(), 'success', $this->context( $campaign, $campaign, $reference->remote_id(), 'found', array( $succeeded ), false ) ) )
			);

			return $written
				? Campaign_Reconcile_Result::success( $campaign, $reference, array( $succeeded ) )
				: $this->refuse( Campaign_Workflow_Error::CONFLICT, 'The draft was found, but the campaign changed while it was being recorded. Reconcile again.', $actor, $campaign->id(), $campaign, 'failure', $attempt );
		}

		if ( ! $found->is_complete() || $this->age( $attempt ) < self::SETTLE_SECONDS ) {
			return $this->refuse( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'The draft has not been found, but its absence cannot be confirmed yet. Reconcile again later.', $actor, $campaign->id(), $campaign, 'unknown', $attempt, null, Campaign_Workflow_Error::REASON_IN_PROGRESS );
		}

		$failed  = $this->settled( $attempt, Delivery_Attempt_Status::FAILED, Retryability::RETRYABLE, null );
		$written = $this->transaction->run(
			fn (): bool => $this->attempts->update_result( $failed )
				&& $this->audits->add( $this->event( $actor, $campaign->id(), 'success', $this->context( $campaign, $campaign, null, 'absent', array( $failed ), false ) ) )
		);

		return $written
			? Campaign_Reconcile_Result::success( $campaign, null, array( $failed ) )
			: $this->refuse( Campaign_Workflow_Error::CONFLICT, 'The draft request could not be settled. Reconcile again.', $actor, $campaign->id(), $campaign, 'failure', $attempt );
	}

	/**
	 * Move the campaign and its unresolved attempts to what the provider reports.
	 *
	 * @param array<int, Delivery_Attempt> $unresolved Settled-eligible attempts.
	 * @param array<string, mixed>         $settings   Decrypted provider settings.
	 */
	private function follow_provider( Campaign_Actor $actor, Campaign $campaign, Remote_Campaign_Reference $reference, array $unresolved, array $settings ): Campaign_Reconcile_Result {
		$remote = $this->drafts->inspect_draft( $settings, $reference->remote_id() );
		if ( $remote instanceof Provider_Error ) {
			return Provider_Error_Category::NOT_FOUND === $remote->category()
				? $this->contradiction( $actor, $campaign, $reference, self::OBSERVED_MISSING, 'The provider no longer has this campaign; it may have been deleted there. Nothing was changed locally. Check the provider before any further delivery.', $unresolved, $remote, Campaign_Workflow_Error::REASON_MISSING )
				: $this->refuse( Campaign_Workflow_Error::PROVIDER_FAILED, 'The provider status could not be read. Nothing changed.', $actor, $campaign->id(), $campaign, 'failure', $unresolved[0] ?? null, $remote );
		}

		$evidence = self::EVIDENCE[ $remote->status() ] ?? null;
		if ( null === $evidence ) {
			return $this->contradiction( $actor, $campaign, $reference, self::OBSERVED_OTHER, 'The provider reports a status CampaignBridge does not track, such as a cancellation in progress or an archived campaign. Resolve it in the provider, then reconcile again.', $unresolved, null, Campaign_Workflow_Error::REASON_UNTRACKED );
		}
		list( $target, $observed ) = $evidence;

		$resolved = array();
		foreach ( $unresolved as $attempt ) {
			$applied = $this->applied( $attempt->operation(), $target );
			if ( null === $applied ) {
				return $this->contradiction( $actor, $campaign, $reference, $observed, sprintf( 'The provider reports the campaign as %s, which does not show whether the earlier %s request took effect. Check the provider.', $remote->status(), str_replace( '_', ' ', $attempt->operation() ) ), $unresolved, null, Campaign_Workflow_Error::REASON_INCONCLUSIVE );
			}
			$age = $this->age( $attempt );
			if ( ! $applied && $age < self::SETTLE_SECONDS ) {
				// The provider may still be applying a request that timed out; only time makes "not applied" evidence.
				return $this->refuse(
					Campaign_Workflow_Error::RECONCILIATION_REQUIRED,
					sprintf( 'The provider does not show the %s request yet, but it may still be applying it. Reconcile again in %d seconds.', str_replace( '_', ' ', $attempt->operation() ), self::SETTLE_SECONDS - $age ),
					$actor,
					$campaign->id(),
					$campaign,
					'failure',
					$attempt,
					null,
					Campaign_Workflow_Error::REASON_IN_PROGRESS,
					self::SETTLE_SECONDS - $age
				);
			}
			$resolved[] = $this->settled(
				$attempt,
				$applied ? Delivery_Attempt_Status::SUCCEEDED : Delivery_Attempt_Status::FAILED,
				$applied || ! in_array( $target, array( Campaign_State::PROVIDER_DRAFT, Campaign_State::SCHEDULED ), true ) ? Retryability::NOT_RETRYABLE : Retryability::RETRYABLE,
				$attempt->remote_correlation()
			);
		}

		$after = $this->follow( $campaign, $target, $remote->send_time() );
		if ( is_string( $after ) ) {
			return $this->contradiction( $actor, $campaign, $reference, $observed, $after, $unresolved, null, Campaign_Workflow_Error::REASON_CONTRADICTION );
		}
		if ( ! in_array( $campaign->state(), self::DELIVERY_STATES, true ) && Campaign_State::PROVIDER_DRAFT === $target ) {
			// The draft handoff has not finished; keep its content observation for it.
			$observed = $reference->observed_state();
		}

		$observation = $this->observation( $reference, $observed, true );
		$unexplained = $after->state() !== $campaign->state()
			&& ! in_array( $after->state(), self::PROGRESSIONS[ $campaign->state() ] ?? array(), true )
			&& array() === array_filter( $resolved, static fn ( Delivery_Attempt $attempt ): bool => Delivery_Attempt_Status::SUCCEEDED === $attempt->status() );
		$written     = $this->transaction->run(
			function () use ( $resolved, $observation, $after, $campaign, $actor, $remote, $unexplained ): bool {
				foreach ( $resolved as $attempt ) {
					if ( ! $this->attempts->update_result( $attempt ) ) {
						return false;
					}
				}

				return $this->references->update_observation( $observation )
					&& $this->campaigns->compare_and_swap( $after, $campaign->version() )
					&& $this->audits->add( $this->event( $actor, $campaign->id(), 'success', $this->context( $campaign, $after, $observation->remote_id(), $remote->status(), $resolved, $unexplained ) ) );
			}
		);

		return $written
			? Campaign_Reconcile_Result::success( $after, $observation, $resolved )
			: $this->refuse( Campaign_Workflow_Error::CONFLICT, 'The campaign changed while it was being reconciled. Reconcile again.', $actor, $campaign->id(), $this->campaigns->get( $campaign->id() ) ?? $campaign );
	}

	/**
	 * The campaign after following the provider, or why it cannot follow.
	 *
	 * @param string|null $send_time Provider send time, required for a scheduled campaign.
	 */
	private function follow( Campaign $campaign, string $target, ?string $send_time ): Campaign|string {
		$now = $this->clock->now();
		if ( ! in_array( $campaign->state(), self::DELIVERY_STATES, true ) ) {
			// Only a draft still being handed off, or a matching settled state, needs no transition.
			return $target === $campaign->state() || ( Campaign_State::PROVIDER_DRAFT === $target && Campaign_State::APPROVED === $campaign->state() )
				? $campaign->claim( $now )
				: sprintf( 'The provider reports the campaign as %s, which contradicts its local %s state. Nothing was changed; check the provider.', $target, $campaign->state() );
		}

		try {
			if ( Campaign_State::SCHEDULED === $target ) {
				return null === $send_time
					? 'The provider reports the campaign as scheduled but not when. Check the provider, then reconcile again.'
					: ( $campaign->scheduled_for() === $send_time && Campaign_State::SCHEDULED === $campaign->state() ? $campaign->claim( $now ) : $campaign->schedule_for( $send_time, $now ) );
			}

			return $target === $campaign->state() ? $campaign->claim( $now ) : $campaign->transition_to( $target, $now );
		} catch ( \InvalidArgumentException ) {
			return sprintf( 'The provider reports the campaign as %s, which the local %s state cannot follow. Nothing was changed; check the provider.', $target, $campaign->state() );
		}
	}

	/**
	 * Whether the provider's state shows an operation took effect; null when it cannot tell.
	 *
	 * @param string $target Local state the provider's status maps to.
	 */
	private function applied( string $operation, string $target ): ?bool {
		return match ( $operation ) {
			Delivery_Operation::CREATE_DRAFT => true,
			Delivery_Operation::SCHEDULE     => match ( $target ) {
				Campaign_State::PROVIDER_DRAFT => false,
				default                        => true,
			},
			Delivery_Operation::UNSCHEDULE   => Campaign_State::PROVIDER_DRAFT === $target,
			Delivery_Operation::SEND         => match ( $target ) {
				Campaign_State::PROVIDER_DRAFT => false,
				Campaign_State::SCHEDULED      => null,
				default                        => true,
			},
			default                          => null,
		};
	}

	/**
	 * Record what the provider reports, keep the campaign where it is, and refuse.
	 *
	 * The observation is stored with the campaign version claimed, so delivery
	 * that requires a draft or scheduled observation stays blocked.
	 *
	 * @param array<int, Delivery_Attempt> $unresolved Attempts that remain unresolved.
	 */
	private function contradiction( Campaign_Actor $actor, Campaign $campaign, Remote_Campaign_Reference $reference, string $observed, string $message, array $unresolved, ?Provider_Error $error, string $reason ): Campaign_Reconcile_Result {
		$observation = $this->observation( $reference, $observed, false );
		$claimed     = $campaign->claim( $this->clock->now() );
		$written     = $this->transaction->run(
			fn (): bool => $this->references->update_observation( $observation )
				&& $this->campaigns->compare_and_swap( $claimed, $campaign->version() )
				&& $this->audits->add(
					$this->event(
						$actor,
						$campaign->id(),
						'unknown',
						array_merge(
							$this->context( $campaign, $campaign, $reference->remote_id(), $observed, array(), false ),
							array(
								'error_code'          => Campaign_Workflow_Error::RECONCILIATION_REQUIRED,
								'provider_error_code' => $error?->code(),
							) 
						) 
					) 
				)
		);
		if ( ! $written ) {
			return $this->refuse( Campaign_Workflow_Error::CONFLICT, 'The campaign changed while it was being reconciled. Reconcile again.', $actor, $campaign->id(), $this->campaigns->get( $campaign->id() ) ?? $campaign );
		}

		return Campaign_Reconcile_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, $message, $reason ), $claimed, $observation, $unresolved[0] ?? null, $error );
	}

	/**
	 * Pending or unknown attempts whose outcome provider state can prove.
	 *
	 * @return array<int, Delivery_Attempt>
	 */
	private function unresolved( string $campaign_id ): array {
		return array_values(
			array_filter(
				$this->attempts->for_campaign( $campaign_id, 100 ),
				static fn ( Delivery_Attempt $attempt ): bool => in_array( $attempt->operation(), self::RECONCILABLE, true )
					&& in_array( $attempt->status(), array( Delivery_Attempt_Status::PENDING, Delivery_Attempt_Status::UNKNOWN ), true )
			)
		);
	}

	private function age( Delivery_Attempt $attempt ): int {
		return max( 0, (int) strtotime( $this->clock->now() ) - (int) strtotime( $attempt->created_at() ) );
	}

	private function observation( Remote_Campaign_Reference $reference, string $observed, bool $reconciled ): Remote_Campaign_Reference {
		return Remote_Campaign_Reference::from_array(
			array_merge(
				$reference->to_array(),
				array(
					'observed_state' => $observed,
					'observed_at'    => $this->clock->now(),
					'reconciled_at'  => $reconciled ? $this->clock->now() : null,
				)
			)
		);
	}

	private function refuse( string $code, string $message, Campaign_Actor $actor, string $campaign_id, ?Campaign $campaign = null, string $result = 'failure', ?Delivery_Attempt $attempt = null, ?Provider_Error $error = null, ?string $reason = null, ?int $retry_after = null ): Campaign_Reconcile_Result {
		try {
			$this->audits->add(
				$this->event(
					$actor,
					$campaign_id,
					$result,
					array(
						'error_code'          => $code,
						'attempt_id'          => $attempt?->id(),
						'provider_error_code' => $error?->code(),
					)
				)
			);
		} catch ( \InvalidArgumentException ) {
			// A malformed external identifier cannot become an unsafe audit record.
			unset( $result );
		}

		return Campaign_Reconcile_Result::failure( new Campaign_Workflow_Error( $code, $message, $reason, $retry_after ), $campaign, null === $campaign ? null : $this->references->get( $campaign->id(), $this->drafts->slug() ), $attempt, $error );
	}

	/**
	 * Provenance of a reconciliation: what the provider reported and what changed.
	 *
	 * @param array<int, Delivery_Attempt> $resolved Attempts settled by this reconciliation.
	 * @return array<string, mixed>
	 */
	private function context( Campaign $before, Campaign $after, ?string $remote_id, string $remote_status, array $resolved, bool $unexplained ): array {
		return array(
			'provider'       => $this->drafts->slug(),
			'remote_id'      => $remote_id,
			'remote_status'  => $remote_status,
			'from_state'     => $before->state(),
			'to_state'       => $after->state(),
			'scheduled_for'  => $after->scheduled_for(),
			'attempt_id'     => ( $resolved[0] ?? null )?->id(),
			'attempt_status' => ( $resolved[0] ?? null )?->status(),
			'resolved_count' => count( $resolved ),
			// The provider changed state with no CampaignBridge request explaining it.
			'unexplained'    => $unexplained,
		);
	}

	/** @param array<string, mixed> $context Safe audit context. */
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

	private function settled( Delivery_Attempt $attempt, string $status, string $retryability, ?string $correlation ): Delivery_Attempt {
		return Delivery_Attempt::from_array(
			array_merge(
				$attempt->to_array(),
				array(
					'status'             => $status,
					'retryability'       => $retryability,
					'remote_correlation' => $correlation,
					// Reconciliation runs in a later request, possibly on a machine
					// whose clock is a moment behind the one that recorded the attempt.
					'updated_at'         => max( $this->clock->now(), $attempt->updated_at() ),
				)
			)
		);
	}
}
