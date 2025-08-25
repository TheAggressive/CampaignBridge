<?php
/**
 * Test loading in WordPress-like environment
 */

// Simulate WordPress ABSPATH constant
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

echo "=== WordPress-like Loading Test ===\n";

// Load autoloader (same way as main plugin file)
if ( file_exists( __DIR__ . '/includes/autoload.php' ) ) {
	echo "✓ Autoloader file exists\n";
	require_once __DIR__ . '/includes/autoload.php';
	echo "✓ Autoloader loaded\n";
} else {
	echo "✗ Autoloader not found\n";
	exit( 1 );
}

// Test class loading
echo "\nTesting class loading...\n";

$classes_to_test = array(
	'CampaignBridge\REST\Routes',
	'CampaignBridge\REST\MailchimpRoutes',
	'CampaignBridge\Plugin',
);

foreach ( $classes_to_test as $class_name ) {
	if ( class_exists( $class_name ) ) {
		echo "✓ $class_name - FOUND\n";

		// Test key methods
		if ( $class_name === 'CampaignBridge\REST\MailchimpRoutes' ) {
			if ( method_exists( $class_name, 'init' ) ) {
				echo "  ✓ init() method exists\n";
			}
			if ( method_exists( $class_name, 'register' ) ) {
				echo "  ✓ register() method exists\n";
			}
		}
	} else {
		echo "✗ $class_name - NOT FOUND\n";
	}
}

echo "\n=== Test completed ===\n";
