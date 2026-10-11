<?php
/**
 * Common shape of a normalized discovered provider reference.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A display-safe provider reference that never carries a raw payload. */
interface Discovered_Item {
	/**
	 * The item's cached values.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array;
}
