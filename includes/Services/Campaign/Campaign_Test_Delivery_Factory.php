<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Production test-delivery composition.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Campaign;

use CampaignBridge\Providers\Mailchimp_Provider;
use CampaignBridge\Providers\Mailchimp_Test_Gateway;
use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Campaign_Snapshot_Repository;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Workflow\Campaign\Campaign_Test_Delivery;
use CampaignBridge\Workflow\Campaign\Random_Id_Generator;
use CampaignBridge\Workflow\Campaign\System_Clock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Builds test delivery for a provider that supports test sends. */
final class Campaign_Test_Delivery_Factory {
	/** The test delivery for one provider, or null when it cannot send tests. */
	public static function create( string $provider ): ?Campaign_Test_Delivery {
		if ( 'mailchimp' !== $provider ) {
			return null;
		}

		return new Campaign_Test_Delivery(
			new Campaign_Repository(),
			new Campaign_Snapshot_Repository(),
			new Remote_Campaign_Reference_Repository(),
			new Delivery_Attempt_Repository(),
			new Audit_Event_Repository(),
			new Random_Id_Generator(),
			new System_Clock(),
			new Mailchimp_Test_Gateway(),
			( new Mailchimp_Provider() )->capabilities()
		);
	}
}
