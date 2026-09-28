<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Production campaign workflow composition.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Campaign;

use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Campaign_Review_Input_Repository;
use CampaignBridge\Repository\Campaign_Snapshot_Repository;
use CampaignBridge\Repository\Database_Transaction;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow;
use CampaignBridge\Workflow\Campaign\Random_Id_Generator;
use CampaignBridge\Workflow\Campaign\System_Clock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Builds the one production application service used by future adapters. */
final class Campaign_Workflow_Factory {
	public static function create(): Campaign_Workflow {
		return new Campaign_Workflow(
			new Campaign_Repository(),
			new Campaign_Snapshot_Repository(),
			new Delivery_Attempt_Repository(),
			new Audit_Event_Repository(),
			new Campaign_Review_Input_Repository(),
			new Database_Transaction(),
			new Random_Id_Generator(),
			new System_Clock()
		);
	}
}
