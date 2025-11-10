<?php
/**
 * Base test case for CampaignBridge tests.
 *
 * @package CampaignBridge\Tests
 */

namespace CampaignBridge\Tests\Helpers;

use WP_UnitTestCase;

/**
 * Enhanced base test case with common utilities.
 */
abstract class Test_Case extends WP_UnitTestCase {

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->setup_plugin_environment();

		// Start output buffering to suppress form HTML output during tests
		ob_start();
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		$this->cleanup_test_data();
		parent::tearDown();

		// Clean up output buffer to suppress form HTML output during tests
		if ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
	}

	/**
	 * Set up plugin-specific environment.
	 */
	protected function setup_plugin_environment(): void {
		// Initialize plugin if needed.
		if ( ! did_action( 'campaignbridge_init' ) ) {
			do_action( 'campaignbridge_init' );
		}
	}

	/**
	 * Clean up test data.
	 */
	protected function cleanup_test_data(): void {
		// Clean up any test-specific data.
		global $wpdb;

		// Remove test posts.
		$wpdb->query(
			"DELETE FROM {$wpdb->posts}
			 WHERE post_title LIKE 'Test%'
			 OR post_content LIKE '%test-content%'"
		);

		// Remove test options.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE 'test_%'
			 OR option_name LIKE '%_test'"
		);

		// Clear caches.
		wp_cache_flush();
	}

	/**
	 * Create a test post with default values.
	 *
	 * @param array $args Post arguments to override defaults.
	 * @return int Post ID.
	 */
	protected function create_test_post( array $args = array() ): int {
		$defaults = array(
			'post_title'   => 'Test Post ' . uniqid(),
			'post_content' => 'Test content for testing purposes.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		);

		$args = wp_parse_args( $args, $defaults );

		return $this->factory->post->create( $args );
	}

	/**
	 * Create a test user with default values.
	 *
	 * @param array $args User arguments to override defaults.
	 * @return int User ID.
	 */
	protected function create_test_user( array $args = array() ): int {
		$defaults = array(
			'user_login' => 'testuser' . uniqid(),
			'user_email' => 'test' . uniqid() . '@example.com',
			'role'       => 'subscriber',
		);

		$args = wp_parse_args( $args, $defaults );

		return $this->factory->user->create( $args );
	}

	/**
	 * Assert that a hook has been registered.
	 *
	 * @param string   $hook_name The hook name.
	 * @param callable $callback The callback function.
	 * @param int      $priority Expected priority.
	 */
	protected function assert_hook_registered( string $hook_name, callable $callback, int $priority = 10 ): void {
		$this->assertEquals(
			$priority,
			has_filter( $hook_name, $callback ),
			"Hook '{$hook_name}' should be registered with priority {$priority}"
		);
	}

	/**
	 * Assert that an option exists and has expected value.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $expected_value Expected value.
	 */
	protected function assert_option_equals( string $option_name, $expected_value ): void {
		$actual_value = get_option( $option_name );
		$this->assertEquals(
			$expected_value,
			$actual_value,
			"Option '{$option_name}' should equal expected value"
		);
	}

	/**
	 * Assert that a post meta value equals expected value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $expected_value Expected value.
	 */
	protected function assert_post_meta_equals( int $post_id, string $meta_key, $expected_value ): void {
		$actual_value = get_post_meta( $post_id, $meta_key, true );
		$this->assertEquals(
			$expected_value,
			$actual_value,
			"Post meta '{$meta_key}' for post {$post_id} should equal expected value"
		);
	}

	/**
	 * Mock WordPress remote request.
	 *
	 * @param array $response Response data.
	 * @param int   $status HTTP status code.
	 */
	protected function mock_remote_request( array $response, int $status = 200 ): void {
		add_filter(
			'pre_http_request',
			function () use ( $response, $status ) {
				return array(
					'body'     => wp_json_encode( $response ),
					'response' => array( 'code' => $status ),
					'headers'  => array( 'content-type' => 'application/json' ),
				);
			}
		);
	}

	/**
	 * Get reflection method for testing private/protected methods.
	 *
	 * @param object|string $class_or_object Class name or object instance.
	 * @param string        $method_name Method name.
	 * @return \ReflectionMethod
	 */
	protected function get_reflection_method( $class_or_object, string $method_name ): \ReflectionMethod {
		$reflection = new \ReflectionClass( $class_or_object );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method;
	}

	/**
	 * Get reflection property for testing private/protected properties.
	 *
	 * @param object|string $class_or_object Class name or object instance.
	 * @param string        $property_name Property name.
	 * @return \ReflectionProperty
	 */
	protected function get_reflection_property( $class_or_object, string $property_name ): \ReflectionProperty {
		$reflection = new \ReflectionClass( $class_or_object );
		$property   = $reflection->getProperty( $property_name );
		$property->setAccessible( true );

		return $property;
	}
}
