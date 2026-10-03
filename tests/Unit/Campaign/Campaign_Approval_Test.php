<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Campaign approver invariant tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_State;
use WP_UnitTestCase;

/** Proves the recorded approver lasts exactly as long as the approval. */
final class Campaign_Approval_Test extends WP_UnitTestCase {
	private static function in_review(): Campaign {
		$data = Campaign::create( 'campaign-one', 7, 42, 'mailchimp', 'abc123', '2026-10-05T12:00:00Z' )->to_array();

		return Campaign::from_array( array_merge( $data, array( 'state' => Campaign_State::READY_FOR_REVIEW ) ) );
	}

	public function test_approval_records_the_approver_through_delivery(): void {
		$approved = self::in_review()->approve_by( 9, '2026-10-05T12:01:00Z' );
		self::assertSame( array( 'approved', 9 ), array( $approved->state(), $approved->approved_by_user_id() ) );

		$draft     = $approved->transition_to( Campaign_State::PROVIDER_DRAFT, '2026-10-05T12:02:00Z' );
		$claimed   = $draft->claim( '2026-10-05T12:03:00Z' );
		$scheduled = $claimed->schedule_for( '2026-10-05T15:00:00Z', '2026-10-05T12:04:00Z' );
		$unknown   = $scheduled->transition_to( Campaign_State::UNKNOWN, '2026-10-05T12:05:00Z' );
		foreach ( array( $draft, $claimed, $scheduled, $unknown ) as $campaign ) {
			self::assertSame( 9, $campaign->approved_by_user_id(), $campaign->state() );
		}
	}

	public function test_returning_to_review_or_draft_clears_the_approver(): void {
		$approved = self::in_review()->approve_by( 9, '2026-10-05T12:01:00Z' );

		self::assertNull( $approved->transition_to( Campaign_State::READY_FOR_REVIEW, '2026-10-05T12:02:00Z' )->approved_by_user_id() );
		self::assertNull( $approved->transition_to( Campaign_State::DRAFT, '2026-10-05T12:02:00Z' )->approved_by_user_id() );
		self::assertNull( $approved->select_audience( 'mailchimp', 'xyz789', '2026-10-05T12:02:00Z' )->approved_by_user_id(), 'Retargeting revokes approval and its approver.' );
		self::assertNull( $approved->edit_template( 43, '2026-10-05T12:02:00Z' )->approved_by_user_id() );
	}

	public function test_an_approver_cannot_be_stored_before_approval(): void {
		$data = array_merge( self::in_review()->to_array(), array( 'approved_by_user_id' => 9 ) );

		$this->expectException( \InvalidArgumentException::class );
		Campaign::from_array( $data );
	}

	public function test_only_a_campaign_in_review_can_be_approved(): void {
		$this->expectException( \InvalidArgumentException::class );
		Campaign::create( 'campaign-one', 7, 42, null, null, '2026-10-05T12:00:00Z' )->approve_by( 9, '2026-10-05T12:01:00Z' );
	}
}
