<?php
/**
 * Campaign workflow authorization boundary tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Security;

use CampaignBridge\Core\Campaign_Authorizer;
use CampaignBridge\Core\Capabilities;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Tests\Helpers\Test_Case;

/** Proves adapters resolve granular capabilities before workflow execution. */
final class Campaign_Workflow_Authorization_Test extends Test_Case {
	public function test_campaign_and_approval_authority_remain_separate(): void {
		$owner_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$other_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$owned    = Campaign::create( 'owned-campaign', $owner_id, 42, null, null, '2026-09-28T12:00:00Z' );
		$other    = Campaign::create( 'other-campaign', $other_id, 42, null, null, '2026-09-28T12:00:00Z' );
		$adapter  = new Campaign_Authorizer();

		$denied = $adapter->actor( $owner_id );
		self::assertFalse( $denied->can_create() );
		self::assertFalse( $denied->can_manage( $owned ) );
		self::assertFalse( $denied->can_approve( $owned ) );

		$user = get_user_by( 'id', $owner_id );
		self::assertInstanceOf( \WP_User::class, $user );
		$user->add_cap( Capabilities::CREATE_CAMPAIGNS );
		$editor = $adapter->actor( $owner_id );
		self::assertTrue( $editor->can_create() );
		self::assertTrue( $editor->can_manage( $owned ) );
		self::assertFalse( $editor->can_manage( $other ) );
		self::assertFalse( $editor->can_approve( $owned ) );

		$user->add_cap( Capabilities::SEND_CAMPAIGNS );
		$approver = $adapter->actor( $owner_id );
		self::assertTrue( $approver->can_approve( $owned ) );
		self::assertFalse( $approver->can_approve( $other ) );
	}

	public function test_campaign_capability_does_not_grant_template_object_access(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$user    = get_user_by( 'id', $user_id );
		self::assertInstanceOf( \WP_User::class, $user );
		$user->add_cap( Capabilities::CREATE_CAMPAIGNS );

		$template_id = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Restricted campaign template',
				'post_content' => '<!-- wp:campaignbridge/container /-->',
			)
		);
		$normal_post = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$adapter     = new Campaign_Authorizer();
		$actor       = $adapter->actor( $user_id );

		self::assertTrue( $actor->can_create() );
		self::assertFalse( $adapter->can_use_template( $actor, $template_id ) );

		$user->add_cap( Capabilities::EDIT_TEMPLATES );
		$authorized = $adapter->actor( $user_id );
		self::assertTrue( user_can( $user_id, 'edit_post', $template_id ) );
		self::assertTrue( $adapter->can_use_template( $authorized, $template_id ) );
		self::assertFalse( $adapter->can_use_template( $authorized, $normal_post ) );
	}
}
