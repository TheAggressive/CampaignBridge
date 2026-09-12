<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Settings Page Configuration
 *
 * This file is OPTIONAL. If not present, defaults are used.
 */

return array(
	// Page configuration.
	'menu_title'     => __( 'Settings', 'campaignbridge' ),
	'page_title'     => __( 'CampaignBridge Settings', 'campaignbridge' ),
	'capability'     => 'campaignbridge_manage',
	'position'       => 10,
	'product_header' => true,
	'show_journey'   => true,
	'description'    => __( 'Configure your email campaign settings and integrations.', 'campaignbridge' ),
	'assets'         => array(
		'asset_styles' => array(
			'campaignbridge-settings' => 'dist/styles/admin/screens/settings.asset.php',
		),
	),
	'tabs'           => array(
		'general'   => array(
			'label' => __( 'General', 'campaignbridge' ),
			'order' => 10,
		),
		'brand'     => array(
			'label' => __( 'Brand', 'campaignbridge' ),
			'order' => 20,
		),
		'providers' => array(
			'label' => __( 'Providers', 'campaignbridge' ),
			'order' => 30,
		),
	),
);
