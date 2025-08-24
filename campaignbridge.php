<?php
/**
 * Plugin Name: CampaignBridge
 * Description: A comprehensive WordPress plugin for creating and managing email campaigns with dynamic content from multiple post types. Features include Mailchimp integration, custom email templates, block-based email design, and automated campaign generation. Perfect for newsletters, promotional emails, and content marketing automation.
 * Requires at least: 6.5.0
 * Tested up to: 6.8.2
 * Requires PHP: 8.2
 * Author: Aggressive Network, LLC
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: campaignbridge
 * Domain Path: /languages
 * Network: false
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'CB_PATH', plugin_dir_path( __FILE__ ) );
define( 'CB_URL', plugin_dir_url( __FILE__ ) );
define( 'CB_VERSION', '0.2.0' );

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
