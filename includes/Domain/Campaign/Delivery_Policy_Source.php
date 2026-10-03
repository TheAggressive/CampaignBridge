<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Typed port signatures are the contract.
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
	public function current(): Delivery_Policy;
}
