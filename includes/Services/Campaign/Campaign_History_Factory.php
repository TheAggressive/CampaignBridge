<?php
/**
 * Production wiring for campaign history reads.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Campaign;

use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Workflow\Campaign\Campaign_History;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Builds the read-only history service over the production repositories. */
final class Campaign_History_Factory {
	/**
	 * Create the history service.
	 *
	 * @param Campaign_Workflow|null $workflow Workflow that authorizes campaign reads.
	 */
	public static function create( ?Campaign_Workflow $workflow = null ): Campaign_History {
		return new Campaign_History(
			$workflow ?? Campaign_Workflow_Factory::create(),
			new Audit_Event_Repository(),
			new Delivery_Attempt_Repository(),
			new Remote_Campaign_Reference_Repository()
		);
	}
}
