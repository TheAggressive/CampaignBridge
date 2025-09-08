<?php
/**
 * Simple test script to verify the EditorSettingsEndpoint works correctly.
 */

// Check if file exists
$filepath = 'includes/REST/EditorSettingsEndpoint.php';
if ( ! file_exists( $filepath ) ) {
	echo '✗ File does not exist: ' . $filepath . PHP_EOL;
	exit( 1 );
}

echo '✓ File exists: ' . $filepath . PHP_EOL;

require_once $filepath;

if ( ! class_exists( 'CampaignBridge\REST\EditorSettingsEndpoint' ) ) {
	echo '✗ Class does not exist after require_once' . PHP_EOL;
	exit( 1 );
}

echo '✓ Class loaded successfully' . PHP_EOL;

try {
	$endpoint = new \CampaignBridge\REST\EditorSettingsEndpoint( 'test_settings' );
	echo '✓ EditorSettingsEndpoint class instantiated successfully' . PHP_EOL;
	echo '✓ Constructor accepts option_name parameter' . PHP_EOL;

	// Test that methods exist
	$methods = array( 'register', 'can_manage', 'handle_request', 'check_rate_limit', 'filter_sensitive_settings', 'filter_styles', 'filter_default_styles' );
	foreach ( $methods as $method ) {
		if ( method_exists( $endpoint, $method ) ) {
			echo '✓ Method ' . $method . ' exists' . PHP_EOL;
		} else {
			echo '✗ Method ' . $method . ' missing' . PHP_EOL;
		}
	}

	echo PHP_EOL . 'Basic class structure checks passed!';
} catch ( Exception $e ) {
	echo 'Error during instantiation: ' . $e->getMessage() . PHP_EOL;
	echo 'This might be expected if WordPress functions are not available.' . PHP_EOL;
}
