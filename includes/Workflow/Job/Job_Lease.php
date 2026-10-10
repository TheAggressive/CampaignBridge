<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed signatures and the class contract document these methods.
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
	/** @param \Closure(): bool $heartbeat Extends the lease; false when it is lost. */
	public function __construct( private readonly \Closure $heartbeat ) {}

	/** Extend the lease. False means another worker may hold the job: stop. */
	public function heartbeat(): bool {
		return ( $this->heartbeat )();
	}
}
