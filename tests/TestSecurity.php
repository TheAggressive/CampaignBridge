<?php
/**
 * Security testing for CampaignBridge plugin.
 *
 * Tests that all sensitive operations have proper permission checks
 * and that built-in security mechanisms work correctly.
 */

class TestSecurity extends WP_UnitTestCase {

	/**
	 * Test that Api_Key_Encryption::decrypt() requires admin permissions
	 */
	public function test_api_key_decryption_requires_admin() {
		// Create a non-admin user
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$encrypted_key = 'encrypted_data_here';

		// Should throw exception for non-admin users
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Unauthorized attempt to decrypt sensitive data. Admin access required.' );

		\CampaignBridge\Core\Api_Key_Encryption::decrypt( $encrypted_key );
	}

	/**
	 * Test that admin users can decrypt API keys
	 */
	public function test_admin_can_decrypt_api_keys() {
		// Create an admin user
		$admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$test_key = 'test_api_key_123';
		$encrypted = \CampaignBridge\Core\Api_Key_Encryption::encrypt( $test_key );

		$decrypted = \CampaignBridge\Core\Api_Key_Encryption::decrypt( $encrypted );

		$this->assertEquals( $test_key, $decrypted );
	}

	/**
	 * Test that Error_Handler::get_log_level() restricts non-admins
	 */
	public function test_log_level_restricted_to_admins() {
		// Create a non-admin user
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$handler = new \CampaignBridge\Core\Error_Handler();
		$reflection = new ReflectionClass( $handler );
		$method = $reflection->getMethod( 'get_log_level' );
		$method->setAccessible( true );

		$level = $method->invoke( $handler );

		// Should return ERROR level for non-admins (most restrictive)
		$this->assertEquals( \CampaignBridge\Core\Error_Handler::LOG_LEVEL_ERROR, $level );
	}

	/**
	 * Test that admin users can access log level settings
	 */
	public function test_admin_can_access_log_level() {
		// Create an admin user
		$admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// Set a custom log level
		update_option( 'campaignbridge_log_level', 'DEBUG' );

		$handler = new \CampaignBridge\Core\Error_Handler();
		$reflection = new ReflectionClass( $handler );
		$method = $reflection->getMethod( 'get_log_level' );
		$method->setAccessible( true );

		$level = $method->invoke( $handler );

		// Should return DEBUG level for admins
		$this->assertEquals( \CampaignBridge\Core\Error_Handler::LOG_LEVEL_DEBUG, $level );
	}

	/**
	 * Test that Performance_Optimizer::batch_update_post_meta() requires admin permissions
	 */
	public function test_database_operations_require_admin() {
		// Create a non-admin user
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$optimizer = new \CampaignBridge\Core\Performance_Optimizer();
		$test_data = [
			1 => [ 'meta_key' => 'test_key', 'meta_value' => 'test_value' ]
		];

		$result = $optimizer->batch_update_post_meta( $test_data );

		// Should return false for non-admins
		$this->assertFalse( $result );
	}

	/**
	 * Test that admin users can perform database operations
	 */
	public function test_admin_can_perform_database_operations() {
		// Create an admin user
		$admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$optimizer = new \CampaignBridge\Core\Performance_Optimizer();

		// Create a test post first
		$post_id = $this->factory->post->create();
		$test_data = [
			$post_id => [ 'meta_key' => 'test_key', 'meta_value' => 'test_value' ]
		];

		$result = $optimizer->batch_update_post_meta( $test_data );

		// Should return true for admins
		$this->assertTrue( $result );

		// Verify the meta was actually set
		$value = get_post_meta( $post_id, 'test_key', true );
		$this->assertEquals( 'test_value', $value );
	}

	/**
	 * Test input sanitization in settings
	 */
	public function test_input_sanitization() {
		$malicious_input = [
			'api_key' => '<script>alert("xss")</script>',
			'email' => 'test@example.com<script>alert("xss")</script>',
			'url' => 'javascript:alert("xss")',
		];

		// Test that malicious input is properly sanitized
		$sanitized = sanitize_text_field( $malicious_input['api_key'] );
		$this->assertEquals( '', $sanitized ); // Should be empty after sanitization

		$sanitized_email = sanitize_email( $malicious_input['email'] );
		$this->assertEquals( 'test@example.com', $sanitized_email );

		$sanitized_url = esc_url_raw( $malicious_input['url'] );
		$this->assertEquals( '', $sanitized_url ); // Should be empty for malicious URLs
	}

	/**
	 * Test that settings access is properly protected
	 */
	public function test_settings_access_protection() {
		// Create a non-admin user
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		// Try to access admin settings (should fail)
		$this->expectException( \WPDieException::class );

		// This should trigger the permission check in Admin::get_decrypted_settings()
		\CampaignBridge\Admin\Pages\Admin::get_decrypted_settings();
	}

	/**
	 * Test that admin users can access settings
	 */
	public function test_admin_can_access_settings() {
		// Create an admin user
		$admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// Set up some test settings
		update_option( 'campaignbridge_settings', [
			'provider' => 'html',
			'api_key' => 'test_key_123'
		] );

		$settings = \CampaignBridge\Admin\Pages\Admin::get_decrypted_settings();

		// Should be able to access settings without exception
		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'provider', $settings );
	}

	/**
	 * Test that nonce verification works
	 */
	public function test_nonce_verification() {
		$action = 'test_action';
		$nonce = wp_create_nonce( $action );

		// Test valid nonce
		$this->assertTrue( wp_verify_nonce( $nonce, $action ) );

		// Test invalid nonce
		$this->assertFalse( wp_verify_nonce( 'invalid_nonce', $action ) );
	}

	/**
	 * Test capability management
	 */
	public function test_capability_management() {
		// Test that administrators have the plugin capability
		$admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$admin_user = get_user_by( 'id', $admin_id );

		$this->assertTrue( $admin_user->has_cap( 'campaignbridge_manage' ) );

		// Test that subscribers don't have the plugin capability
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		$subscriber_user = get_user_by( 'id', $subscriber_id );

		$this->assertFalse( $subscriber_user->has_cap( 'campaignbridge_manage' ) );
	}
}
