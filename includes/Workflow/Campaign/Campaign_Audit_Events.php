<?php
/**
 * Construction and safe recording of campaign audit events.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Audit_Context;
use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Audit_Event_Source;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Remote_Campaign_Reference;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Builds canonical audit records without changing campaign or delivery state. */
final class Campaign_Audit_Events {
	/**
	 * Build the audit writer without recording events.
	 *
	 * @param Audit_Event_Source    $audits Audit event storage.
	 * @param Campaign_Id_Generator $ids    Identifier generator.
	 * @param Campaign_Clock        $clock Source of the current time.
	 */
	public function __construct(
		private readonly Audit_Event_Source $audits,
		private readonly Campaign_Id_Generator $ids,
		private readonly Campaign_Clock $clock
	) {}

	/**
	 * Append a failure audit event.
	 *
	 * @param Campaign_Actor       $actor     Who is acting, with their resolved campaign authority.
	 * @param string               $action    Audit action name.
	 * @param string               $target_id ID of the record the event is about.
	 * @param string               $code      Stable error code.
	 * @param array<string, mixed> $context   Safe audit context.
	 * @param string               $result    Audit result: success, failure, denied, or unknown.
	 */
	public function failure(
		Campaign_Actor $actor,
		string $action,
		string $target_id,
		string $code,
		array $context = array(),
		string $result = 'failure'
	): void {
		try {
			$this->audits->add( $this->event( $actor, $action, $target_id, $result, array_merge( array( 'error_code' => $code ), $context ) ) );
		} catch ( \InvalidArgumentException ) {
			// Malformed external identifiers cannot become an unsafe audit record.
			return;
		}
	}

	/**
	 * Build one workflow audit event.
	 *
	 * @param Campaign_Actor       $actor     Who is acting, with their resolved campaign authority.
	 * @param string               $action    Audit action name.
	 * @param string               $target_id ID of the record the event is about.
	 * @param string               $result    Audit result: success, failure, denied, or unknown.
	 * @param array<string, mixed> $context   Safe audit context.
	 */
	public function event( Campaign_Actor $actor, string $action, string $target_id, string $result, array $context ): Audit_Event {
		return Audit_Event::from_array(
			array(
				'schema_version' => Audit_Event::SCHEMA_VERSION,
				'id'             => $this->ids->generate( 'audit' ),
				'actor_user_id'  => $actor->user_id(),
				'action'         => $action,
				'target_type'    => 'campaign',
				'target_id'      => $target_id,
				'result'         => $result,
				'context'        => Audit_Context::from_array( $context )->to_array(),
				'created_at'     => $this->clock->now(),
			)
		);
	}

	/**
	 * Identify the actor's operation, artifact, remote reference, and normalized result.
	 *
	 * @param Campaign                   $before    The campaign before the change.
	 * @param Campaign                   $after     The campaign after the change.
	 * @param Remote_Campaign_Reference  $reference The campaign's remote reference.
	 * @param Delivery_Attempt           $attempt   The delivery attempt.
	 * @param array<string, string|null> $extra     Operation-specific context.
	 * @param Provider_Error|null        $error     Normalized provider error, when there is one.
	 * @return array<string, mixed>
	 */
	public function delivery_context( Campaign $before, Campaign $after, Remote_Campaign_Reference $reference, Delivery_Attempt $attempt, array $extra, ?Provider_Error $error ): array {
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
}
