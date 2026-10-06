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
	public const ARCHIVE         = 'archive';
	public const DUPLICATE       = 'duplicate';

	/**
	 * Actions available to the actor on the campaign, in a stable order.
	 *
	 * @param Campaign_Actor $actor    Reader.
	 * @param Campaign       $campaign Campaign as read.
	 * @return array<int, string>
	 */
	public static function for( Campaign_Actor $actor, Campaign $campaign ): array {
		if ( ! $actor->can_manage( $campaign ) ) {
			return array();
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
		if ( Campaign_State_Machine::can_transition( $state, Campaign_State::ARCHIVED ) ) {
			$actions[] = self::ARCHIVE;
		}
		if ( $actor->can_create() ) {
			$actions[] = self::DUPLICATE;
		}

		return $actions;
	}

	/**
	 * Every action name the contract can return.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array( self::EDIT, self::SNAPSHOT, self::SUBMIT, self::APPROVE, self::REVOKE_APPROVAL, self::ARCHIVE, self::DUPLICATE );
	}
}
