<?php
/**
 * Start background watches for campaigns that need one.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

use CampaignBridge\Domain\Campaign\Watched_Campaign_Source;
use CampaignBridge\Domain\Job\Job;
use CampaignBridge\Domain\Job\Job_Source;
use CampaignBridge\Workflow\Job\Job_Handler;
use CampaignBridge\Workflow\Job\Job_Lease;
use CampaignBridge\Workflow\Job\Job_Outcome;
use CampaignBridge\Workflow\Job\Job_Queue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A recurring job that queues a reconciliation watch for every scheduled,
 * sending, or unknown campaign without one.
 *
 * A campaign whose watch stopped for a reason only an operator can resolve
 * is skipped until the campaign changes, so the same unresolvable check is
 * not repeated. Queueing is deduplicated, so running the sweep often cannot
 * create a second watch for one campaign.
 */
final class Reconciliation_Sweep implements Job_Handler {
	public const TYPE     = 'reconcile_sweep';
	public const INTERVAL = 300;
	public const BATCH    = 100;

	/**
	 * Build the sweep.
	 *
	 * @param Watched_Campaign_Source $campaigns Campaigns in watched states.
	 * @param Job_Source              $jobs      Job storage.
	 * @param Job_Queue               $queue     Enqueues watches.
	 * @param Campaign_Clock          $clock     Source of the current time.
	 */
	public function __construct(
		private readonly Watched_Campaign_Source $campaigns,
		private readonly Job_Source $jobs,
		private readonly Job_Queue $queue,
		private readonly Campaign_Clock $clock
	) {}

	/**
	 * Queue the missing watches now.
	 *
	 * @return int How many watches were queued.
	 */
	public function sweep(): int {
		$queued = 0;
		$now    = $this->clock->now();
		foreach ( $this->campaigns->watched( Reconcile_Cadence::WATCHED, self::BATCH ) as $campaign ) {
			if ( null !== $this->jobs->find_active( Campaign_Reconcile_Job::dedupe_key( $campaign->id() ) ) ) {
				continue;
			}
			$latest = $this->jobs->latest_for_target( Campaign_Reconcile_Job::TYPE, 'campaign', $campaign->id() );
			if ( null !== $latest && Reconcile_Watch::stopped( $latest, $campaign ) ) {
				continue;
			}
			$delay = Reconcile_Cadence::is_due( $campaign, $now ) ? 0 : Reconcile_Cadence::next_delay( $campaign, $now );
			if ( null !== $this->queue->enqueue( Campaign_Reconcile_Job::TYPE, 'campaign', $campaign->id(), array(), $delay ) ) {
				++$queued;
			}
		}

		return $queued;
	}

	/**
	 * {@inheritDoc}
	 */
	public function type(): string {
		return self::TYPE;
	}

	/**
	 * {@inheritDoc}
	 *
	 * The sweep only reads campaigns and enqueues deduplicated jobs.
	 */
	public function retry_safe(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle( Job $job, Job_Lease $lease ): Job_Outcome {
		$this->sweep();

		return Job_Outcome::again( self::INTERVAL );
	}

	/**
	 * {@inheritDoc}
	 */
	public function abandoned( Job $job ): void {}
}
