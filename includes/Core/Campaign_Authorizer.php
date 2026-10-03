<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * WordPress capability adapter for campaign workflows.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Workflow\Campaign\Campaign_Actor;
use CampaignBridge\Workflow\Campaign\Campaign_Template_Authority;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolves granular WordPress capabilities before entering the workflow layer. */
final class Campaign_Authorizer implements Campaign_Template_Authority {
	public function actor( int $user_id ): Campaign_Actor {
		return new Campaign_Actor(
			$user_id,
			user_can( $user_id, Capabilities::CREATE_CAMPAIGNS ),
			user_can( $user_id, Capabilities::MANAGE ),
			user_can( $user_id, Capabilities::SEND_CAMPAIGNS ),
			user_can( $user_id, Capabilities::TEST_CAMPAIGNS )
		);
	}

	public function can_use_template( Campaign_Actor $actor, int $template_id ): bool {
		$template = get_post( $template_id );

		return $template instanceof \WP_Post
			&& Post_Type_Email_Template::POST_TYPE === $template->post_type
			&& user_can( $actor->user_id(), 'edit_post', $template_id );
	}
}
