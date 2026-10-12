<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * When background reconciliation looks again.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Workflow\Campaign\Reconcile_Cadence;
use WP_UnitTestCase;

/** Proves the cadence waits for the send time, then backs off as a send ages. */
final class Reconcile_Cadence_Test extends WP_UnitTestCase {
	private const NOW = '2030-01-01T12:00:00Z';

	private static function campaign( string $state, ?string $scheduled_for = null, string $updated_at = self::NOW ): Campaign {
		return Campaign::from_array(
			array_merge(
				Campaign::create( 'campaign-one', 7, 42, 'mailchimp', 'abc123', '2029-12-01T00:00:00Z' )->to_array(),
				array(
					'state'               => $state,
					'active_snapshot_id'  => 'snapshot-one',
					'approved_by_user_id' => 9,
					'scheduled_for'       => $scheduled_for,
					'updated_at'          => $updated_at,
				)
			)
		);
	}

	public function test_only_scheduled_sending_and_unknown_campaigns_are_watched(): void {
		foreach ( array( 'scheduled', 'sending', 'unknown' ) as $state ) {
			self::assertTrue( Reconcile_Cadence::is_watched( $state ), $state );
		}
		foreach ( array( 'draft', 'ready_for_review', 'approved', 'provider_draft', 'sent', 'failed', 'cancelled', 'archived' ) as $state ) {
			self::assertFalse( Reconcile_Cadence::is_watched( $state ), $state );
		}
	}

	public function test_a_scheduled_campaign_is_first_read_two_minutes_after_its_send_time(): void {
		$campaign = self::campaign( 'scheduled', '2030-01-01T13:00:00Z' );

		self::assertFalse( Reconcile_Cadence::is_due( $campaign, self::NOW ) );
		self::assertSame( HOUR_IN_SECONDS + 120, Reconcile_Cadence::next_delay( $campaign, self::NOW ) );
		self::assertFalse( Reconcile_Cadence::is_due( $campaign, '2030-01-01T13:01:00Z' ) );
		self::assertTrue( Reconcile_Cadence::is_due( $campaign, '2030-01-01T13:02:00Z' ) );
		self::assertSame( 300, Reconcile_Cadence::next_delay( $campaign, '2030-01-01T13:30:00Z' ), 'Past its send time, every five minutes.' );
	}

	public function test_a_sending_campaign_is_read_less_often_as_the_send_ages(): void {
		self::assertSame( 300, Reconcile_Cadence::next_delay( self::campaign( 'sending', null, '2030-01-01T11:59:00Z' ), self::NOW ) );
		self::assertSame( 1800, Reconcile_Cadence::next_delay( self::campaign( 'sending', null, '2030-01-01T11:00:00Z' ), self::NOW ) );
		self::assertSame( 3600, Reconcile_Cadence::next_delay( self::campaign( 'sending', null, '2029-12-31T12:00:00Z' ), self::NOW ), 'Never less often than hourly.' );
	}

	public function test_an_unknown_outcome_is_read_soon(): void {
		self::assertSame( 60, Reconcile_Cadence::next_delay( self::campaign( 'unknown' ), self::NOW ) );
		self::assertTrue( Reconcile_Cadence::is_due( self::campaign( 'unknown' ), self::NOW ) );
	}
}
