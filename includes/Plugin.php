<?php
/**
 * Plugin bootstrap and orchestrator for CampaignBridge.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge;

use CampaignBridge\Blocks\Blocks;
use CampaignBridge\Notices;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\REST\Routes as RestRoutes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Composes WordPress hooks and initializes all subsystems. */
class Plugin {
	/** Initialize the plugin after the bootstrap runtime checks. */
	public function __construct() {
		Notices::init();
		Blocks::init();
		Post_Type_Email_Template::init();
		\CampaignBridge\Admin\Admin::get_instance();

		RestRoutes::init();
		\add_action( 'rest_api_init', array( RestRoutes::class, 'register' ) );
	}
}
