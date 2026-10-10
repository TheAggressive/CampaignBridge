<?php
/**
 * Delivery policy port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Supplies the site's current delivery governance policy. */
interface Delivery_Policy_Source {
	/**
	 * The site's delivery policy now.
	 */
	public function current(): Delivery_Policy;
}
