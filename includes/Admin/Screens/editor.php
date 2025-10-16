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
	'dist/scripts/editor/editor.js',
	array( 'wp-edit-post' ) // Additional dependencies.
);
$screen->enqueue_style( 'cb-block-editor-core-styles', 'dist/scripts/editor/editor.css' );
$screen->enqueue_style( 'cb-block-editor-styles', 'dist/styles/editor/editor.css', array( 'wp-edit-post' ) );



?>

<div id="cb-block-editor-root" class="editor-screen"></div>
