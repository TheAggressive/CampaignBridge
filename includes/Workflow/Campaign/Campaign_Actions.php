<?php
/**
 * Actions an actor may take on a campaign now.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_State;
use CampaignBridge\Domain\Campaign\Campaign_State_Machine;
use CampaignBridge\Domain\Campaign\Delivery_Policy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Derives the actions an operator screen may offer from the actor's authority
 * and the campaign state machine, so screens never restate those rules.
 *
 * An offered action can still be refused when it runs (for example, a
 * concurrent change makes the version stale); the workflow remains the
 * authority. Actions not listed here are not offered yet.
 */
final class Campaign_Actions {
	public const EDIT            = 'edit';
	public const SNAPSHOT        = 'snapshot';
	public const SUBMIT          = 'submit';
	public const APPROVE         = 'approve';
	public const REVOKE_APPROVAL = 'revoke_approval';
	public const PROVIDER_DRAFT  = 'create_provider_draft';
	public const TEST_SEND       = 'test_send';
	public const SCHEDULE        = 'schedule';
	public const UNSCHEDULE      = 'unschedule';
	public const SEND            = 'send';
	public const RECONCILE       = 'reconcile';
	public const ARCHIVE         = 'archive';
	public const DUPLICATE       = 'duplicate';

	/** Actions the workflow refuses without edit access to the campaign's template. */
	private const TEMPLATE_ACTIONS = array( self::EDIT, self::SNAPSHOT, self::SUBMIT, self::APPROVE, self::DUPLICATE );

	/** States in which the provider may hold a campaign whose outcome can be read back. */
	private const RECONCILABLE = array( Campaign_State::PROVIDER_DRAFT, Campaign_State::SCHEDULED, Campaign_State::SENDING, Campaign_State::UNKNOWN );

	/**
	 * Actions available to the actor on the campaign, in a stable order.
	 *
	 * Delivery actions also follow the site's separation-of-duties policy, so
	 * the approver of a campaign is not offered schedule or send when the
	 * policy requires a second person. Actions that read the template are
	 * offered only to an actor the workflow lets use that template.
	 *
	 * @param Campaign_Actor       $actor           Reader.
	 * @param Campaign             $campaign        Campaign as read.
	 * @param Delivery_Policy|null $policy          Site delivery policy; null applies none.
	 * @param bool                 $template_access Whether the actor may use the campaign's template.
	 * @return array<int, string>
	 */
	public static function for( Campaign_Actor $actor, Campaign $campaign, ?Delivery_Policy $policy = null, bool $template_access = true ): array {
		if ( ! $actor->can_manage( $campaign ) ) {
			return array();
		}
		if ( ! $template_access ) {
			return array_values( array_diff( self::for( $actor, $campaign, $policy ), self::TEMPLATE_ACTIONS ) );
		}

		$state   = $campaign->state();
		$actions = array();
		// Template, targeting, and snapshot changes are allowed while local review is still open.
		if ( Campaign_State_Machine::is_editable( $state ) ) {
			$actions[] = self::EDIT;
			$actions[] = self::SNAPSHOT;
		}
		if ( Campaign_State::DRAFT === $state && null !== $campaign->active_snapshot_id() ) {
			$actions[] = self::SUBMIT;
		}
		if ( Campaign_State::READY_FOR_REVIEW === $state && $actor->can_approve( $campaign ) ) {
			$actions[] = self::APPROVE;
		}
		if ( Campaign_State::APPROVED === $state ) {
			$actions[] = self::REVOKE_APPROVAL;
		}
		array_push( $actions, ...self::delivery( $actor, $campaign, $policy ) );
		if ( Campaign_State_Machine::can_transition( $state, Campaign_State::ARCHIVED ) ) {
			$actions[] = self::ARCHIVE;
		}
		if ( $actor->can_create() ) {
			$actions[] = self::DUPLICATE;
		}

		return $actions;
	}

	/**
	 * Provider handoff, test, delivery, and reconciliation actions.
	 *
	 * @param Campaign_Actor       $actor    Reader.
	 * @param Campaign             $campaign Campaign as read.
	 * @param Delivery_Policy|null $policy   Site delivery policy.
	 * @return array<int, string>
	 */
	private static function delivery( Campaign_Actor $actor, Campaign $campaign, ?Delivery_Policy $policy ): array {
		if ( null === $campaign->provider() || null === $campaign->audience_reference() ) {
			return array();
		}

		$state       = $campaign->state();
		$can_deliver = $actor->can_deliver( $campaign );
		$independent = null === $policy || $policy->allows_delivery_by( $actor->user_id(), $campaign->approved_by_user_id() );
		$actions     = array();
		if ( Campaign_State::APPROVED === $state && $actor->can_approve( $campaign ) ) {
			$actions[] = self::PROVIDER_DRAFT;
		}
		if ( Campaign_State::PROVIDER_DRAFT === $state && $actor->can_test( $campaign ) ) {
			$actions[] = self::TEST_SEND;
		}
		if ( Campaign_State::PROVIDER_DRAFT === $state && $can_deliver && $independent ) {
			$actions[] = self::SCHEDULE;
			$actions[] = self::SEND;
		}
		if ( Campaign_State::SCHEDULED === $state && $can_deliver ) {
			$actions[] = self::UNSCHEDULE;
		}
		if ( in_array( $state, self::RECONCILABLE, true ) && $can_deliver ) {
			$actions[] = self::RECONCILE;
		}

		return $actions;
	}

	/**
	 * Every action name the contract can return.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array( self::EDIT, self::SNAPSHOT, self::SUBMIT, self::APPROVE, self::REVOKE_APPROVAL, self::PROVIDER_DRAFT, self::TEST_SEND, self::SCHEDULE, self::UNSCHEDULE, self::SEND, self::RECONCILE, self::ARCHIVE, self::DUPLICATE );
	}
}
