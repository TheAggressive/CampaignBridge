<?php
/**
 * Debug script to test plugin loading step by step
 */

echo "=== CampaignBridge Plugin Debug ===\n";

// Step 1: Test autoloader
echo "\n1. Testing autoloader...\n";
if ( file_exists( 'includes/autoload.php' ) ) {
	require_once 'includes/autoload.php';
	echo "✓ Autoloader loaded\n";
} else {
	echo "✗ Autoloader not found\n";
	exit( 1 );
}

// Step 2: Test basic class loading
echo "\n2. Testing basic class loading...\n";
try {
	if ( class_exists( 'CampaignBridge\\Plugin' ) ) {
		echo "✓ Plugin class found\n";
	} else {
		echo "✗ Plugin class not found\n";
		exit( 1 );
	}
} catch ( Exception $e ) {
	echo '✗ Error loading Plugin class: ' . $e->getMessage() . "\n";
	exit( 1 );
}

// Step 3: Test Service_Container
echo "\n3. Testing Service_Container...\n";
try {
	if ( class_exists( 'CampaignBridge\\Core\\Service_Container' ) ) {
		echo "✓ Service_Container class found\n";
	} else {
		echo "✗ Service_Container class not found\n";
		exit( 1 );
	}
} catch ( Exception $e ) {
	echo '✗ Error loading Service_Container: ' . $e->getMessage() . "\n";
	exit( 1 );
}

// Step 4: Test provider classes
echo "\n4. Testing provider classes...\n";
try {
	if ( class_exists( 'CampaignBridge\\Providers\\Mailchimp_Provider' ) ) {
		echo "✓ Mailchimp_Provider class found\n";
	} else {
		echo "✗ Mailchimp_Provider class not found\n";
	}

	if ( class_exists( 'CampaignBridge\\Providers\\Html_Provider' ) ) {
		echo "✓ Html_Provider class found\n";
	} else {
		echo "✗ Html_Provider class not found\n";
	}
} catch ( Exception $e ) {
	echo '✗ Error loading provider classes: ' . $e->getMessage() . "\n";
}

// Step 5: Test Service_Container instantiation
echo "\n5. Testing Service_Container instantiation...\n";
try {
	$container = new \CampaignBridge\Core\Service_Container();
	echo "✓ Service_Container instantiated\n";
} catch ( Exception $e ) {
	echo '✗ Error instantiating Service_Container: ' . $e->getMessage() . "\n";
	exit( 1 );
}

// Step 6: Test Service_Container initialization
echo "\n6. Testing Service_Container initialization...\n";
try {
	$container->initialize();
	echo "✓ Service_Container initialized\n";
} catch ( Exception $e ) {
	echo '✗ Error initializing Service_Container: ' . $e->getMessage() . "\n";
	exit( 1 );
}

// Step 7: Test getting providers from container
echo "\n7. Testing provider retrieval...\n";
try {
	$mailchimp = $container->get( 'mailchimp_provider' );
	echo "✓ Mailchimp provider retrieved\n";

	$html = $container->get( 'html_provider' );
	echo "✓ HTML provider retrieved\n";
} catch ( Exception $e ) {
	echo '✗ Error retrieving providers: ' . $e->getMessage() . "\n";
	exit( 1 );
}

// Step 8: Test Plugin instantiation
echo "\n8. Testing Plugin instantiation...\n";
try {
	$plugin = new \CampaignBridge\Plugin();
	echo "✓ Plugin instantiated successfully!\n";
} catch ( Exception $e ) {
	echo '✗ Error instantiating Plugin: ' . $e->getMessage() . "\n";
	echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
	exit( 1 );
}

echo "\n=== All tests passed! Plugin should work correctly ===\n";
