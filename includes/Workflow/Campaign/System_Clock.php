<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
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
	public function now(): string {
		return gmdate( 'Y-m-d\TH:i:s\Z' );
	}
}
