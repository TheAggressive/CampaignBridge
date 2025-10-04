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
define( 'CB_BASENAME', plugin_basename( __FILE__ ) );
define( 'CB_FILE', __FILE__ );
define( 'CB_VERSION', '0.2.0' );
define( 'CB_INCLUDES_PATH', CB_PATH . 'includes/' );
define( 'CB_ASSETS_PATH', CB_PATH . 'assets/' );
define( 'CB_ASSETS_URL', CB_URL . 'assets/' );
define( 'CB_MIN_PHP_VERSION', '8.2' );
define( 'CB_MIN_WP_VERSION', '6.5.0' );

// i18n.
add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'campaignbridge', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

// Plugin activation/deactivation hooks.
register_activation_hook( __FILE__, 'campaignbridge_activate' );
register_deactivation_hook( __FILE__, 'campaignbridge_deactivate' );

/**
 * Plugin activation hook.
 * Performs setup tasks when the plugin is activated.
 *
 * @return void
 */
function campaignbridge_activate() {
	// Check PHP version compatibility.
	if ( version_compare( PHP_VERSION, CB_MIN_PHP_VERSION, '<' ) ) {
		deactivate_plugins( CB_BASENAME );
		wp_die(
			esc_html__(
				sprintf(
					'CampaignBridge requires PHP %s or higher. Your current version is %s.',
					CB_MIN_PHP_VERSION,
					PHP_VERSION
				),
				'campaignbridge'
			),
			'Plugin Activation Error',
			array( 'back_link' => true )
		);
	}

	// Check WordPress version compatibility.
	if ( version_compare( get_bloginfo( 'version' ), CB_MIN_WP_VERSION, '<' ) ) {
		deactivate_plugins( CB_BASENAME );
		wp_die(
			esc_html__(
				sprintf(
					'CampaignBridge requires WordPress %s or higher. Your current version is %s.',
					CB_MIN_WP_VERSION,
					get_bloginfo( 'version' )
				),
				'campaignbridge'
			),
			'Plugin Activation Error',
			array( 'back_link' => true )
		);
	}

	// Grant custom capability to administrators.
	$admin_role = get_role( 'administrator' );
	if ( $admin_role && ! $admin_role->has_cap( 'campaignbridge_manage' ) ) {
		$admin_role->add_cap( 'campaignbridge_manage' );
	}

	// Flush rewrite rules on activation.
	flush_rewrite_rules();

	// Log activation (debug only).
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'CampaignBridge plugin activated successfully.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security event logging.
	}
}

/**
 * Plugin deactivation hook.
 * Performs cleanup tasks when the plugin is deactivated.
 *
 * @return void
 */
function campaignbridge_deactivate() {
	// Flush rewrite rules on deactivation.
	flush_rewrite_rules();

	// Log deactivation (debug only).
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'CampaignBridge plugin deactivated.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security event logging.
	}
}

// Custom autoloader for CampaignBridge.
if ( file_exists( __DIR__ . '/includes/autoload.php' ) ) {
	require_once __DIR__ . '/includes/autoload.php';
} else {
	add_action(
		'admin_notices',
		function () {
			$class   = 'notice notice-error';
			$message = esc_html__( 'CampaignBridge: Autoloader not found. Please reinstall the plugin.', 'campaignbridge' );
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
		}
	);
	return;
}

// Bootstrap plugin with enhanced error handling.
try {
	new \CampaignBridge\Plugin();
} catch ( \Exception $e ) {
	// Enhanced error handling for bootstrap failures.
	add_action(
		'admin_notices',
		function () use ( $e ) {
			$class   = 'notice notice-error is-dismissible';
			$message = sprintf(
				'<strong>%1$s</strong> %2$s<br><small>%3$s</small>',
				esc_html__( 'CampaignBridge Error:', 'campaignbridge' ),
				esc_html( $e->getMessage() ),
				esc_html__( 'Check the error logs for more details.', 'campaignbridge' )
			);
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
		}
	);

	// Log the error for debugging (debug only).
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'CampaignBridge Bootstrap Error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security event logging.
		error_log( 'Stack trace: ' . $e->getTraceAsString() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security event logging.
	}
}
