<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Production draft handoff composition.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Campaign;

use CampaignBridge\Providers\Mailchimp_Draft_Gateway;
use CampaignBridge\Providers\Mailchimp_Provider;
use CampaignBridge\Providers\Mailchimp_Token_Mapper;
use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Campaign_Snapshot_Repository;
use CampaignBridge\Repository\Database_Transaction;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Services\Provider\Provider_Discovery_Factory;
use CampaignBridge\Workflow\Campaign\Campaign_Draft_Handoff;
use CampaignBridge\Workflow\Campaign\Random_Id_Generator;
use CampaignBridge\Workflow\Campaign\System_Clock;
use CampaignBridge\Services\Lock\Lock_Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Builds the draft handoff for a provider that supports remote drafts. */
final class Campaign_Draft_Handoff_Factory {
	/** The handoff for one provider, or null when it cannot create drafts. */
	public static function create( string $provider ): ?Campaign_Draft_Handoff {
		$discovery = Provider_Discovery_Factory::service( $provider );
		if ( 'mailchimp' !== $provider || null === $discovery ) {
			return null;
		}

		return new Campaign_Draft_Handoff(
			new Campaign_Repository(),
			new Campaign_Snapshot_Repository(),
			new Remote_Campaign_Reference_Repository(),
			new Delivery_Attempt_Repository(),
			new Audit_Event_Repository(),
			new Database_Transaction(),
			new Random_Id_Generator(),
			new System_Clock(),
			new Mailchimp_Draft_Gateway(),
			( new Mailchimp_Provider() )->capabilities(),
			new Mailchimp_Token_Mapper(),
			$discovery,
			Lock_Factory::manager()
		);
	}
}
