<?php
/**
 * Example test file showing how to write real tests.
 *
 * @package CampaignBridge\Tests
 */

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Tests\Helpers\Test_Factory;

/**
 * Example test class demonstrating WordPress test patterns.
 *
 * Replace this with your actual plugin tests.
 */
class Example_Test extends Test_Case {

	/**
	 * Test WordPress environment is working.
	 */
	public function test_wordpress_functions_available(): void {
		$this->assertTrue( function_exists( 'wp_insert_post' ) );
		$this->assertTrue( function_exists( 'get_option' ) );
		$this->assertTrue( defined( 'ABSPATH' ) );
	}

	/**
	 * Example test for creating posts.
	 */
	public function test_create_post(): void {
		$post_id = $this->create_test_post([
			'post_title'   => 'Test Post',
			'post_content' => 'Test content',
		]);

		$this->assertGreaterThan( 0, $post_id );

		$post = get_post( $post_id );
		$this->assertEquals( 'Test Post', $post->post_title );
		$this->assertEquals( 'publish', $post->post_status );
	}

	/**
	 * Example test for options.
	 */
	public function test_option_handling(): void {
		$option_name  = 'test_option';
		$option_value = 'test_value';

		update_option( $option_name, $option_value );
		$this->assert_option_equals( $option_name, $option_value );

		delete_option( $option_name );
		$this->assertFalse( get_option( $option_name ) );
	}

	/**
	 * Example test using the test factory.
	 */
	public function test_factory_usage(): void {
		// Example of using Test_Factory (replace with your actual factory methods)
		$test_data = [
			'setting_name'  => 'example_setting',
			'setting_value' => 'example_value',
		];

		$this->assertIsArray( $test_data );
		$this->assertArrayHasKey( 'setting_name', $test_data );
	}

	/**
	 * Example test with mocked HTTP request.
	 */
	public function test_http_request_mocking(): void {
		// Mock an HTTP response
		$this->mock_remote_request([
			'success' => true,
			'data'    => 'test_response',
		], 200 );

		// Your plugin code would make the HTTP request here
		$response = wp_remote_get( 'https://example.com/api' );
		$body     = wp_remote_retrieve_body( $response );
		$data     = json_decode( $body, true );

		$this->assertTrue( $data['success'] );
		$this->assertEquals( 'test_response', $data['data'] );
	}
}
