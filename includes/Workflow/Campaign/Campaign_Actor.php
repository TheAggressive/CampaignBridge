<?php // phpcs:disable Squiz.Commenting.FunctionComment
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
	public function __construct(
		private readonly int $user_id,
		private readonly bool $can_create,
		private readonly bool $can_manage_all,
		private readonly bool $can_approve
	) {
		if ( 1 > $user_id ) {
			throw new \InvalidArgumentException( 'Campaign actor must be a positive user ID.' );
		}
	}

	public function user_id(): int {
		return $this->user_id;
	}

	public function can_create(): bool {
		return $this->can_create;
	}

	public function can_manage( Campaign $campaign ): bool {
		return $this->can_manage_all || ( $this->can_create && $this->user_id === $campaign->owner_user_id() );
	}

	public function can_approve( Campaign $campaign ): bool {
		return $this->can_approve && $this->can_manage( $campaign );
	}

	public function can_manage_all(): bool {
		return $this->can_manage_all;
	}
}
