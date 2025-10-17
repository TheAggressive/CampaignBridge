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
	'cb-block-editor-script',
	'dist/scripts/editor/editor.asset.php',
	array( 'wp-edit-post' ) // Additional dependencies.
);
$screen->enqueue_style(
	'cb-block-editor-styles',
	'dist/styles/editor/editor.css',
	array( 'wp-editor' )
);

?>

<div id="cb-block-editor-root" class="editor-screen"></div>
