<?php
/**
 * Plugin Name: CampaignBridge
 * Description: Select posts from multiple post types and send them via Mailchimp using a saved template.
 * Version: 0.1.0
 * Author: Your Name
 *
 * @package CampaignBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// i18n.
add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'campaignbridge', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

// Custom autoloader for CampaignBridge.
if ( file_exists( __DIR__ . '/includes/autoload.php' ) ) {
	require_once __DIR__ . '/includes/autoload.php';
} else {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'CampaignBridge: Autoloader not found.', 'campaignbridge' ) . '</p></div>';
		}
	);
	return;
}

// Bootstrap plugin.
new \CampaignBridge\Plugin();
