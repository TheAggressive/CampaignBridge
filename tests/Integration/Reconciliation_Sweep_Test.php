<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,CampaignBridge.Standard.Sniffs.Database
/**
 * Fair, bounded discovery of campaigns needing background watches.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Job\Job;
use CampaignBridge\Domain\Job\Job_State;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Job_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;
use CampaignBridge\Workflow\Campaign\Campaign_Reconcile_Job;
use CampaignBridge\Workflow\Campaign\Reconciliation_Sweep;
use CampaignBridge\Workflow\Job\Job_Queue;

/** Selection must skip existing watches before limiting each sweep. */
final class Reconciliation_Sweep_Test extends Test_Case {
	private const NOW = '2026-10-18T12:00:00Z';

	public function set_up(): void {
		parent::set_up();
		self::assertTrue( Schema_Manager::migrate() );
		global $wpdb;
		foreach ( array( 'jobs', 'campaigns' ) as $table ) {
			$wpdb->query( 'DELETE FROM ' . Schema_Manager::table( $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted isolated test tables.
		}
	}

	/** @return array<string, array{string}> */
	public static function blocked_states(): array {
		return array(
			'active' => array( Job_State::QUEUED ),
			'failed' => array( Job_State::FAILED ),
			'dead'   => array( Job_State::DEAD ),
		);
	}

	/** @dataProvider blocked_states */
	public function test_existing_watches_do_not_starve_later_campaigns( string $state ): void {
		$campaigns = new Campaign_Repository();
		$jobs      = new Job_Repository();
		for ( $i = 0; $i < Reconciliation_Sweep::BATCH; ++$i ) {
			$id = sprintf( 'blocked-%03d', $i );
			self::assertTrue( $campaigns->add( $this->campaign( $id ) ) );
			self::assertTrue( $jobs->add( $this->watch( $id, $state ) ) );
		}
		for ( $i = 0; $i <= Reconciliation_Sweep::BATCH; ++$i ) {
			self::assertTrue( $campaigns->add( $this->campaign( sprintf( 'waiting-%03d', $i ) ) ) );
		}

		$sweep = $this->sweep();
		self::assertSame( Reconciliation_Sweep::BATCH, $sweep->sweep(), 'Existing watches must not occupy the candidate limit.' );
		self::assertSame( 1, $sweep->sweep(), 'The next batch must reach the remaining eligible campaign.' );
		self::assertSame( 0, $sweep->sweep(), 'Active and stopped watches remain untouched.' );
		self::assertNotNull( $jobs->find_active( Campaign_Reconcile_Job::dedupe_key( 'waiting-100' ) ) );
	}

	public function test_a_stopped_watch_restarts_only_after_the_campaign_changes(): void {
		$campaigns = new Campaign_Repository();
		$jobs      = new Job_Repository();
		$campaign  = $this->campaign( 'changed-campaign' );
		self::assertTrue( $campaigns->add( $campaign ) );
		self::assertTrue( $jobs->add( $this->watch( $campaign->id(), Job_State::FAILED ) ) );
		self::assertSame( 0, $this->sweep()->sweep() );

		$changed = Campaign::from_array(
			array_merge(
				$campaign->to_array(),
				array(
					'version'    => 2,
					'updated_at' => '2026-10-18T12:00:01Z',
				)
			)
		);
		self::assertTrue( $campaigns->compare_and_swap( $changed, 1 ) );
		self::assertSame( 1, $this->sweep()->sweep() );
	}

	public function test_the_latest_watch_wins_but_an_active_watch_always_blocks_a_duplicate(): void {
		$campaigns = new Campaign_Repository();
		$jobs      = new Job_Repository();
		foreach ( array( 'finished-campaign', 'active-campaign' ) as $id ) {
			self::assertTrue( $campaigns->add( $this->campaign( $id ) ) );
			self::assertTrue( $jobs->add( $this->watch( $id, 'active-campaign' === $id ? Job_State::QUEUED : Job_State::FAILED ) ) );
			// At equal timestamps, the lexically greater ID is the latest watch.
			$finished = Job::from_array(
				array_merge( $this->watch( $id, Job_State::SUCCEEDED )->to_array(), array( 'id' => 'zz-watch-' . $id ) )
			);
			self::assertTrue( $jobs->add( $finished ) );
		}
		$eligible = $campaigns->needing_watch( array( 'unknown' ), Campaign_Reconcile_Job::TYPE, 100 );
		self::assertSame( array( 'finished-campaign' ), array_map( static fn ( Campaign $campaign ): string => $campaign->id(), $eligible ) );
		self::assertSame( 1, $this->sweep()->sweep() );
		self::assertSame( array(), $campaigns->needing_watch( array( 'draft' ), Campaign_Reconcile_Job::TYPE, 100 ) );
		self::assertSame( array(), $campaigns->needing_watch( array( 'unknown' ), Campaign_Reconcile_Job::TYPE, 0 ) );
	}

	private function campaign( string $id ): Campaign {
		return Campaign::from_array(
			array_merge( Campaign::create( $id, 1, 1, 'mailchimp', 'audience', self::NOW )->to_array(), array( 'state' => 'unknown' ) )
		);
	}

	private function watch( string $campaign_id, string $state ): Job {
		return Job::from_array(
			array_merge(
				Job::create( 'watch-' . $campaign_id, Campaign_Reconcile_Job::TYPE, 'campaign', $campaign_id, array(), 5, self::NOW, self::NOW )->to_array(),
				array(
					'state'      => $state,
					'dedupe_key' => Job_State::QUEUED === $state ? Campaign_Reconcile_Job::dedupe_key( $campaign_id ) : null,
				)
			)
		);
	}

	private function sweep(): Reconciliation_Sweep {
		$clock = $this->createMock( Campaign_Clock::class );
		$clock->method( 'now' )->willReturn( self::NOW );
		$jobs = new Job_Repository();

		return new Reconciliation_Sweep( new Campaign_Repository(), $jobs, new Job_Queue( $jobs, $clock ), $clock );
	}
}
