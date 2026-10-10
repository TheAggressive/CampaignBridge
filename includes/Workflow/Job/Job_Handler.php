<?php
/**
 * Work for one job type.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Job;

use CampaignBridge\Domain\Job\Job;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs one type of job.
 *
 * A handler is retry-safe only when running it again after it stopped part
 * way cannot cause an irreversible effect twice: reads, reconciliation, and
 * idempotent writes qualify; a provider send does not. When a worker stops
 * while holding a job whose handler is not retry-safe, the job is never run
 * again. `abandoned()` is called instead, so the handler can hand the
 * uncertain outcome to reconciliation or an operator.
 */
interface Job_Handler {
	/**
	 * The job type this handler runs; a lowercase identifier.
	 */
	public function type(): string;

	/**
	 * Whether running the job again after it stopped part way is safe.
	 */
	public function retry_safe(): bool;

	/**
	 * Do the work. Long work should call `$lease->heartbeat()` and stop when it returns false.
	 *
	 * @param Job       $job   The job.
	 * @param Job_Lease $lease The worker's hold on the job.
	 */
	public function handle( Job $job, Job_Lease $lease ): Job_Outcome;

	/**
	 * The job's worker stopped and the job will not run again. Must not contact a provider.
	 *
	 * @param Job $job The job.
	 */
	public function abandoned( Job $job ): void;
}
