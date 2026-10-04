<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Campaigns screen configuration.
 *
 * @package CampaignBridge
 */

return array(
	'menu_title'     => __( 'Campaigns', 'campaignbridge' ),
	'page_title'     => __( 'Campaigns', 'campaignbridge' ),
	'capability'     => \CampaignBridge\Core\Capabilities::CREATE_CAMPAIGNS,
	'position'       => 0,
	'product_header' => true,
	'description'    => __( 'Create, review, and deliver email campaigns.', 'campaignbridge' ),
	'assets'         => array(
		'asset_styles' => array(
			'campaignbridge-campaigns' => array(
				'src'  => 'dist/styles/admin/screens/campaigns.asset.php',
				'deps' => array( 'wp-components' ),
			),
		),
	),
);
