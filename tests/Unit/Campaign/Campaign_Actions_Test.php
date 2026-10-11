<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Operator actions derived from authority, state, and delivery policy.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Campaign;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Delivery_Policy;
use CampaignBridge\Workflow\Campaign\Campaign_Actions;
use CampaignBridge\Workflow\Campaign\Campaign_Actor;
use WP_UnitTestCase;

/** Proves screens are offered exactly the delivery actions the workflow would allow. */
final class Campaign_Actions_Test extends WP_UnitTestCase {
	private const OWNER    = 7;
	private const APPROVER = 9;

	private static function campaign( string $state, ?string $provider = 'mailchimp', ?int $approved_by = self::APPROVER ): Campaign {
		$data = Campaign::create( 'campaign-one', self::OWNER, 42, $provider, null === $provider ? null : 'abc123', '2026-10-05T12:00:00Z' )->to_array();

		return Campaign::from_array(
			array_merge(
				$data,
				array(
					'state'               => $state,
					'active_snapshot_id'  => 'snapshot-one',
					'approved_by_user_id' => in_array( $state, array( 'draft', 'ready_for_review' ), true ) ? null : $approved_by,
					'scheduled_for'       => 'scheduled' === $state ? '2030-01-01T00:00:00Z' : null,
				)
			)
		);
	}

	/** A manager who may approve, deliver, and test. */
	private static function manager( int $user_id = self::OWNER ): Campaign_Actor {
		return new Campaign_Actor( $user_id, true, true, true, true );
	}

	/** The workflow refuses template steps without template access, so they are not offered. */
	public function test_template_steps_need_template_access(): void {
		$review = Campaign_Actions::for( self::manager(), self::campaign( 'ready_for_review' ), null, false );
		self::assertNotContains( 'approve', $review );
		self::assertNotContains( 'duplicate', $review );
		self::assertContains( 'archive', $review );

		$draft = Campaign_Actions::for( self::manager(), self::campaign( 'draft' ), null, false );
		self::assertSame( array(), array_values( array_intersect( $draft, array( 'edit', 'snapshot', 'submit' ) ) ) );

		// Delivery reads only the frozen snapshot, so it stays available.
		self::assertContains( 'schedule', Campaign_Actions::for( self::manager(), self::campaign( 'provider_draft' ), null, false ) );
		self::assertContains( 'approve', Campaign_Actions::for( self::manager(), self::campaign( 'ready_for_review' ) ) );
	}

	public function test_an_approved_provider_campaign_offers_the_draft_handoff(): void {
		$actions = Campaign_Actions::for( self::manager(), self::campaign( 'approved' ) );

		self::assertContains( 'create_provider_draft', $actions );
		self::assertNotContains( 'schedule', $actions );
	}

	public function test_a_provider_draft_offers_test_schedule_send_and_reconcile(): void {
		self::assertSame(
			array( 'test_send', 'schedule', 'send', 'reconcile' ),
			array_values( array_intersect( Campaign_Actions::for( self::manager(), self::campaign( 'provider_draft' ) ), array( 'create_provider_draft', 'test_send', 'schedule', 'unschedule', 'send', 'reconcile' ) ) )
		);
	}

	public function test_a_scheduled_campaign_offers_unschedule_but_not_send(): void {
		$actions = Campaign_Actions::for( self::manager(), self::campaign( 'scheduled' ) );

		self::assertContains( 'unschedule', $actions );
		self::assertContains( 'reconcile', $actions );
		self::assertNotContains( 'send', $actions );
		self::assertNotContains( 'schedule', $actions );
	}

	public function test_an_unknown_campaign_offers_only_reconciliation_for_delivery(): void {
		$actions = Campaign_Actions::for( self::manager(), self::campaign( 'unknown' ) );

		self::assertContains( 'reconcile', $actions );
		foreach ( array( 'schedule', 'unschedule', 'send', 'test_send', 'create_provider_draft' ) as $refused ) {
			self::assertNotContains( $refused, $actions );
		}
	}

	public function test_separation_of_duties_withholds_schedule_and_send_from_the_approver(): void {
		$policy = Delivery_Policy::from_settings( true, '' );

		$approver = Campaign_Actions::for( self::manager( self::APPROVER ), self::campaign( 'provider_draft' ), $policy );
		self::assertNotContains( 'schedule', $approver );
		self::assertNotContains( 'send', $approver );
		self::assertContains( 'test_send', $approver, 'Tests are not delivery to the audience.' );

		$other = Campaign_Actions::for( self::manager( 11 ), self::campaign( 'provider_draft' ), $policy );
		self::assertContains( 'schedule', $other );
		self::assertContains( 'send', $other );

		$unrecorded = Campaign_Actions::for( self::manager( 11 ), self::campaign( 'provider_draft', 'mailchimp', null ), $policy );
		self::assertNotContains( 'send', $unrecorded, 'An unrecorded approver cannot be shown to be independent.' );

		self::assertContains( 'unschedule', Campaign_Actions::for( self::manager( self::APPROVER ), self::campaign( 'scheduled' ), $policy ), 'Stopping delivery needs no second person.' );
	}

	public function test_authority_gates_each_delivery_action(): void {
		$tester    = new Campaign_Actor( self::OWNER, true, false, false, true );
		$deliverer = new Campaign_Actor( self::OWNER, true, false, true, false );

		self::assertSame( array( 'test_send' ), array_values( array_intersect( Campaign_Actions::for( $tester, self::campaign( 'provider_draft' ) ), array( 'test_send', 'schedule', 'send', 'reconcile' ) ) ) );
		self::assertNotContains( 'test_send', Campaign_Actions::for( $deliverer, self::campaign( 'provider_draft' ) ) );
		self::assertNotContains( 'create_provider_draft', Campaign_Actions::for( $tester, self::campaign( 'approved' ) ) );
	}

	public function test_html_export_campaigns_have_no_delivery_actions(): void {
		$actions = Campaign_Actions::for( self::manager(), self::campaign( 'approved', null ) );

		self::assertSame( array(), array_intersect( $actions, array( 'create_provider_draft', 'test_send', 'schedule', 'unschedule', 'send', 'reconcile' ) ) );
	}
}
