<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,CampaignBridge.Standard.Sniffs.Database
/**
 * Query budget for one background-job tick.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Performance;

use CampaignBridge\Cron\Job_Dispatcher;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Services\Job\Job_Factory;
use CampaignBridge\Tests\Helpers\Test_Case;

/** A tick's cost is bounded by its batch size, not by how much work is queued. */
final class Job_Dispatch_Performance_Test extends Test_Case {
	public function set_up(): void {
		parent::set_up();
		self::assertTrue( Schema_Manager::migrate() );
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . Schema_Manager::table( 'jobs' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted test table.
	}

	public function test_an_idle_tick_is_a_handful_of_queries(): void {
		self::assertLessThanOrEqual( 5, $this->queries_for_tick() );
	}

	public function test_a_busy_tick_is_bounded_by_the_batch_not_the_queue(): void {
		$queue = Job_Factory::queue();
		for ( $i = 0; $i < Job_Dispatcher::BATCH * 3; $i++ ) {
			$queue->enqueue( 'unregistered', 'campaign', 'campaign-' . $i );
		}

		$queries = $this->queries_for_tick();

		// Claim selects, then per job one claim, one read, and one transition.
		self::assertLessThanOrEqual( 5 + 3 * Job_Dispatcher::BATCH, $queries );
	}

	private function queries_for_tick(): int {
		global $wpdb;
		$before = $wpdb->num_queries;
		Job_Dispatcher::run_batch();

		return $wpdb->num_queries - $before;
	}
}
