<?php
/**
 * Uninstall CampaignBridge.
 *
 * @package CampaignBridge
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin settings.
delete_option( 'campaignbridge_settings' );

// Delete Mailchimp-related transients for any lingering keys (best-effort patterns).
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cb_mc_%' OR option_name LIKE '_transient_timeout_cb_mc_%'" );
