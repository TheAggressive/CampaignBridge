<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed signatures and the class contract document these methods.
/**
 * Production job composition.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Job;

use CampaignBridge\Repository\Job_Repository;
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
	public static function queue(): Job_Queue {
		return new Job_Queue( new Job_Repository(), new System_Clock() );
	}

	public static function runner(): Job_Runner {
		return new Job_Runner( new Job_Repository(), self::handlers(), new System_Clock() );
	}

	/**
	 * Every registered job handler. Background work types add theirs here.
	 *
	 * @return array<int, Job_Handler>
	 */
	public static function handlers(): array {
		return array();
	}
}
