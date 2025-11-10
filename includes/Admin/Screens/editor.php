<?php
/**
 * Email Template Editor Screen
 *
 * This is a simple screen with no tabs.
 * Controller auto-discovered: Editor_Controller (if exists)
 *
 * @package CampaignBridge
 */

global $screen;

// Add additional dependencies to the existing .asset.php dependencies.
$screen->asset_enqueue_script(
	'campaignbridge-block-editor-script',
	'dist/scripts/editor/editor.asset.php',
);

$screen->enqueue_style(
	'campaignbridge-block-editor-styles',
	'dist/styles/editor/editor.css',
	array( 'wp-editor', 'wp-block-library', 'wp-edit-blocks', 'wp-components', 'wp-edit-post' )
);

?>

<div id="cb-block-editor-root" class="editor-screen"></div>

<style>
	h1 {
	color: red;
	}
</style>
