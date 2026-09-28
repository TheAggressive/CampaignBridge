<?php
/**
 * Durable campaign and immutable snapshot repository tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_Snapshot;
use CampaignBridge\Domain\Email\Compiled_Artifact;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Campaign_Snapshot_Repository;
use CampaignBridge\Repository\Post_Snapshot_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Workflow\Email\Template_Preview;

/** Exercises typed round trips, CAS, and frozen M1 artifacts. */
final class Campaign_Repository_Test extends Test_Case {
	/** Ensure a clean custom-table fixture. */
	public function set_up(): void {
		parent::set_up();
		self::assertTrue( Schema_Manager::migrate() );
		$this->clear_tables();
	}

	/** Campaign records round trip and missing records return null. */
	public function test_campaign_round_trip_and_missing_record(): void {
		$repository = new Campaign_Repository();
		$campaign   = $this->campaign( 'campaign-one' );

		self::assertNull( $repository->get( 'missing-campaign' ) );
		self::assertTrue( $repository->add( $campaign ) );
		self::assertFalse( $repository->add( $campaign ) );
		self::assertSame( $campaign->to_array(), $repository->get( $campaign->id() )?->to_array() );
	}

	/** Only an exact expected version may perform a compare-and-swap. */
	public function test_campaign_compare_and_swap_rejects_stale_writes(): void {
		$repository = new Campaign_Repository();
		$campaign   = $this->campaign( 'campaign-cas' );
		self::assertTrue( $repository->add( $campaign ) );
		$replacement = Campaign::from_array(
			array_merge(
				$campaign->to_array(),
				array(
					'version'    => 2,
					'updated_at' => '2026-01-01T00:01:00Z',
				)
			)
		);

		self::assertFalse( $repository->compare_and_swap( $replacement, 0 ) );
		self::assertTrue( $repository->compare_and_swap( $replacement, 1 ) );
		self::assertFalse( $repository->compare_and_swap( $replacement, 1 ) );
		self::assertSame( 2, $repository->get( $campaign->id() )?->version() );
	}

	/** Compare-and-swap cannot rewrite the immutable creation identity. */
	public function test_campaign_compare_and_swap_rejects_changed_creation_timestamp(): void {
		$repository = new Campaign_Repository();
		$campaign   = $this->campaign( 'campaign-created-at' );
		self::assertTrue( $repository->add( $campaign ) );
		$replacement = Campaign::from_array(
			array_merge(
				$campaign->to_array(),
				array(
					'version'    => 2,
					'created_at' => '2025-12-31T23:59:00Z',
					'updated_at' => '2026-01-01T00:01:00Z',
				)
			)
		);

		self::assertFalse( $repository->compare_and_swap( $replacement, 1 ) );
		self::assertSame( $campaign->to_array(), $repository->get( $campaign->id() )?->to_array() );
	}

	/** Owner reads are indexed, deterministic, and bounded by the requested limit. */
	public function test_campaign_owner_listing_is_bounded(): void {
		$repository = new Campaign_Repository();
		foreach ( array( 'campaign-a', 'campaign-b', 'campaign-c' ) as $id ) {
			self::assertTrue( $repository->add( $this->campaign( $id ) ) );
		}

		self::assertCount( 2, $repository->for_owner( 7, 2 ) );
		self::assertSame( array(), $repository->for_owner( 0 ) );
	}

