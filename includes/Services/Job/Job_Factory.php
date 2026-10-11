<?php
/**
 * Production job composition.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Job;

use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Job_Repository;
use CampaignBridge\Services\Campaign\Campaign_Reconciler_Factory;
use CampaignBridge\Services\Provider\Provider_Discovery_Factory;
use CampaignBridge\Workflow\Campaign\Campaign_Reconcile_Job;
use CampaignBridge\Workflow\Campaign\Campaign_Reconciler;
use CampaignBridge\Workflow\Campaign\Reconciliation_Sweep;
use CampaignBridge\Workflow\Campaign\System_Clock;
use CampaignBridge\Workflow\Job\Job_Handler;
use CampaignBridge\Workflow\Job\Job_Queue;
use CampaignBridge\Workflow\Job\Job_Runner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the job queue and runner with the production store and handlers.
 *
 * Delivery adapters (cron, WP-CLI, future admin tools) use this instead of
 * touching the job repository directly.
 */
final class Job_Factory {
	/**
	 * The job queue with production storage.
	 */
	public static function queue(): Job_Queue {
		return new Job_Queue( new Job_Repository(), new System_Clock() );
	}

	/**
	 * The job runner with production storage and handlers.
	 */
	public static function runner(): Job_Runner {
		return new Job_Runner( new Job_Repository(), self::handlers(), new System_Clock() );
	}

	/**
	 * Every registered job handler. Background work types add theirs here.
	 *
	 * @return array<int, Job_Handler>
	 */
	public static function handlers(): array {
		$jobs  = new Job_Repository();
		$clock = new System_Clock();

		return array(
			new Campaign_Reconcile_Job(
				new Campaign_Repository(),
				static fn ( string $provider ): ?Campaign_Reconciler => Campaign_Reconciler_Factory::create( $provider ),
				static fn ( string $provider ): array => Provider_Discovery_Factory::settings( $provider ),
				$clock
			),
			new Reconciliation_Sweep( new Campaign_Repository(), $jobs, new Job_Queue( $jobs, $clock ), $clock ),
		);
	}
}
