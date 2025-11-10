<?php
/**
 * Unit tests for CampaignBridge Autoloader
 *
 * @package CampaignBridge\Tests\Unit
 */

namespace CampaignBridge\Tests\Unit;

use CampaignBridge_Autoloader;
use WP_UnitTestCase;

/**
 * Test CampaignBridge_Autoloader functionality
 *
 * Tests the class-based autoloader for security, performance, and correctness.
 */
class _Autoloader_Test extends WP_UnitTestCase {

	/**
	 * Original autoloader registered before tests
	 *
	 * @var bool
	 */
	private bool $original_registered = false;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Check if autoloader is already registered
		$this->original_registered = $this->is_autoloader_registered();

		// Clear cache for clean tests
		CampaignBridge_Autoloader::clear_cache();
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Clear cache after each test
		CampaignBridge_Autoloader::clear_cache();

		// Ensure autoloader is in expected state
		if ( $this->original_registered && ! $this->is_autoloader_registered() ) {
			CampaignBridge_Autoloader::register();
		} elseif ( ! $this->original_registered && $this->is_autoloader_registered() ) {
			CampaignBridge_Autoloader::unregister();
		}
	}

	/**
	 * Test that the autoloader can be registered and unregistered
	 */
	public function test_autoloader_registration(): void {
		// Unregister first to ensure clean state
		CampaignBridge_Autoloader::unregister();

		// Should not be registered
		$this->assertFalse( $this->is_autoloader_registered(), 'Autoloader should not be registered after unregister' );

		// Register
		$result = CampaignBridge_Autoloader::register();
		$this->assertTrue( $result, 'Autoloader registration should succeed' );
		$this->assertTrue( $this->is_autoloader_registered(), 'Autoloader should be registered after register call' );

		// Unregister
		$result = CampaignBridge_Autoloader::unregister();
		$this->assertTrue( $result, 'Autoloader unregistration should succeed' );
		$this->assertFalse( $this->is_autoloader_registered(), 'Autoloader should not be registered after unregister call' );
	}

	/**
	 * Test loading valid CampaignBridge classes
	 */
	public function test_loading_valid_campaignbridge_classes(): void {
		// Ensure autoloader is registered
		if ( ! $this->is_autoloader_registered() ) {
			CampaignBridge_Autoloader::register();
		}

		// Test loading existing classes
		$test_cases = array(
			'CampaignBridge\Notices',
			'CampaignBridge\Admin\Admin',
			'CampaignBridge\Admin\Controllers\Settings_Controller',
		);

		foreach ( $test_cases as $class_name ) {
			// Check if already loaded by bootstrap
			$already_loaded = class_exists( $class_name, false );

			if ( ! $already_loaded ) {
				// Trigger autoload
				class_exists( $class_name );

				// Should be loaded now
				$this->assertTrue( class_exists( $class_name, false ), "Class {$class_name} should be loaded after autoload trigger" );
			} else {
				// If already loaded, just verify it exists
				$this->assertTrue( class_exists( $class_name, false ), "Class {$class_name} should exist (was pre-loaded)" );
			}
		}
	}

	/**
	 * Test that non-CampaignBridge classes are ignored
	 */
	public function test_ignoring_non_campaignbridge_classes(): void {
		if ( ! $this->is_autoloader_registered() ) {
			CampaignBridge_Autoloader::register();
		}

		// These should not be handled by our autoloader
		$non_campaignbridge_classes = array(
			'WP_Post',
			'stdClass',
			'Some_Other_Plugin_Class',
			'Completely\Unrelated\Class',
		);

		foreach ( $non_campaignbridge_classes as $class_name ) {
			// These classes should not exist (or exist from other autoloaders)
			// The point is our autoloader shouldn't interfere
			CampaignBridge_Autoloader::load( $class_name );
			// Test passes if no exceptions thrown
			$this->assertTrue( true, "Autoloader should handle non-CampaignBridge class {$class_name} gracefully" );
		}
	}

	/**
	 * Test class map caching functionality
	 */
	public function test_class_map_caching(): void {
		// Clear cache first
		CampaignBridge_Autoloader::clear_cache();
		$initial_map = CampaignBridge_Autoloader::get_class_map();
		$this->assertEmpty( $initial_map, 'Class map should be empty after clearing' );

		// Load a class to populate cache
		if ( ! $this->is_autoloader_registered() ) {
			CampaignBridge_Autoloader::register();
		}

		// Use a class that should work and not be pre-loaded
		$test_class = 'CampaignBridge\Admin\Controllers\Settings_Controller';
		$was_loaded = class_exists( $test_class, false );

		// If not pre-loaded, trigger loading
		if ( ! $was_loaded ) {
			class_exists( $test_class );
		}

		// Check cache - should contain the class now
		$map_after_load = CampaignBridge_Autoloader::get_class_map();

		// The autoloader only caches successful loads, so check if we actually loaded it
		if ( ! $was_loaded && class_exists( $test_class, false ) ) {
			$this->assertArrayHasKey( $test_class, $map_after_load, 'Successfully loaded class should be in cache' );

			$cached_path = $map_after_load[ $test_class ];
			$this->assertFileExists( $cached_path, 'Cached file path should exist' );
			$this->assertStringEndsWith( 'Settings_Controller.php', $cached_path, 'Cached path should point to correct file' );
		} else {
			// If already loaded by bootstrap or some other mechanism, just verify cache exists
			$this->assertIsArray( $map_after_load, 'Class map should be an array' );
		}

		// Test cache clearing
		CampaignBridge_Autoloader::clear_cache();
		$cleared_map = CampaignBridge_Autoloader::get_class_map();
		$this->assertEmpty( $cleared_map, 'Class map should be empty after clearing' );
	}

	/**
	 * Test security: directory traversal prevention
	 */
	public function test_directory_traversal_prevention(): void {
		// These should be rejected for security
		$malicious_classes = array(
			'CampaignBridge\..\..\..\wp-config',
			'CampaignBridge\../../../../../etc/passwd',
			'CampaignBridge\/etc/passwd',
			'CampaignBridge\..\Admin',
		);

		foreach ( $malicious_classes as $class_name ) {
			// Should not load anything for these
			CampaignBridge_Autoloader::load( $class_name );

			// Class should not exist (unless it existed before)
			$this->assertFalse( class_exists( $class_name, false ), "Malicious class {$class_name} should not be loaded" );
		}
	}

	/**
	 * Test handling of invalid class paths
	 */
	public function test_invalid_class_path_handling(): void {
		$invalid_classes = array(
			'CampaignBridge\Invalid@Class',
			'CampaignBridge\Class with spaces',
			'CampaignBridge\Class-with-dashes',
			'CampaignBridge\Class#with#special#chars',
		);

		foreach ( $invalid_classes as $class_name ) {
			CampaignBridge_Autoloader::load( $class_name );
			$this->assertFalse( class_exists( $class_name, false ), "Invalid class {$class_name} should not be loaded" );
		}
	}

	/**
	 * Test handling of non-existent classes
	 */
	public function test_non_existent_class_handling(): void {
		$non_existent_classes = array(
			'CampaignBridge\NonExistentClass',
			'CampaignBridge\Admin\NonExistentController',
			'CampaignBridge\Invalid\Path\Class',
		);

		foreach ( $non_existent_classes as $class_name ) {
			$initial_exists = class_exists( $class_name, false );

			CampaignBridge_Autoloader::load( $class_name );

			// Should still not exist (unless loaded by another autoloader)
			$this->assertEquals( $initial_exists, class_exists( $class_name, false ), "Non-existent class {$class_name} should not be loaded by our autoloader" );
		}
	}

	/**
	 * Test that forbidden file patterns are rejected
	 */
	public function test_forbidden_file_patterns(): void {
		// These should be blocked for security
		$forbidden_patterns = array(
			'CampaignBridge\wp-config', // wp-config access
			'CampaignBridge\.env\Local', // Environment files
			'CampaignBridge\config\Local', // Config files
		);

		foreach ( $forbidden_patterns as $class_name ) {
			CampaignBridge_Autoloader::load( $class_name );
			$this->assertFalse( class_exists( $class_name, false ), "Forbidden pattern {$class_name} should not be loaded" );
		}
	}

	/**
	 * Test cache invalidation when file doesn't exist
	 */
	public function test_cache_invalidation_for_missing_files(): void {
		// Manually add a non-existent file to cache
		$reflection = new \ReflectionClass( CampaignBridge_Autoloader::class );
		$property   = $reflection->getProperty( 'class_map' );
		$property->setAccessible( true );

		$fake_class = 'CampaignBridge\FakeTestClass';
		$fake_path  = '/non/existent/path/FakeTestClass.php';

		$property->setValue( null, array( $fake_class => $fake_path ) );

		// Verify it's in cache
		$this->assertArrayHasKey( $fake_class, CampaignBridge_Autoloader::get_class_map() );

		// Try to load it - should remove from cache since file doesn't exist
		CampaignBridge_Autoloader::load( $fake_class );

		// Should be removed from cache
		$this->assertArrayNotHasKey( $fake_class, CampaignBridge_Autoloader::get_class_map(), 'Non-existent file should be removed from cache' );
	}

	/**
	 * Test path length limits
	 */
	public function test_path_length_limits(): void {
		// Create a very long class name that would exceed path limits
		$long_class_name = 'CampaignBridge\\' . str_repeat( 'VeryLongClassName', 50 );

		CampaignBridge_Autoloader::load( $long_class_name );

		$this->assertFalse( class_exists( $long_class_name, false ), 'Very long class names should be rejected' );
	}

	/**
	 * Test that autoloader doesn't interfere with existing classes
	 */
	public function test_no_interference_with_existing_classes(): void {
		// stdClass should exist
		$this->assertTrue( class_exists( 'stdClass', false ), 'stdClass should exist' );

		// Our autoloader shouldn't affect it
		CampaignBridge_Autoloader::load( 'stdClass' );
		$this->assertTrue( class_exists( 'stdClass', false ), 'stdClass should still exist after autoloader call' );
	}

	/**
	 * Helper method to check if our autoloader is registered
	 */
	private function is_autoloader_registered(): bool {
		$autoloaders = spl_autoload_functions();

		foreach ( $autoloaders as $autoloader ) {
			if ( is_array( $autoloader ) && $autoloader[0] === CampaignBridge_Autoloader::class ) {
				return true;
			}
		}

		return false;
	}
}
