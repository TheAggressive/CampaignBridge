<?php
/**
 * Custom autoloader for CampaignBridge
 * Handles WordPress naming conventions and PSR-4 autoloading
 *
 * @package CampaignBridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom autoloader function
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
function campaignbridge_autoloader( $class ) {
	// Only handle CampaignBridge classes.
	if ( strpos( $class, 'CampaignBridge\\' ) !== 0 ) {
		return;
	}

	// Convert namespace to PSR-4 file path.
	$relative_class = str_replace( 'CampaignBridge\\', '', $class );
	$file_path      = __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

	// Load the file if it exists.
	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
}

// Register the autoloader.
spl_autoload_register( 'campaignbridge_autoloader' );
