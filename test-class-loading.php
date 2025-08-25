<?php
require_once __DIR__ . '/includes/autoload.php';

echo "Testing class loading...\n";

if ( class_exists( 'CampaignBridge\REST\MailchimpRoutes' ) ) {
	echo "✓ MailchimpRoutes class found!\n";

	// Test if the static methods exist
	if ( method_exists( 'CampaignBridge\REST\MailchimpRoutes', 'init' ) ) {
		echo "✓ init() method exists\n";
	} else {
		echo "✗ init() method missing\n";
	}

	if ( method_exists( 'CampaignBridge\REST\MailchimpRoutes', 'register' ) ) {
		echo "✓ register() method exists\n";
	} else {
		echo "✗ register() method missing\n";
	}
} else {
	echo "✗ MailchimpRoutes class NOT found!\n";
}

echo "Test completed.\n";
