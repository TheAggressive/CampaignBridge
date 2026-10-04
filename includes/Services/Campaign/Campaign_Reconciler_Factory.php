<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Production campaign reconciliation composition.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Campaign;

use CampaignBridge\Providers\Mailchimp_Draft_Gateway;
use CampaignBridge\Providers\Mailchimp_Provider;
use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Database_Transaction;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Workflow\Campaign\Campaign_Reconciler;
use CampaignBridge\Workflow\Campaign\Random_Id_Generator;
use CampaignBridge\Workflow\Campaign\System_Clock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Builds the reconciler for a provider, or null when it has no reconciliation adapter. */
final class Campaign_Reconciler_Factory {
	public static function create( string $provider ): ?Campaign_Reconciler {
		if ( 'mailchimp' !== $provider ) {
			return null;
		}

		return new Campaign_Reconciler(
			new Campaign_Repository(),
			new Remote_Campaign_Reference_Repository(),
			new Delivery_Attempt_Repository(),
			new Audit_Event_Repository(),
			new Database_Transaction(),
			new Random_Id_Generator(),
			new System_Clock(),
			new Mailchimp_Draft_Gateway(),
			( new Mailchimp_Provider() )->capabilities()
		);
	}
}
