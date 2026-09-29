<?php
/**
 * Campaign template input persistence port.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Loads live template data without compiling or authorizing it. */
interface Campaign_Template_Input_Source {
	/**
	 * Loads one template's live canonical input.
	 *
	 * @param int $template_id WordPress template post ID.
	 */
	public function get( int $template_id ): ?Campaign_Template_Input;
}
