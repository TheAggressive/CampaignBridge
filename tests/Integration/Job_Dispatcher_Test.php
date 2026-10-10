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

	public function test_a_batch_runs_through_the_production_runner(): void {
		self::assertSame(
			array(
				'claimed'   => 0,
				'succeeded' => 0,
				'retried'   => 0,
				'failed'    => 0,
				'dead'      => 0,
			),
			Job_Dispatcher::run_batch()
		);
	}
}
