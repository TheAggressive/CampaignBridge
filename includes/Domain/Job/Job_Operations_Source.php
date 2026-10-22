<?php
/**
 * Operator access to durable jobs.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Job;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bounded reads and conditional recovery without taking over a worker's lease. */
interface Job_Operations_Source extends Job_Source {
	/**
	 * Read one page of jobs, newest first, with stable ID ordering for ties.
	 *
	 * @param string|null $state Optional lifecycle state.
	 * @param int         $limit Page size, at most 100.
	 * @param int         $offset Number of records to skip.
	 * @return array<int, Job>
	 */
	public function listing( ?string $state, int $limit, int $offset ): array;

	/**
	 * Requeue a failed or dead job only if its observed state still matches.
	 *
	 * The workflow verifies retry safety. A conflicting active dedupe key fails.
	 *
	 * @param Job    $expected The observed job.
	 * @param string $now Current UTC timestamp.
	 */
	public function requeue( Job $expected, string $now ): bool;

	/**
	 * Mark a queued job failed with cancelled_by_operator, releasing its dedupe key.
	 *
	 * @param Job    $expected The observed job.
	 * @param string $now Current UTC timestamp.
	 */
	public function cancel_queued( Job $expected, string $now ): bool;
}
