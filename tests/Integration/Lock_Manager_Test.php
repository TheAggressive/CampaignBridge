<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,Generic.Files.OneObjectStructurePerFile,CampaignBridge.Standard.Sniffs.Database
/**
 * Expiring locks around campaign and provider work.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Lock_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Error;
use CampaignBridge\Workflow\Lock\Lock_Manager;

final class Lock_Test_Clock implements Campaign_Clock {
	public int $now = 1790000000;

	public function now(): string {
		return gmdate( 'Y-m-d\TH:i:s\Z', $this->now );
	}
}

/** Proves one holder at a time, owner-only release, and audited takeover of abandoned locks. */
final class Lock_Manager_Test extends Test_Case {
	private Lock_Test_Clock $clock;

	public function set_up(): void {
		parent::set_up();
		self::assertTrue( Schema_Manager::migrate() );
		global $wpdb;
		foreach ( array( 'locks', 'audit_events' ) as $table ) {
			$wpdb->query( 'DELETE FROM ' . Schema_Manager::table( $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted test table.
		}
		$this->clock = new Lock_Test_Clock();
	}

	public function test_work_runs_once_under_the_lock_and_a_second_holder_is_refused(): void {
		$first  = $this->manager( 'lock-first' );
		$second = $this->manager( 'lock-second' );
		$inner  = null;

		$result = $first->campaign(
			'campaign-one',
			'send',
			function () use ( $second, &$inner ): string {
				$this->clock->now += 60;
				$inner = $second->campaign( 'campaign-one', 'reconcile', static fn (): string => 'ran', static fn ( Campaign_Workflow_Error $error ): Campaign_Workflow_Error => $error );
				return 'sent';
			},
			static fn (): string => 'refused'
		);

		self::assertSame( 'sent', $result );
		self::assertInstanceOf( Campaign_Workflow_Error::class, $inner );
		self::assertSame( Campaign_Workflow_Error::LOCKED, $inner->code() );
		self::assertSame( Lock_Manager::TTL_SECONDS - 60, $inner->retry_after(), 'The refusal says when the lock frees.' );
		self::assertSame( 'ran', $second->campaign( 'campaign-one', 'reconcile', static fn (): string => 'ran', static fn (): string => 'refused' ), 'Released when the work finished.' );
	}

	public function test_different_campaigns_do_not_block_each_other(): void {
		$outer = $this->manager( 'lock-first' );

		$inner = $outer->campaign(
			'campaign-one',
			'send',
			fn (): string => $this->manager( 'lock-second' )->campaign( 'campaign-two', 'send', static fn (): string => 'ran', static fn (): string => 'refused' ),
			static fn (): string => 'refused'
		);

		self::assertSame( 'ran', $inner );
	}

	public function test_the_lock_is_released_when_the_work_throws(): void {
		try {
			$this->manager( 'lock-first' )->campaign( 'campaign-one', 'send', static fn () => throw new \RuntimeException( 'provider exploded' ), static fn (): string => 'refused' );
			self::fail( 'The exception should propagate.' );
		} catch ( \RuntimeException ) {
			self::assertSame( 'ran', $this->manager( 'lock-second' )->campaign( 'campaign-one', 'send', static fn (): string => 'ran', static fn (): string => 'refused' ) );
		}
	}

	public function test_only_the_owner_releases_and_an_abandoned_lock_is_taken_over_and_audited(): void {
		$locks = new Lock_Repository();
		$now   = $this->clock->now();
		self::assertTrue( $locks->acquire( 'campaign:campaign-one', 'lock-crashed', 'send', gmdate( 'Y-m-d\TH:i:s\Z', $this->clock->now + Lock_Manager::TTL_SECONDS ), $now )->acquired );
		self::assertFalse( $locks->release( 'campaign:campaign-one', 'lock-other' ) );

		$this->clock->now += Lock_Manager::TTL_SECONDS + 1;
		$result = $this->manager( 'lock-recovery' )->campaign( 'campaign-one', 'reconcile', static fn (): string => 'ran', static fn (): string => 'refused' );

		self::assertSame( 'ran', $result );
		$events = ( new Audit_Event_Repository() )->for_target( 'campaign', 'campaign-one' );
		self::assertCount( 1, $events );
		self::assertSame( 'campaign_lock_takeover', $events[0]->action() );
		self::assertEquals(
			array(
				'operation'             => 'reconcile',
				'interrupted_operation' => 'send',
			),
			$events[0]->context()->to_array()
		);
		self::assertFalse( $locks->release( 'campaign:campaign-one', 'lock-crashed' ), 'The crashed holder cannot free the lock it lost.' );
	}

	public function test_racing_takeovers_have_one_winner(): void {
		$locks = new Lock_Repository();
		$past  = gmdate( 'Y-m-d\TH:i:s\Z', $this->clock->now - 10 );
		self::assertTrue( $locks->acquire( 'campaign:campaign-one', 'lock-crashed', 'send', $past, gmdate( 'Y-m-d\TH:i:s\Z', $this->clock->now - 400 ) )->acquired );
		$until = gmdate( 'Y-m-d\TH:i:s\Z', $this->clock->now + 300 );

		$a = $locks->acquire( 'campaign:campaign-one', 'lock-a', 'reconcile', $until, $this->clock->now() );
		$b = $locks->acquire( 'campaign:campaign-one', 'lock-b', 'reconcile', $until, $this->clock->now() );

		self::assertTrue( $a->acquired );
		self::assertFalse( $b->acquired );
		self::assertSame( $until, $b->held_until );
	}

	private function manager( string $owner ): Lock_Manager {
		return new Lock_Manager( new Lock_Repository(), new Audit_Event_Repository(), $this->clock, $owner );
	}
}
