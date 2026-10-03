<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Public operation names and typed signatures form the application contract.
/**
 * Test delivery of an approved campaign's remote draft.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Audit_Context;
use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Audit_Event_Source;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_Snapshot;
use CampaignBridge\Domain\Campaign\Campaign_Snapshot_Source;
use CampaignBridge\Domain\Campaign\Campaign_Source;
use CampaignBridge\Domain\Campaign\Campaign_State;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Domain\Campaign\Delivery_Attempt_Source;
use CampaignBridge\Domain\Campaign\Delivery_Attempt_Status;
use CampaignBridge\Domain\Campaign\Delivery_Operation;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference_Source;
use CampaignBridge\Domain\Campaign\Retryability;
use CampaignBridge\Domain\Provider\Provider_Capabilities;
use CampaignBridge\Domain\Provider\Provider_Operation;
use CampaignBridge\Domain\Provider\Provider_Test_Gateway;
use CampaignBridge\Domain\Provider\Test_Delivery;
use CampaignBridge\Domain\Provider\Test_Outcome;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a test of the remote draft created from the approved artifact.
 *
 * Protocol:
 *
 * 1. Only a `provider_draft` campaign whose remote draft is confirmed can be
 *    tested. The test sends what the provider holds for that draft; editor
 *    HTML is never sent.
 * 2. Recipients are validated and bounded before anything is recorded, and
 *    are never persisted: attempts and audit events record only their count.
 * 3. A durable per-campaign quota bounds tests independently of the
 *    per-user transport limit.
 * 4. Write-ahead. A `pending` test_send attempt is stored before the call.
 *    Its idempotency key identifies one test request: repeating the key
 *    returns the recorded outcome and never sends again.
 * 5. A test is not idempotent, so an unconfirmed outcome is recorded as
 *    `unknown` and never retried. It does not block later tests with a new
 *    key, because a duplicate test reaches only named test addresses.
 *
 * A test never changes the campaign's lifecycle state or version.
 */
final class Campaign_Test_Delivery {
	/** Most test sends one campaign may attempt in a rolling window. */
	public const QUOTA = 10;

	/** Rolling quota window in seconds. */
	public const QUOTA_WINDOW = 86400;

	private const ACTION = 'campaign_test_send';

	public function __construct(
		private readonly Campaign_Source $campaigns,
		private readonly Campaign_Snapshot_Source $snapshots,
		private readonly Remote_Campaign_Reference_Source $references,
		private readonly Delivery_Attempt_Source $attempts,
		private readonly Audit_Event_Source $audits,
		private readonly Campaign_Id_Generator $ids,
		private readonly Campaign_Clock $clock,
		private readonly Provider_Test_Gateway $gateway,
		private readonly Provider_Capabilities $capabilities
	) {}

