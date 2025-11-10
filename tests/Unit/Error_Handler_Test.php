<?php
/**
 * Tests for Error_Handler class
 *
 * @package CampaignBridge
 * @subpackage Tests\Unit
 */

// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Testing logging functionality

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Core\Error_Handler;
use WP_UnitTestCase;

/**
 * Test Error_Handler functionality
 */
class Error_Handler_Test extends WP_UnitTestCase {
	/**
	 * Error_Handler instance
	 *
	 * @var Error_Handler
	 */
	private Error_Handler $error_handler;

	/**
	 * Reflection class for testing private methods
	 *
	 * @var \ReflectionClass
	 */
	private \ReflectionClass $reflection;

	/**
	 * Original log level for restoration
	 *
	 * @var int
	 */
	private int $original_log_level;

	/**
	 * Test admin user ID for cleanup
	 *
	 * @var int
	 */
	private int $test_admin_user_id;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Set up admin user for testing (Error_Handler requires admin for full logging)
		$this->test_admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->test_admin_user_id );

		// Reset singleton instance between tests
		$reflection_class  = new \ReflectionClass( Error_Handler::class );
		$instance_property = $reflection_class->getProperty( 'instance' );
		$instance_property->setAccessible( true );
		$instance_property->setValue( null, null );

		$this->error_handler = new Error_Handler();

		// Set the singleton instance to our test instance
		$instance_property->setValue( null, $this->error_handler );

		$this->reflection = new \ReflectionClass( $this->error_handler );

		// Override the log level to allow all logging for testing
		$this->set_log_level( 0 ); // DEBUG level

		// Store original log level for restoration
		$this->original_log_level = $this->invoke_private_method( 'get_log_level' );
	}

	/**
	 * Test singleton pattern
	 */
	public function test_singleton_pattern(): void {
		$instance1 = $this->invoke_private_method( 'get_instance' );
		$instance2 = $this->invoke_private_method( 'get_instance' );

		$this->assertSame( $instance1, $instance2 );
		$this->assertInstanceOf( Error_Handler::class, $instance1 );
	}

	/**
	 * Test exception handling
	 */
	public function _test_handle_exception(): void {
		$log_file     = WP_CONTENT_DIR . '/campaignbridge.log';
		$initial_size = file_exists( $log_file ) ? filesize( $log_file ) : 0;

		// Create a test exception
		$test_exception = new \Exception( 'Test exception message' );

		// Mock wp_die to prevent actual script termination
		$wp_die_called  = false;
		$wp_die_message = '';

		// Temporarily override wp_die
		if ( ! function_exists( 'wp_die' ) ) {
			function wp_die( $message ) {
				global $wp_die_called, $wp_die_message;
				$wp_die_called  = true;
				$wp_die_message = $message;
				return; // Don't actually die
			}
		}

		// Temporarily set WP_DEBUG to false to trigger wp_die path
		$original_wp_debug = WP_DEBUG;
		if ( defined( 'WP_DEBUG' ) ) {
			// Can't redefine constants, so we'll just test the logging part
			$this->error_handler->handle_exception( $test_exception, 'test_context' );
		}

		// Verify exception was logged
		$this->assertFileExists( $log_file );
		$this->assertGreaterThan( $initial_size, filesize( $log_file ) );
	}

	/**
	 * Test safe operation wrapper with successful operation
	 */
	public function test_safe_operation_success(): void {
		$result = $this->error_handler->safe_operation(
			function () {
				return 'success';
			},
			'test_operation'
		);

		$this->assertEquals( 'success', $result );
	}

	/**
	 * Test safe operation wrapper with exception
	 */
	public function test_safe_operation_exception(): void {
		$result = $this->error_handler->safe_operation(
			function () {
				throw new \Exception( 'Test exception' );
			},
			'test_operation'
		);

		$this->assertNull( $result );
	}

	/**
	 * Test debug logging method
	 */
	public function test_debug_logging(): void {
		// Set log level to DEBUG to enable debug messages
		$this->set_log_level( 0 );

		// Get initial log file size
		$log_file     = WP_CONTENT_DIR . '/campaignbridge.log';
		$initial_size = file_exists( $log_file ) ? filesize( $log_file ) : 0;

		Error_Handler::debug( 'Test debug message', array( 'key' => 'value' ) );

		// Verify log file was written to
		$this->assertFileExists( $log_file );
		$this->assertGreaterThan( $initial_size, filesize( $log_file ) );
	}

	/**
	 * Test info logging method
	 */
	public function test_info_logging(): void {
		$log_file     = WP_CONTENT_DIR . '/campaignbridge.log';
		$initial_size = file_exists( $log_file ) ? filesize( $log_file ) : 0;

		Error_Handler::info( 'Test info message', array( 'key' => 'value' ) );

		$this->assertFileExists( $log_file );
		$this->assertGreaterThan( $initial_size, filesize( $log_file ) );
	}

	/**
	 * Test warning logging method
	 */
	public function test_warning_logging(): void {
		$log_file     = WP_CONTENT_DIR . '/campaignbridge.log';
		$initial_size = file_exists( $log_file ) ? filesize( $log_file ) : 0;

		Error_Handler::warning( 'Test warning message', array( 'key' => 'value' ) );

		$this->assertFileExists( $log_file );
		$this->assertGreaterThan( $initial_size, filesize( $log_file ) );
	}

	/**
	 * Test error logging method
	 */
	public function test_error_logging(): void {
		$log_file     = WP_CONTENT_DIR . '/campaignbridge.log';
		$initial_size = file_exists( $log_file ) ? filesize( $log_file ) : 0;

		Error_Handler::error( 'Test error message', array( 'key' => 'value' ) );

		$this->assertFileExists( $log_file );
		$this->assertGreaterThan( $initial_size, filesize( $log_file ) );
	}

	/**
	 * Test log level filtering - debug messages filtered out at INFO level
	 */
	public function test_log_level_filtering(): void {
		$log_file     = WP_CONTENT_DIR . '/campaignbridge.log';
		$initial_size = file_exists( $log_file ) ? filesize( $log_file ) : 0;

		$this->set_log_level( 1 ); // INFO level

		// Debug messages should be filtered out
		Error_Handler::debug( 'Debug message should be filtered' );
		$size_after_debug = filesize( $log_file );

		// Should be same size (debug message filtered)
		$this->assertEquals( $initial_size, $size_after_debug );

		// Info message should be logged
		Error_Handler::info( 'Info message should appear' );
		$final_size = filesize( $log_file );

		// Should be larger now
		$this->assertGreaterThan( $size_after_debug, $final_size );
	}

	/**
	 * Test log level name conversion
	 */
	public function test_get_level_name(): void {
		$this->assertEquals( 'DEBUG', $this->invoke_private_method( 'get_level_name', 0 ) );
		$this->assertEquals( 'INFO', $this->invoke_private_method( 'get_level_name', 1 ) );
		$this->assertEquals( 'WARNING', $this->invoke_private_method( 'get_level_name', 2 ) );
		$this->assertEquals( 'ERROR', $this->invoke_private_method( 'get_level_name', 3 ) );
		$this->assertEquals( 'UNKNOWN', $this->invoke_private_method( 'get_level_name', 99 ) );
	}

	/**
	 * Test should_log method
	 */
	public function test_should_log(): void {
		$this->set_log_level( 1 ); // INFO level

		$this->assertFalse( $this->invoke_private_method( 'should_log', 0 ) ); // DEBUG should not log
		$this->assertTrue( $this->invoke_private_method( 'should_log', 1 ) );  // INFO should log
		$this->assertTrue( $this->invoke_private_method( 'should_log', 2 ) );  // WARNING should log
		$this->assertTrue( $this->invoke_private_method( 'should_log', 3 ) );  // ERROR should log
	}

	/**
	 * Test get_log_level method for non-admin users
	 */
	public function test_get_log_level_non_admin(): void {
		// Create non-admin user
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$log_level = $this->invoke_private_method( 'get_log_level' );
		$this->assertEquals( 3, $log_level ); // Should return ERROR level for non-admins
	}

	/**
	 * Test get_log_level method for admin users
	 */
	public function test_get_log_level_admin(): void {
		// Create admin user
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Set log level option
		\CampaignBridge\Core\Storage::update_option( 'campaignbridge_log_level', 'DEBUG' );

		$log_level = $this->invoke_private_method( 'get_log_level' );
		$this->assertEquals( 0, $log_level ); // Should return DEBUG level for admins
	}

	/**
	 * Test WordPress error handler registration
	 */
	public function test_register_error_handler(): void {
		$this->error_handler->register_error_handler();

		$this->assertEquals( 10, has_filter( 'wp_php_error', array( $this->error_handler, 'handle_wp_php_error' ) ) );
		$this->assertEquals( 10, has_filter( 'wp_die_handler', array( $this->error_handler, 'handle_wp_die' ) ) );
	}

	/**
	 * Test WordPress PHP error handling
	 */
	public function test_handle_wp_php_error(): void {
		$log_file     = WP_CONTENT_DIR . '/campaignbridge.log';
		$initial_size = file_exists( $log_file ) ? filesize( $log_file ) : 0;

		$error = array(
			'message' => 'Test PHP error',
			'file'    => '/path/to/file.php',
			'line'    => 123,
		);

		$this->error_handler->handle_wp_php_error( $error, 'Test error message' );

		$this->assertFileExists( $log_file );
		$this->assertGreaterThan( $initial_size, filesize( $log_file ) );
	}

	/**
	 * Test WordPress die handling
	 */
	public function test_handle_wp_die(): void {
		$log_file     = WP_CONTENT_DIR . '/campaignbridge.log';
		$initial_size = file_exists( $log_file ) ? filesize( $log_file ) : 0;

		$this->error_handler->handle_wp_die( 'Test die message' );

		$this->assertFileExists( $log_file );
		$this->assertGreaterThan( $initial_size, filesize( $log_file ) );
	}

	/**
	 * Test log file path
	 */
	public function test_log_file_path(): void {
		$log_file_property = $this->reflection->getProperty( 'log_file' );
		$log_file_property->setAccessible( true );
		$log_file = $log_file_property->getValue( $this->error_handler );

		$this->assertEquals( WP_CONTENT_DIR . '/campaignbridge.log', $log_file );
	}

	/**
	 * Clean up after each test
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Restore original log level
		$this->set_log_level( $this->original_log_level );

		// Clean up test admin user
		if ( isset( $this->test_admin_user_id ) ) {
			wp_delete_user( $this->test_admin_user_id );
		}
	}

	/**
	 * Helper method to invoke private methods
	 */
	private function invoke_private_method( string $method_name, ...$args ) {
		$method = $this->reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( $this->error_handler, $args );
	}

	/**
	 * Helper method to set log level for testing
	 */
	private function set_log_level( int $level ): void {
		$log_level_property = $this->reflection->getProperty( 'log_level' );
		$log_level_property->setAccessible( true );
		$log_level_property->setValue( $this->error_handler, $level );
	}
}
