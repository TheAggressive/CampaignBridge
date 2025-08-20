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

	// Convert namespace to file path.
	$relative_class = str_replace( 'CampaignBridge\\', '', $class );
	$file_path      = __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

	// Check if file exists.
	if ( file_exists( $file_path ) ) {
		require_once $file_path;
		return;
	}

	// Try WordPress naming convention (class-{name}.php).
	$class_name   = strtolower( str_replace( '_', '-', basename( str_replace( '\\', '/', $relative_class ) ) ) );
	$wp_file_path = __DIR__ . '/' . dirname( str_replace( '\\', '/', $relative_class ) ) . '/class-' . $class_name . '.php';

	if ( file_exists( $wp_file_path ) ) {
		require_once $wp_file_path;
		return;
	}
}

// Register the autoloader.
spl_autoload_register( 'campaignbridge_autoloader' );