	/**
	 * Send, or replay, one test of the campaign's remote draft.
	 *
	 * @param array<mixed>         $recipients Candidate test addresses; used for this call only.
	 * @param array<string, mixed> $settings   Decrypted provider settings for this call only.
	 */
	public function send_test( Campaign_Actor $actor, string $campaign_id, array $recipients, string $format, string $idempotency_key, array $settings ): Campaign_Test_Result {
		$campaign = $this->campaigns->get( $campaign_id );
		if ( null === $campaign ) {
			return $this->refuse( Campaign_Workflow_Error::NOT_FOUND, 'Campaign was not found.', $actor, $campaign_id );
		}
		if ( ! $actor->can_test( $campaign ) ) {
			return $this->refuse( Campaign_Workflow_Error::FORBIDDEN, 'Campaign operation is not allowed.', $actor, $campaign_id, $campaign, 'denied' );
		}
		if ( '' === $idempotency_key || 191 < strlen( $idempotency_key ) ) {
			return $this->refuse( Campaign_Workflow_Error::INVALID_INPUT, 'A bounded idempotency key is required.', $actor, $campaign_id, $campaign );
		}
		try {
			$delivery = Test_Delivery::create( $recipients, $format );
		} catch ( \InvalidArgumentException ) {
			return $this->refuse( Campaign_Workflow_Error::INVALID_INPUT, sprintf( 'A test needs between 1 and %d valid email addresses and a known format.', Test_Delivery::MAX_RECIPIENTS ), $actor, $campaign_id, $campaign );
		}
		$provider = $this->gateway->slug();
		if ( ! $this->capabilities->supports( Provider_Operation::SEND_TEST ) || $provider !== $campaign->provider() ) {
			return $this->refuse( Campaign_Workflow_Error::INVALID_INPUT, 'The campaign must target a provider that supports test sends.', $actor, $campaign_id, $campaign );
		}

		$reference = $this->references->get( $campaign_id, $provider );
		$recorded  = $this->attempts->find_idempotency( $campaign_id, Delivery_Operation::TEST_SEND, $idempotency_key );
		if ( null !== $recorded ) {
			return $this->replay( $actor, $campaign, $reference, $recorded );
		}

		if ( Campaign_State::PROVIDER_DRAFT !== $campaign->state() || null === $reference || Campaign_Draft_Handoff::OBSERVED_DRAFT !== $reference->observed_state() ) {
			return $this->refuse( Campaign_Workflow_Error::INVALID_STATE, 'Only a campaign whose provider draft has been created can send a test.', $actor, $campaign_id, $campaign );
		}
		$snapshot = null === $campaign->active_snapshot_id() ? null : $this->snapshots->get( $campaign->active_snapshot_id() );
		if ( null === $snapshot || $snapshot->campaign_id() !== $campaign_id ) {
			return $this->refuse( Campaign_Workflow_Error::MISSING_SNAPSHOT, 'Campaign snapshot is unavailable.', $actor, $campaign_id, $campaign );
		}
		if ( self::QUOTA <= $this->recent_tests( $campaign_id ) ) {
			return $this->refuse( Campaign_Workflow_Error::RATE_LIMITED, sprintf( 'This campaign has reached its limit of %d test sends per day.', self::QUOTA ), $actor, $campaign_id, $campaign );
		}

		$attempt = $this->attempt( $this->ids->generate( 'attempt' ), $campaign_id, $idempotency_key, Delivery_Attempt_Status::PENDING, Retryability::UNKNOWN, $reference->remote_id(), null );
		if ( ! $this->attempts->add( $attempt ) ) {
			// A concurrent request claimed this key first; its outcome is not ours to assume.
			return $this->refuse( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'Another test request with this key is already in progress.', $actor, $campaign_id, $campaign );
		}

		$outcome = $this->gateway->send_test( $settings, $reference->remote_id(), $delivery );

		return match ( $outcome->status() ) {
			Test_Outcome::SENT   => $this->sent( $actor, $campaign, $reference, $attempt, $snapshot, $delivery ),
			Test_Outcome::FAILED => $this->definite_failure( $actor, $campaign, $reference, $attempt, $snapshot, $delivery, $outcome->error() ),
			default              => $this->ambiguous( $actor, $campaign, $reference, $attempt, $snapshot, $delivery, $outcome->error() ),
		};
	}

	/** Report the recorded outcome of a repeated key; nothing is sent. */
	private function replay( Campaign_Actor $actor, Campaign $campaign, ?Remote_Campaign_Reference $reference, Delivery_Attempt $recorded ): Campaign_Test_Result {
		return match ( $recorded->status() ) {
			Delivery_Attempt_Status::SUCCEEDED => Campaign_Test_Result::replay( $campaign, $reference, $recorded ),
			Delivery_Attempt_Status::FAILED    => $this->refuse( Campaign_Workflow_Error::PROVIDER_FAILED, 'This idempotency key belongs to a test the provider refused. Use a new key to try again.', $actor, $campaign->id(), $campaign, 'failure', $recorded ),
			default                            => $this->refuse( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'This idempotency key belongs to a test whose delivery was not confirmed. Check the test inboxes, then use a new key to send another test.', $actor, $campaign->id(), $campaign, 'failure', $recorded ),
		};
	}

