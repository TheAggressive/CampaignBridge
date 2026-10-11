<?php
/**
 * Delivery policy settings repository.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Core\Storage;
use CampaignBridge\Domain\Campaign\Delivery_Policy;
use CampaignBridge\Domain\Campaign\Delivery_Policy_Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the policy saved by the manager-only Policies settings tab.
 *
 * The options are written by the settings form, which requires
 * `campaignbridge_manage`, so people who only approve or send cannot relax
 * the policy that constrains them.
 */
final class Delivery_Policy_Repository implements Delivery_Policy_Source {
	public const SEPARATE_DELIVERY = 'campaignbridge_policy_separate_delivery';
	public const TEST_DOMAINS      = 'campaignbridge_policy_test_recipient_domains';

	/**
	 * {@inheritDoc}
	 */
	public function current(): Delivery_Policy {
		$domains = Storage::get_option( self::TEST_DOMAINS, '' );

		return Delivery_Policy::from_settings(
			in_array( Storage::get_option( self::SEPARATE_DELIVERY, false ), array( true, 1, '1' ), true ),
			is_string( $domains ) ? $domains : ''
		);
	}
}
