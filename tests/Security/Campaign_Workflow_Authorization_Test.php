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
}