	/** Test sends attempted for this campaign within the rolling quota window. */
	private function recent_tests( string $campaign_id ): int {
		$since = gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $this->clock->now() ) - self::QUOTA_WINDOW );

		return count(
			array_filter(
				$this->attempts->for_campaign( $campaign_id, 100 ),
				static fn ( Delivery_Attempt $attempt ): bool => Delivery_Operation::TEST_SEND === $attempt->operation() && $attempt->created_at() >= $since
			)
		);
	}

	private function sent( Campaign_Actor $actor, Campaign $campaign, Remote_Campaign_Reference $reference, Delivery_Attempt $attempt, Campaign_Snapshot $snapshot, Test_Delivery $delivery ): Campaign_Test_Result {
		$succeeded = $this->settled( $attempt, Delivery_Attempt_Status::SUCCEEDED, Retryability::NOT_RETRYABLE );
		if ( ! $this->attempts->update_result( $succeeded ) ) {
			$this->audit( $actor, $campaign, 'unknown', $reference, $attempt, $snapshot, $delivery, null );

			return Campaign_Test_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'The provider accepted the test, but its outcome could not be recorded.' ), $campaign, $reference, $attempt );
		}
		$this->audit( $actor, $campaign, 'success', $reference, $succeeded, $snapshot, $delivery, null );

		return Campaign_Test_Result::sent( $campaign, $reference, $succeeded, $snapshot, $delivery );
	}

	private function definite_failure( Campaign_Actor $actor, Campaign $campaign, Remote_Campaign_Reference $reference, Delivery_Attempt $attempt, Campaign_Snapshot $snapshot, Test_Delivery $delivery, ?Provider_Error $error ): Campaign_Test_Result {
		$failed = $this->settled( $attempt, Delivery_Attempt_Status::FAILED, null !== $error && $error->is_retryable() ? Retryability::RETRYABLE : Retryability::NOT_RETRYABLE );
		$stored = $this->attempts->update_result( $failed );
		$this->audit( $actor, $campaign, 'failure', $reference, $stored ? $failed : $attempt, $snapshot, $delivery, $error );

		return Campaign_Test_Result::failure( new Campaign_Workflow_Error( Campaign_Workflow_Error::PROVIDER_FAILED, 'The provider refused the test. No test was sent.' ), $campaign, $reference, $stored ? $failed : $attempt, $error );
	}

	private function ambiguous( Campaign_Actor $actor, Campaign $campaign, Remote_Campaign_Reference $reference, Delivery_Attempt $attempt, Campaign_Snapshot $snapshot, Test_Delivery $delivery, ?Provider_Error $error ): Campaign_Test_Result {
		$unknown = $this->settled( $attempt, Delivery_Attempt_Status::UNKNOWN, Retryability::UNKNOWN );
		$stored  = $this->attempts->update_result( $unknown );
		$this->audit( $actor, $campaign, 'unknown', $reference, $stored ? $unknown : $attempt, $snapshot, $delivery, $error );

		return Campaign_Test_Result::failure(
			new Campaign_Workflow_Error( Campaign_Workflow_Error::RECONCILIATION_REQUIRED, 'The provider did not confirm whether the test was sent. It will not be retried automatically; check the test inboxes before sending another test with a new key.' ),
			$campaign,
			$reference,
			$stored ? $unknown : $attempt,
			$error
		);
	}

	private function refuse( string $code, string $message, Campaign_Actor $actor, string $campaign_id, ?Campaign $campaign = null, string $result = 'failure', ?Delivery_Attempt $attempt = null ): Campaign_Test_Result {
		try {
			$this->audits->add( $this->event( $actor, $campaign_id, $result, array( 'error_code' => $code ) ) );
		} catch ( \InvalidArgumentException ) {
			// A malformed external identifier cannot become an unsafe audit record.
			unset( $result );
		}

		return Campaign_Test_Result::failure( new Campaign_Workflow_Error( $code, $message ), $campaign, null, $attempt );
	}

	/** Record the tested artifact and request size; never the recipients. */
	private function audit( Campaign_Actor $actor, Campaign $campaign, string $result, Remote_Campaign_Reference $reference, Delivery_Attempt $attempt, Campaign_Snapshot $snapshot, Test_Delivery $delivery, ?Provider_Error $error ): void {
		$this->audits->add(
			$this->event(
				$actor,
				$campaign->id(),
				$result,
				array(
					'provider'          => $reference->provider(),
					'remote_id'         => $reference->remote_id(),
					'attempt_id'        => $attempt->id(),
					'attempt_status'    => $attempt->status(),
					'snapshot_id'       => $snapshot->id(),
					'fingerprint'       => $snapshot->artifact()->fingerprint(),
					'campaign_state'    => $campaign->state(),
					'test_format'       => $delivery->format(),
					'destination_count' => $delivery->recipient_count(),
					'error_category'    => $error?->category(),
					'error_code'        => $error?->code(),
				)
			)
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

	private function settled( Delivery_Attempt $attempt, string $status, string $retryability ): Delivery_Attempt {
		return $this->attempt( $attempt->id(), $attempt->campaign_id(), (string) $attempt->idempotency_key(), $status, $retryability, $attempt->remote_correlation(), $attempt->created_at() );
	}

	private function attempt( string $id, string $campaign_id, string $key, string $status, string $retryability, ?string $correlation, ?string $created_at ): Delivery_Attempt {
		$now = $this->clock->now();

		return Delivery_Attempt::from_array(
			array(
				'schema_version'     => Delivery_Attempt::SCHEMA_VERSION,
				'id'                 => $id,
				'campaign_id'        => $campaign_id,
				'operation'          => Delivery_Operation::TEST_SEND,
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
