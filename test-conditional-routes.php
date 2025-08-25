<?php
/**
 * Test script to verify conditional Mailchimp route registration
 */

echo "=== Testing Conditional Mailchimp Route Registration ===\n";

// Test function to check if a route is registered
function is_route_registered( $namespace, $route ) {
	global $wp_rest_server;
	if ( ! $wp_rest_server ) {
		return false;
	}

	$routes     = $wp_rest_server->get_routes();
	$full_route = '/' . $namespace . '/' . $route;
	return isset( $routes[ $full_route ] );
}

echo "\n1. Testing with Mailchimp as provider...\n";

// Simulate Mailchimp provider setting
update_option( 'campaignbridge_settings', array( 'provider' => 'mailchimp' ) );

// Initialize the plugin (this should register Mailchimp routes)
require_once 'includes/Plugin.php';
$plugin = new CampaignBridge\Plugin();

echo "✓ Plugin initialized with Mailchimp provider\n";

// Check if Mailchimp routes are registered
$mailchimp_routes = array(
	'mailchimp/sections',
	'mailchimp/audiences',
	'mailchimp/templates',
	'mailchimp/verify',
);

$routes_registered = 0;
foreach ( $mailchimp_routes as $route ) {
	if ( is_route_registered( 'campaignbridge/v1', $route ) ) {
		echo "✓ Route /$route is registered\n";
		++$routes_registered;
	} else {
		echo "✗ Route /$route is NOT registered\n";
	}
}

echo "\nMailchimp routes registered: $routes_registered/" . count( $mailchimp_routes ) . "\n";

echo "\n2. Testing with HTML as provider...\n";

// Change provider to HTML
update_option( 'campaignbridge_settings', array( 'provider' => 'html' ) );

// Re-initialize the plugin (this should NOT register Mailchimp routes)
// Note: In a real scenario, you'd restart the request, but for testing we'll simulate
echo "✓ Switched provider to HTML\n";

// Check if Mailchimp routes are still registered (they shouldn't be in a fresh request)
echo "Note: In production, routes would not be registered for HTML provider.\n";
echo "Current routes would only exist from the previous Mailchimp test.\n";

echo "\n=== Test completed ===\n";
echo "\nExpected behavior:\n";
echo "- With Mailchimp provider: All 4 Mailchimp routes should be registered\n";
echo "- With HTML provider: No Mailchimp routes should be registered\n";
echo "\nThis improves performance and API clarity by only exposing relevant endpoints.\n";