	/** Future and malformed campaign rows fail closed without being rewritten. */
	public function test_campaign_malformed_data_version_returns_null(): void {
		global $wpdb;
		$repository = new Campaign_Repository();
		self::assertTrue( $repository->add( $this->campaign( 'campaign-future' ) ) );
		$wpdb->update( Schema_Manager::table( 'campaigns' ), array( 'data_version' => 99 ), array( 'id' => 'campaign-future' ) );

		self::assertNull( $repository->get( 'campaign-future' ) );
		self::assertSame( '99', (string) $wpdb->get_var( 'SELECT data_version FROM ' . Schema_Manager::table( 'campaigns' ) . " WHERE id = 'campaign-future'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted table and fixed fixture.
	}

	/** Stored review input remains independent of later WordPress source edits. */
	public function test_snapshot_round_trip_preserves_exact_frozen_artifact(): void {
		$post_id  = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Reviewed title',
			)
		);
		$preview  = new Template_Preview( new Post_Snapshot_Repository() );
		$input    = $preview->capture( $this->post_content( $post_id ) );
		$artifact = $preview->compile_frozen( $input );
		$campaign = $this->campaign( 'campaign-snapshot' );
		self::assertTrue( ( new Campaign_Repository() )->add( $campaign ) );

		$snapshot   = $this->snapshot( 'snapshot-one', $campaign->id(), $input, Compiled_Artifact::from_result( $artifact ) );
		$repository = new Campaign_Snapshot_Repository();
		self::assertNull( $repository->get( 'snapshot-missing' ) );
		self::assertTrue( $repository->add( $snapshot ) );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Changed later',
			)
		);

		$loaded = $repository->get( 'snapshot-one' );
		self::assertNotNull( $loaded );
		self::assertSame( $snapshot->to_array(), $loaded->to_array() );
		self::assertSame( 'Reviewed title', $loaded->review_input()->context()->post_snapshot( (string) $post_id )?->get( 'title' ) );
		self::assertSame( $artifact->html(), $preview->compile_frozen( $loaded->review_input() )->html() );
	}

	/** Malformed snapshot JSON fails closed and remains untouched. */
	public function test_malformed_snapshot_returns_null(): void {
		global $wpdb;
		$preview    = new Template_Preview( new Post_Snapshot_Repository() );
		$input      = $preview->capture( '<!-- wp:campaignbridge/container /-->' );
		$campaign   = $this->campaign( 'campaign-malformed-snapshot' );
		$repository = new Campaign_Snapshot_Repository();
		self::assertTrue( ( new Campaign_Repository() )->add( $campaign ) );
		self::assertTrue( $repository->add( $this->snapshot( 'snapshot-malformed', $campaign->id(), $input, Compiled_Artifact::from_result( $preview->compile_frozen( $input ) ) ) ) );
		$wpdb->update( Schema_Manager::table( 'campaign_snapshots' ), array( 'review_input' => '{broken' ), array( 'id' => 'snapshot-malformed' ) );

		self::assertNull( $repository->get( 'snapshot-malformed' ) );
		self::assertSame( '{broken', $wpdb->get_var( 'SELECT review_input FROM ' . Schema_Manager::table( 'campaign_snapshots' ) . " WHERE id = 'snapshot-malformed'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted table and fixed fixture.
	}

	/** Existing snapshot identity/revision cannot mutate; refresh creates revision two. */
	public function test_snapshot_is_insert_only_and_refresh_has_distinct_identity(): void {
		$preview    = new Template_Preview( new Post_Snapshot_Repository() );
		$input      = $preview->capture( '<!-- wp:campaignbridge/container /-->' );
		$artifact   = Compiled_Artifact::from_result( $preview->compile_frozen( $input ) );
		$campaign   = $this->campaign( 'campaign-refresh' );
		$campaigns  = new Campaign_Repository();
		$repository = new Campaign_Snapshot_Repository();
		self::assertTrue( $campaigns->add( $campaign ) );
		self::assertTrue( $repository->add( $this->snapshot( 'snapshot-v1', $campaign->id(), $input, $artifact ) ) );
		self::assertFalse( $repository->add( $this->snapshot( 'snapshot-v1-copy', $campaign->id(), $input, $artifact ) ) );

		$refreshed = $preview->refresh( $input );
		self::assertTrue( $repository->add( $this->snapshot( 'snapshot-v2', $campaign->id(), $refreshed, Compiled_Artifact::from_result( $preview->compile_frozen( $refreshed ) ) ) ) );
		self::assertSame( array( 2, 1 ), array_map( static fn ( Campaign_Snapshot $item ): int => $item->revision(), $repository->for_campaign( $campaign->id(), 2 ) ) );
	}

	/** Campaigns may activate only a snapshot that belongs to them. */
	public function test_active_snapshot_relationship_is_enforced(): void {
		$campaigns = new Campaign_Repository();
		$first     = $this->campaign( 'campaign-first' );
		$second    = $this->campaign( 'campaign-second' );
		self::assertTrue( $campaigns->add( $first ) );
		self::assertTrue( $campaigns->add( $second ) );
		$preview  = new Template_Preview( new Post_Snapshot_Repository() );
		$input    = $preview->capture( '<!-- wp:campaignbridge/container /-->' );
		$snapshot = $this->snapshot( 'snapshot-owned', $first->id(), $input, Compiled_Artifact::from_result( $preview->compile_frozen( $input ) ) );
		self::assertTrue( ( new Campaign_Snapshot_Repository() )->add( $snapshot ) );

		$invalid = Campaign::from_array(
			array_merge(
				$second->to_array(),
				array(
					'version'            => 2,
					'active_snapshot_id' => $snapshot->id(),
					'updated_at'         => '2026-01-01T00:01:00Z',
				)
			)
		);
		self::assertFalse( $campaigns->compare_and_swap( $invalid, 1 ) );
		$valid = Campaign::from_array(
			array_merge(
				$first->to_array(),
				array(
					'version'            => 2,
					'active_snapshot_id' => $snapshot->id(),
					'updated_at'         => '2026-01-01T00:01:00Z',
				)
			)
		);
		self::assertTrue( $campaigns->compare_and_swap( $valid, 1 ) );
	}

	/** Create a stable provider-neutral campaign fixture. */
	private function campaign( string $id ): Campaign {
		return Campaign::create( $id, 7, 42, 'provider-one', 'audience-one', '2026-01-01T00:00:00Z' );
	}

	/** Build a snapshot from canonical M1 values. */
	private function snapshot( string $id, string $campaign_id, \CampaignBridge\Domain\Email\Review_Input $input, Compiled_Artifact $artifact ): Campaign_Snapshot {
		return Campaign_Snapshot::from_array(
			array(
				'schema_version' => 1,
				'id'             => $id,
				'campaign_id'    => $campaign_id,
				'revision'       => $input->revision(),
				'review_input'   => $input->to_array(),
				'artifact'       => $artifact->to_array(),
				'created_at'     => '2026-01-01T00:00:00Z',
			)
		);
	}

	/** Template fixture that binds a post title through the frozen snapshot. */
	private function post_content( int $post_id ): string {
		return '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/post-card {"postId":' . $post_id . '} -->'
			. '<!-- wp:heading {"metadata":{"bindings":{"content":{"source":"campaignbridge/post-data","args":{"field":"title"}}}}} --><h2></h2><!-- /wp:heading -->'
			. '<!-- /wp:campaignbridge/post-card --><!-- /wp:campaignbridge/container -->';
	}

	/** Remove rows in relationship-safe order. */
	private function clear_tables(): void {
		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted test table.
		}
	}
}
