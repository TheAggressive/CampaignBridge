<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Campaign delivery-time invariant tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_State;
use WP_UnitTestCase;

/** Proves a delivery time exists only where the lifecycle gives it meaning. */
final class Campaign_Schedule_Test extends WP_UnitTestCase {
	private static function provider_draft(): Campaign {
		$data = Campaign::create( 'campaign-one', 7, 42, 'mailchimp', 'abc123', '2026-10-05T12:00:00Z' )->to_array();

		return Campaign::from_array( array_merge( $data, array( 'state' => Campaign_State::PROVIDER_DRAFT ) ) );
	}

	public function test_scheduling_records_the_time_and_unscheduling_clears_it(): void {
		$scheduled = self::provider_draft()->schedule_for( '2026-10-05T15:00:00Z', '2026-10-05T12:01:00Z' );
		self::assertSame( array( 'scheduled', 2, '2026-10-05T15:00:00Z' ), array( $scheduled->state(), $scheduled->version(), $scheduled->scheduled_for() ) );

		$unknown = $scheduled->transition_to( Campaign_State::UNKNOWN, '2026-10-05T12:02:00Z' );
		self::assertSame( '2026-10-05T15:00:00Z', $unknown->scheduled_for(), 'An unconfirmed outcome keeps the intended time for reconciliation.' );

		$unscheduled = $scheduled->transition_to( Campaign_State::PROVIDER_DRAFT, '2026-10-05T12:02:00Z' );
		self::assertSame( array( 'provider_draft', null ), array( $unscheduled->state(), $unscheduled->scheduled_for() ) );
	}

	public function test_a_claim_consumes_one_version_and_changes_nothing_else(): void {
		$scheduled = self::provider_draft()->schedule_for( '2026-10-05T15:00:00Z', '2026-10-05T12:01:00Z' );
		$claimed   = $scheduled->claim( '2026-10-05T12:03:00Z' );

		self::assertSame( $scheduled->version() + 1, $claimed->version() );
		self::assertSame(
			array_diff_key( $scheduled->to_array(), array_flip( array( 'version', 'updated_at' ) ) ),
			array_diff_key( $claimed->to_array(), array_flip( array( 'version', 'updated_at' ) ) )
		);
	}

	public function test_only_a_provider_draft_can_be_scheduled(): void {
		$this->expectException( \InvalidArgumentException::class );
		Campaign::create( 'campaign-one', 7, 42, 'mailchimp', 'abc123', '2026-10-05T12:00:00Z' )->schedule_for( '2026-10-05T15:00:00Z', '2026-10-05T12:01:00Z' );
	}

	public function test_a_stored_time_is_refused_before_scheduling(): void {
		$data = array_merge( self::provider_draft()->to_array(), array( 'scheduled_for' => '2026-10-05T15:00:00Z' ) );

		$this->expectException( \InvalidArgumentException::class );
		Campaign::from_array( $data );
	}

	public function test_records_without_the_field_remain_readable(): void {
		$data = self::provider_draft()->to_array();
		unset( $data['scheduled_for'] );

		self::assertNull( Campaign::from_array( $data )->scheduled_for() );
	}
}
