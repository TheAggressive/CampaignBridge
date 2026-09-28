<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * WordPress capability adapter for campaign workflows.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

use CampaignBridge\Workflow\Campaign\Campaign_Actor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolves granular WordPress capabilities before entering the workflow layer. */
final class Campaign_Authorizer {
	public function actor( int $user_id ): Campaign_Actor {
		return new Campaign_Actor(
			$user_id,
			user_can( $user_id, Capabilities::CREATE_CAMPAIGNS ),
			user_can( $user_id, Capabilities::MANAGE ),
			user_can( $user_id, Capabilities::SEND_CAMPAIGNS )
		);
	}
}
