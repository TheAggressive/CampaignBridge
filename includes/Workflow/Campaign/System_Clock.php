<?php
/**
 * UTC system clock.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Supplies canonical UTC timestamps. */
final class System_Clock implements Campaign_Clock {
	/**
	 * {@inheritDoc}
	 */
	public function now(): string {
		return gmdate( 'Y-m-d\TH:i:s\Z' );
	}
}
