<?php
/**
 * Email editor screen configuration.
 *
 * Assets are declared here so WordPress can print them in the admin header,
 * before classic notices and the application shell are rendered.
 *
 * @package CampaignBridge\Admin\Screens
 */

declare(strict_types=1);

return array(
	'application_screen' => true,
	'assets'             => array(
		'asset_styles'  => array(
			'campaignbridge-block-editor-styles' => array(
				'src'  => 'dist/styles/editor/editor.asset.php',
				'deps' => array( 'wp-editor', 'wp-block-library', 'wp-edit-blocks', 'wp-components', 'wp-edit-post' ),
			),
		),
		'asset_scripts' => array(
			'campaignbridge-block-editor-script' => 'dist/scripts/editor/editor.asset.php',
		),
	),
);
