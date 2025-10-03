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
 * Custom autoloader function with enhanced error handling and security
 *
 * @param string $class_name The fully-qualified class name.
 * @return void
 */
function campaignbridge_autoloader( $class_name ) {
	// Debug logging for troubleshooting.
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( "CampaignBridge Autoloader: Attempting to load class: $class_name" );
	}

	// Configuration constants.
	$namespace_prefix = 'CampaignBridge\\';
	$base_directory   = __DIR__;
	$max_path_length  = 500; // Prevent excessive path lengths.

	// Performance optimization: cache resolved paths.
	static $class_map = array();

	// Only handle CampaignBridge classes.
	if ( 0 !== strpos( $class_name, $namespace_prefix ) ) {
		return;
	}

	// Check cache first for performance.
	if ( isset( $class_map[ $class_name ] ) ) {
		if ( file_exists( $class_map[ $class_name ] ) ) {
			require_once $class_map[ $class_name ];
			return;
		} else {
			// Cached file doesn't exist, remove from cache.
			unset( $class_map[ $class_name ] );
		}
	}

	// Convert namespace to PSR-4 file path.
	$relative_class = str_replace( $namespace_prefix, '', $class_name );

	// Security: Validate the relative path to prevent directory traversal.
	if ( ! campaignbridge_validate_class_path( $relative_class ) ) {
		campaignbridge_log_autoload_error( "Invalid class path: $relative_class" );
		return;
	}

	$file_path = $base_directory . '/' . str_replace( '\\', '/', $relative_class ) . '.php';

	// Security: Validate absolute file path.
	if ( ! campaignbridge_validate_file_path( $file_path, $base_directory, $max_path_length ) ) {
		campaignbridge_log_autoload_error( "Invalid file path: $file_path" );
		return;
	}

	// Load the file if it exists.
	if ( file_exists( $file_path ) ) {
		// Cache successful resolution for performance.
		$class_map[ $class_name ] = $file_path;
		require_once $file_path;
	} else {
		campaignbridge_log_autoload_error( "File not found: $file_path for class: $class_name" );
	}
}

/**
 * Validate relative class path to prevent directory traversal
 *
 * @param string $relative_class The relative class path.
 * @return bool True if path is valid, false otherwise.
 */
function campaignbridge_validate_class_path( $relative_class ) {
	// Basic validation - prevent obvious directory traversal.
	if ( false !== strpos( $relative_class, '..' ) ) {
		return false;
	}

	// Ensure path doesn't start with / .
	if ( 0 === strpos( $relative_class, '/' ) ) {
		return false;
	}

	// Allow alphanumeric, underscores, forward slashes, and backslashes.
	if ( ! preg_match( '/^[a-zA-Z0-9_\/\\\\]+$/', $relative_class ) ) {
		return false;
	}

	return true;
}

/**
 * Validate absolute file path for security
 *
 * @param string $file_path The absolute file path.
 * @param string $base_directory The expected base directory.
 * @param int    $max_length Maximum allowed path length.
 * @return bool True if path is valid, false otherwise.
 */
function campaignbridge_validate_file_path( $file_path, $base_directory, $max_length ) {
	// Check path length.
	if ( $max_length < strlen( $file_path ) ) {
		return false;
	}

	// Ensure file is within the expected directory.
	$real_file_path = realpath( $file_path );
	$real_base_dir  = realpath( $base_directory );

	if ( false === $real_file_path || false === $real_base_dir ) {
		return false;
	}

	// Ensure the file path starts with the base directory.
	if ( 0 !== strpos( $real_file_path, $real_base_dir ) ) {
		return false;
	}

	// Additional security: ensure we're not loading sensitive files.
	$forbidden_patterns = array(
		$base_directory . '/../',
		$base_directory . '/../../',
		'.git',
		'.env',
		'config.php',
		'wp-config.php',
	);

	foreach ( $forbidden_patterns as $pattern ) {
		if ( false !== strpos( $real_file_path, $pattern ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Log autoloader errors for debugging.
 *
 * @param string $message Error message.
 * @return void
 */
function campaignbridge_log_autoload_error( $message ) {
	if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'CampaignBridge Autoloader: ' . $message );
	}
}

// Register the autoloader.
spl_autoload_register( 'campaignbridge_autoloader' );
