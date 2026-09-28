<?php // phpcs:disable Squiz.Commenting.FunctionComment
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
	public function now(): string;
}
