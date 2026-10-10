<?php
/**
 * Campaign workflow clock.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Campaign_Clock {
	/**
	 * The current time as a canonical UTC timestamp.
	 */
	public function now(): string;
}
