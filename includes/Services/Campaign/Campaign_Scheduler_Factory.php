<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Production scheduler composition.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Campaign;

use CampaignBridge\Providers\Mailchimp_Delivery_Gateway;
use CampaignBridge\Providers\Mailchimp_Provider;
use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Campaign_Snapshot_Repository;
use CampaignBridge\Repository\Database_Transaction;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Workflow\Campaign\Campaign_Scheduler;
use CampaignBridge\Workflow\Campaign\Random_Id_Generator;
use CampaignBridge\Workflow\Campaign\System_Clock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Builds the scheduler for a provider that supports scheduled delivery. */
final class Campaign_Scheduler_Factory {
	/** The scheduler for one provider, or null when it cannot schedule. */
	public static function create( string $provider ): ?Campaign_Scheduler {
		if ( 'mailchimp' !== $provider ) {
			return null;
		}

		return new Campaign_Scheduler(
			new Campaign_Repository(),
			new Campaign_Snapshot_Repository(),
			new Remote_Campaign_Reference_Repository(),
			new Delivery_Attempt_Repository(),
			new Audit_Event_Repository(),
			new Database_Transaction(),
			new Random_Id_Generator(),
			new System_Clock(),
			new Mailchimp_Delivery_Gateway(),
			( new Mailchimp_Provider() )->capabilities()
		);
	}
}
