<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * WP-Cron dispatch of background jobs.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Cron\Job_Dispatcher;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Tests\Helpers\Test_Case;

/** Proves the plugin wires a bounded one-minute tick and removes it on deactivation. */
final class Job_Dispatcher_Test extends Test_Case {
	public function set_up(): void {
		parent::set_up();
		self::assertTrue( Schema_Manager::migrate() );
		Job_Dispatcher::unschedule();
	}

	public function tear_down(): void {
		Job_Dispatcher::unschedule();
		parent::tear_down();
	}

	public function test_the_plugin_registers_the_tick(): void {
		self::assertNotFalse( has_action( Job_Dispatcher::HOOK, array( Job_Dispatcher::class, 'tick' ) ) );
		self::assertNotFalse( has_action( 'init', array( Job_Dispatcher::class, 'ensure_scheduled' ) ) );
		self::assertSame( MINUTE_IN_SECONDS, wp_get_schedules()[ Job_Dispatcher::SCHEDULE ]['interval'] ?? null );
	}

	public function test_the_tick_is_scheduled_once_and_cleared_on_deactivation(): void {
		self::assertFalse( wp_next_scheduled( Job_Dispatcher::HOOK ) );

		Job_Dispatcher::ensure_scheduled();
		$first = wp_next_scheduled( Job_Dispatcher::HOOK );
		Job_Dispatcher::ensure_scheduled();

		self::assertIsInt( $first );
		self::assertSame( $first, wp_next_scheduled( Job_Dispatcher::HOOK ), 'Scheduling again keeps one event.' );
		self::assertSame( Job_Dispatcher::SCHEDULE, wp_get_schedule( Job_Dispatcher::HOOK ) );

		\CampaignBridge_Plugin::deactivate();
		self::assertFalse( wp_next_scheduled( Job_Dispatcher::HOOK ) );
	}

	public function test_a_batch_runs_the_reconciliation_sweep_through_the_production_runner(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . Schema_Manager::table( 'jobs' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted test table.

		$first = Job_Dispatcher::run_batch();
		self::assertSame( 1, $first['claimed'] ?? null, 'The sweep is queued and run on the first tick.' );
		self::assertSame( 1, $first['rescheduled'] ?? null, 'It looks again on its interval.' );

		$sweep = ( new \CampaignBridge\Repository\Job_Repository() )->find_active( 'reconcile_sweep:site:campaigns' );
		self::assertNotNull( $sweep );
		self::assertSame( 'queued', $sweep->state() );

		self::assertSame( 0, Job_Dispatcher::run_batch()['claimed'] ?? null, 'One sweep, not one per tick.' );
	}
}
