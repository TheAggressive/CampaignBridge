<?php
/**
 * Durable job storage port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Job;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores jobs and arbitrates which worker holds each one.
 *
 * Every transition after a claim names its lease owner, so a worker that lost
 * its lease cannot complete, retry, or fail a job another worker now holds.
 */
interface Job_Source {
	/**
	 * Insert one queued job; a dedupe key already held by an active job fails.
	 *
	 * @param Job $job The job.
	 */
	public function add( Job $job ): bool;

	/**
	 * One job by ID.
	 *
	 * @param string $id Record ID.
	 */
	public function get( string $id ): ?Job;

	/**
	 * The active (queued or claimed) job holding a dedupe key.
	 *
	 * @param string $dedupe_key Key held by one active job for the same work.
	 */
	public function find_active( string $dedupe_key ): ?Job;

	/**
	 * Claim up to `$limit` runnable jobs for one owner: expired leases first, then queued jobs that are due, oldest first. Each claim counts one attempt; an expired lease with no attempts left is claimed as exhausted without counting one, so its handler can be told it was abandoned.
	 *
	 * @param string $owner       Lease or lock owner identity.
	 * @param string $now         Current UTC timestamp.
	 * @param string $lease_until UTC time the lease expires.
	 * @param int    $limit       Maximum number of records.
	 * @return array<int, Job_Claim>
	 */
	public function claim( string $owner, string $now, string $lease_until, int $limit ): array;

	/**
	 * Extend a lease the owner still holds.
	 *
	 * @param string $id          Record ID.
	 * @param string $owner       Lease or lock owner identity.
	 * @param string $lease_until UTC time the lease expires.
	 * @param string $now         Current UTC timestamp.
	 */
	public function heartbeat( string $id, string $owner, string $lease_until, string $now ): bool;

	/**
	 * Record success and release the job and its dedupe key.
	 *
	 * @param string $id    Record ID.
	 * @param string $owner Lease or lock owner identity.
	 * @param string $now   Current UTC timestamp.
	 */
	public function succeed( string $id, string $owner, string $now ): bool;

	/**
	 * Return a held job to the queue to run again after `$run_after`.
	 *
	 * @param string $id        Record ID.
	 * @param string $owner     Lease or lock owner identity.
	 * @param string $run_after Earliest UTC time the job may run.
	 * @param string $error     Stable error code.
	 * @param string $now       Current UTC timestamp.
	 */
	public function retry( string $id, string $owner, string $run_after, string $error, string $now ): bool;

	/**
	 * End a held job as `failed` or `dead`, releasing its dedupe key.
	 *
	 * @param string $id    Record ID.
	 * @param string $owner Lease or lock owner identity.
	 * @param string $state Job state.
	 * @param string $error Stable error code.
	 * @param string $now   Current UTC timestamp.
	 */
	public function finish( string $id, string $owner, string $state, string $error, string $now ): bool;

	/**
	 * Number of jobs in each state; states with none are omitted.
	 *
	 * @return array<string, int> Count by state; states with none are omitted.
	 */
	public function counts_by_state(): array;

	/**
	 * Work that should be running but is not.
	 *
	 * @param string $overdue_before Queued jobs due before this UTC time count as overdue.
	 * @param string $now            Current UTC timestamp.
	 * @return array{overdue: int, expired_leases: int, oldest_overdue: string|null}
	 */
	public function stalled( string $overdue_before, string $now ): array;

	/**
	 * Delete up to `$limit` succeeded jobs last updated before `$before`.
	 *
	 * @param string $before The campaign before the change.
	 * @param int    $limit  Maximum number of records.
	 */
	public function purge_succeeded( string $before, int $limit ): int;
}
