<?php
/**
 * The site's current delivery policy for adapters.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Campaign;

use CampaignBridge\Domain\Campaign\Delivery_Policy;
use CampaignBridge\Repository\Delivery_Policy_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reads the delivery policy so adapters never reach the repository directly. */
final class Delivery_Policy_Reader {
	/** The policy in force now. */
	public static function current(): Delivery_Policy {
		return ( new Delivery_Policy_Repository() )->current();
	}
}
