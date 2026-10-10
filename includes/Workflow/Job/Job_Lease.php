<?php
/**
 * A worker's hold on one job.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Job;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Lets a handler keep its lease while it works and learn when it has lost it. */
final class Job_Lease {
	/**
	 * Build the job lease.
	 *
	 * @param \Closure $heartbeat Extends the lease; returns false when it is lost.
	 * @phpstan-param \Closure(): bool $heartbeat
	 */
	public function __construct( private readonly \Closure $heartbeat ) {}

	/** Extend the lease. False means another worker may hold the job: stop. */
	public function heartbeat(): bool {
		return ( $this->heartbeat )();
	}
}
