<?php // phpcs:disable Squiz.Commenting.FunctionComment
/**
 * Campaign workflow identifier generator.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Campaign_Id_Generator {
	public function generate( string $prefix ): string;
}
