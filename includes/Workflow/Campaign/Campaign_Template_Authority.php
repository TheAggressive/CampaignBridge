<?php
/**
 * Template object authorization for campaign workflows.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolves whether an actor may use one CampaignBridge template. */
interface Campaign_Template_Authority {
	/**
	 * Determines whether the actor may use the exact template object.
	 *
	 * @param Campaign_Actor $actor       Explicit workflow actor.
	 * @param int            $template_id WordPress template post ID.
	 */
	public function can_use_template( Campaign_Actor $actor, int $template_id ): bool;
}
