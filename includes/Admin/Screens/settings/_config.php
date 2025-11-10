<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Settings Page Configuration
 *
 * This file is OPTIONAL. If not present, defaults are used.
 */

return array(
	// Page configuration.
	'menu_title'  => __( 'Settings', 'campaignbridge' ),
	'page_title'  => __( 'CampaignBridge Settings', 'campaignbridge' ),
	'capability'  => 'manage_options',
	'position'    => 10,
	'description' => __( 'Configure your email campaign settings and integrations.', 'campaignbridge' ),

);
