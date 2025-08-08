<?php
/*
Plugin Name: CampaignBridge
Description: Select posts from multiple post types and send them via Mailchimp using a saved template.
Version: 0.1.0
Author: Your Name
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-campaignbridge.php';
require_once __DIR__ . '/includes/class-campaignbridge-notices.php';

new CampaignBridge();
