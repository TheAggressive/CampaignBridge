<?php
/**
 * Explicit campaign workflow actor and granted authority.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Carries adapter-resolved authority without consulting global user state. */
final class Campaign_Actor {
	/**
	 * Build the campaign actor.
	 *
	 * @param int  $user_id        User ID.
	 * @param bool $can_create     Whether the user may create campaigns.
	 * @param bool $can_manage_all Whether the user manages every campaign.
	 * @param bool $can_approve    Whether the user may approve and deliver.
	 * @param bool $can_test       Whether the user may send tests.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public function __construct(
		private readonly int $user_id,
		private readonly bool $can_create,
		private readonly bool $can_manage_all,
		private readonly bool $can_approve,
		private readonly bool $can_test = false
	) {
		if ( 1 > $user_id ) {
			throw new \InvalidArgumentException( 'Campaign actor must be a positive user ID.' );
		}
	}

	/**
	 * The actor's user ID.
	 */
	public function user_id(): int {
		return $this->user_id;
	}

	/**
	 * Whether the actor can create.
	 */
	public function can_create(): bool {
		return $this->can_create;
	}

	/**
	 * Whether the actor manages this campaign: their own, or any with the management capability.
	 *
	 * @param Campaign $campaign The campaign as read.
	 */
	public function can_manage( Campaign $campaign ): bool {
		return $this->can_manage_all || ( $this->can_create && $this->user_id === $campaign->owner_user_id() );
	}

	/**
	 * Whether the actor may approve this campaign.
	 *
	 * @param Campaign $campaign The campaign as read.
	 */
	public function can_approve( Campaign $campaign ): bool {
		return $this->can_approve && $this->can_manage( $campaign );
	}

	/**
	 * Scheduling, sending, and unscheduling share the approval capability.
	 *
	 * @param Campaign $campaign The campaign as read.
	 */
	public function can_deliver( Campaign $campaign ): bool {
		return $this->can_approve( $campaign );
	}

	/**
	 * Test sends are separate from approval and production send authority.
	 *
	 * @param Campaign $campaign The campaign as read.
	 */
	public function can_test( Campaign $campaign ): bool {
		return $this->can_test && $this->can_manage( $campaign );
	}

	/**
	 * Whether the actor can manage all.
	 */
	public function can_manage_all(): bool {
		return $this->can_manage_all;
	}
}
