<?php
/**
 * WP-Cron dispatch for background jobs.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Cron;

use CampaignBridge\Repository\Job_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Services\Job\Job_Factory;
use CampaignBridge\Workflow\Campaign\System_Clock;
use CampaignBridge\Workflow\Job\Job_Time;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs a bounded batch of due jobs on every WP-Cron tick.
 *
 * WP-Cron only runs when the site receives traffic or a system cron calls
 * it, so a tick may be late; the job store keeps due work until then, and
 * stalled-work detection reports jobs that should already have run. Each
 * tick also removes succeeded jobs older than a week.
 */
final class Job_Dispatcher {
	public const HOOK         = 'campaignbridge_run_jobs';
	public const SCHEDULE     = 'campaignbridge_every_minute';
	public const BATCH        = 10;
	private const KEEP_DAYS   = 7;
	private const PURGE_BATCH = 50;

	/** Register the schedule, the tick handler, and keep the event scheduled. */
	public static function register(): void {
		\add_filter( 'cron_schedules', array( self::class, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Deliberate one-minute tick: an idle tick is one indexed query, and due jobs must not wait 15 minutes.
		\add_action( self::HOOK, array( self::class, 'tick' ) );
		\add_action( 'init', array( self::class, 'ensure_scheduled' ) );
	}

	/**
	 * Add a one-minute interval.
	 *
	 * @param array<string, array{interval: int, display: string}> $schedules Registered schedules.
	 * @return array<string, array{interval: int, display: string}>
	 */
	public static function schedules( $schedules ): array {
		$schedules                   = is_array( $schedules ) ? $schedules : array();
		$schedules[ self::SCHEDULE ] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute (CampaignBridge background jobs)', 'campaignbridge' ),
		);

		return $schedules;
	}

	/** Schedule the recurring tick once; a no-op when it is already scheduled. */
	public static function ensure_scheduled(): void {
		if ( false === \wp_next_scheduled( self::HOOK ) ) {
			\wp_schedule_event( time(), self::SCHEDULE, self::HOOK );
		}
	}

	/** Remove the recurring tick, for deactivation. */
	public static function unschedule(): void {
		\wp_clear_scheduled_hook( self::HOOK );
	}

	/** The WP-Cron callback: run one batch. */
	public static function tick(): void {
		self::run_batch();
	}

	/**
	 * Run one batch of due jobs, then purge old succeeded jobs.
	 *
	 * @return array{claimed: int, succeeded: int, retried: int, failed: int, dead: int}|null Null when storage is not ready.
	 */
	public static function run_batch(): ?array {
		if ( ! Schema_Manager::is_current() ) {
			return null;
		}

		$report = Job_Factory::runner()->run( self::BATCH );
		( new Job_Repository() )->purge_succeeded( Job_Time::after( ( new System_Clock() )->now(), -self::KEEP_DAYS * DAY_IN_SECONDS ), self::PURGE_BATCH );

		return $report;
	}
}
