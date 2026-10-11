<?php
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
	/**
	 * Resolve a user's campaign authority from their WordPress capabilities.
	 *
	 * @param int $user_id User ID.
	 */
	public function actor( int $user_id ): Campaign_Actor {
		return new Campaign_Actor(
			$user_id,
			user_can( $user_id, Capabilities::CREATE_CAMPAIGNS ),
			user_can( $user_id, Capabilities::MANAGE ),
			user_can( $user_id, Capabilities::SEND_CAMPAIGNS ),
			user_can( $user_id, Capabilities::TEST_CAMPAIGNS )
		);
	}

	/**
	 * Whether the actor may edit the email template.
	 *
	 * @param Campaign_Actor $actor       Who is acting, with their resolved campaign authority.
	 * @param int            $template_id Email template post ID.
	 */
	public function can_use_template( Campaign_Actor $actor, int $template_id ): bool {
		$template = get_post( $template_id );

		return $template instanceof \WP_Post
			&& Post_Type_Email_Template::POST_TYPE === $template->post_type
			&& user_can( $actor->user_id(), 'edit_post', $template_id );
	}
}
