<?php
/**
 * A job one worker now holds.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Job;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The claimed job, and whether it was taken over from a worker whose lease
 * expired. A takeover means an earlier run may have done some or all of its
 * work before it stopped, so only a handler that is safe to repeat may run it.
 * An exhausted takeover used its last attempt on the run that stopped, so it
 * must not run again at all.
 */
final class Job_Claim {
	/**
	 * Describe one claim.
	 *
	 * @param Job  $job        The claimed job, with this claim's attempt counted.
	 * @param bool $taken_over Whether a stopped worker held it before.
	 * @param bool $exhausted  Whether that worker used its last attempt.
	 */
	public function __construct(
		public readonly Job $job,
		public readonly bool $taken_over,
		public readonly bool $exhausted = false
	) {}
}
