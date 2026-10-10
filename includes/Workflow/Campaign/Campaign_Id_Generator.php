<?php
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
	/**
	 * A new random identifier with the given prefix.
	 *
	 * @param string $prefix Identifier prefix.
	 */
	public function generate( string $prefix ): string;
}
