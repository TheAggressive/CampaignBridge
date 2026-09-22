<?php
/**
 * REST API Integration Tests.
 *
 * Tests REST API endpoints with real WordPress environment, including
 * rate limiting, authentication, and data persistence.
 *
 * @package CampaignBridge\Tests\Integration
 */

declare( strict_types = 1 );

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Core\Encryption;
use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Repository\Provider_Connection_Repository;

/**
 * Class REST_API_Test
 */
class REST_API_Test extends Test_Case {

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Set up admin environment for REST API tests.
		set_current_screen( 'admin' );
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Clean up any options set during tests.
		delete_option( 'campaignbridge_included_post_types' );
		( new Provider_Connection_Repository() )->delete( 'mailchimp' );
	}

	/**
	 * Store one encrypted Mailchimp credential for reveal tests.
	 *
	 * @param string $api_key Plaintext test credential.
	 * @return void
	 */
	private function store_mailchimp_api_key( string $api_key ): void {
		$encrypted  = Encryption::encrypt( $api_key );
		$connection = Provider_Connection::create( 'mailchimp', $encrypted );
		$this->assertTrue( ( new Provider_Connection_Repository() )->save( $connection ) );

	}

	/**
	 * Test posts endpoint returns real WordPress posts with proper formatting.
	 */
	public function test_posts_endpoint_returns_wordpress_posts(): void {
		// Arrange: Create a test user with admin capabilities.
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create test posts.
		$post_ids = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$post_ids[] = $this->create_test_post(
				array(
					'post_title'   => "Test Post {$i}",
					'post_content' => "Content for test post {$i}",
					'post_status'  => 'publish',
				)
			);
		}

		// Act: Make REST API request.
		$request  = new \WP_REST_Request( 'GET', '/campaignbridge/v1/posts' );
		$response = rest_do_request( $request );

		// Assert: Verify response structure and data.
		$this->assertEquals( 200, $response->get_status(), 'Should return 200 status' );

		$data = $response->get_data();
		$this->assertIsArray( $data, 'Should return array data' );
		$this->assertArrayHasKey( 'items', $data, 'Should have items key' );
		$this->assertIsArray( $data['items'], 'Items should be array' );
		$this->assertNotEmpty( $data['items'], 'Should have posts in response' );

		// Verify post data structure (simplified format).
		$first_post = $data['items'][0];
		$this->assertArrayHasKey( 'id', $first_post, 'Post should have ID' );
		$this->assertArrayHasKey( 'label', $first_post, 'Post should have label' );
		$this->assertIsInt( $first_post['id'], 'Post ID should be integer' );
		$this->assertIsString( $first_post['label'], 'Post label should be string' );
		$this->assertStringContainsString( 'Test Post', $first_post['label'], 'Should contain test post title' );
	}

	/**
	 * Test posts endpoint with specific post type parameter.
	 */
	public function test_posts_endpoint_with_post_type_parameter(): void {
		// Arrange: Create admin user and test posts.
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create posts of different types.
		$page_id = $this->create_test_post(
			array(
				'post_title'   => 'Test Page',
				'post_content' => 'Page content',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		$post_id = $this->create_test_post(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Post content',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);

		// Act: Request pages specifically.
		$request = new \WP_REST_Request( 'GET', '/campaignbridge/v1/posts' );
		$request->set_param( 'post_type', 'page' );
		$response = rest_do_request( $request );

		// Assert: Should only return pages.
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data['items'] );

		// All returned posts should be pages.
		foreach ( $data['items'] as $item ) {
			$this->assertEquals( 'page', get_post_type( $item['id'] ), 'Should only return pages' );
		}
	}

	/**
	 * Test post-types endpoint returns configured post types.
	 */
	public function test_post_types_endpoint_returns_configured_types(): void {
		// Arrange: Create admin user and configure post types.
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Configure specific post types in settings.
		update_option(
			'campaignbridge_included_post_types',
			array(
				'included_post_types' => array( 'post' ),
			)
		);

		// Act: Request post types.
		$request  = new \WP_REST_Request( 'GET', '/campaignbridge/v1/post-types' );
		$response = rest_do_request( $request );

		// Assert: Should return configured post types.
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'items', $data );

		$items = $data['items'];
		$this->assertIsArray( $items );

		// Extract post type IDs from items.
		$post_type_ids = array_column( $items, 'id' );

		// Should contain our configured type.
		$this->assertContains( 'post', $post_type_ids, 'Should include post type' );
		$post_type_item = current(
			array_filter(
				$items,
				static fn ( array $item ): bool => 'post' === $item['id']
			)
		);
		$this->assertIsArray( $post_type_item );
		$this->assertArrayHasKey( 'archive_url', $post_type_item );
		$this->assertSame( esc_url_raw( (string) get_post_type_archive_link( 'post' ) ), $post_type_item['archive_url'] );

		// Should not include page (excluded by logic).
		$this->assertNotContains( 'page', $post_type_ids, 'Should exclude page type' );
	}

	/**
	 * Test post-types endpoint returns all public types when none configured.
	 */
	public function test_post_types_endpoint_returns_all_public_when_none_configured(): void {
		// Arrange: Create admin user without any post type configuration.
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Ensure no post types setting exists.
		delete_option( 'campaignbridge_included_post_types' );

		// Act: Request post types.
		$request  = new \WP_REST_Request( 'GET', '/campaignbridge/v1/post-types' );
		$response = rest_do_request( $request );

		// Assert: Should return all public post types.
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data['items'] );

		// Extract post type IDs from items.
		$post_type_ids = array_column( $data['items'], 'id' );

		// Should include common public post types.
		$this->assertContains( 'post', $post_type_ids, 'Should include post type by default' );

		// Should exclude page and attachment (as per endpoint logic).
		$this->assertNotContains( 'page', $post_type_ids, 'Should exclude page type by default' );
		$this->assertNotContains( 'attachment', $post_type_ids, 'Should exclude attachment type by default' );
	}

	/**
	 * Test REST API permission checking requires admin capabilities.
	 */
	public function test_rest_api_requires_admin_permissions(): void {
		// Arrange: Create subscriber user (no admin permissions).
		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		// Act: Try to access posts endpoint.
		$request  = new \WP_REST_Request( 'GET', '/campaignbridge/v1/posts' );
		$response = rest_do_request( $request );

		// Assert: Should be forbidden (403).
		$this->assertEquals( 403, $response->get_status(), 'Should require admin permissions' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertEquals( 'rest_forbidden', $data['code'], 'Should return forbidden error' );
	}

	/**
	 * Test REST API rejects unauthenticated requests.
	 */
	public function test_rest_api_rejects_unauthenticated_requests(): void {
		// Arrange: No user logged in.
		wp_set_current_user( 0 );

		// Act: Try to access posts endpoint.
		$request  = new \WP_REST_Request( 'GET', '/campaignbridge/v1/posts' );
		$response = rest_do_request( $request );

		// Assert: Should reject unauthenticated access (401 or 403).
		$this->assertContains(
			$response->get_status(),
			array( 401, 403 ),
			'Should require authentication or authorization'
		);

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );

		// WordPress may return either 'rest_not_logged_in' or 'rest_forbidden' depending on implementation
		$this->assertContains(
			$data['code'],
			array( 'rest_not_logged_in', 'rest_forbidden' ),
			'Should return appropriate authentication/authorization error'
		);
	}

	/**
	 * Test rate limiting works correctly in real environment.
	 */
	public function test_rate_limiting_works_with_real_wordpress_cache(): void {
		// Arrange: Create admin user and make initial requests.
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Make requests up to the limit (30 requests per minute).
		for ( $i = 0; $i < 30; $i++ ) {
			$request  = new \WP_REST_Request( 'GET', '/campaignbridge/v1/posts' );
			$response = rest_do_request( $request );

			// All requests within limit should succeed.
			$this->assertEquals( 200, $response->get_status(), "Request {$i} should succeed" );
		}

		// Act: Make one more request that should be rate limited.
		$request  = new \WP_REST_Request( 'GET', '/campaignbridge/v1/posts' );
		$response = rest_do_request( $request );

		// Assert: Should be rate limited (429).
		$this->assertEquals( 429, $response->get_status(), 'Should be rate limited after 30 requests' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertEquals( 'rate_limit_exceeded', $data['code'], 'Should return rate limit error' );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertStringContainsString( 'Rate limit exceeded', $data['message'], 'Should contain rate limit message' );
	}

	/**
	 * Test posts endpoint validates post type parameter.
	 */
	public function test_posts_endpoint_validates_post_type_parameter(): void {
		// Arrange: Create admin user.
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Act: Request with invalid post type.
		$request = new \WP_REST_Request( 'GET', '/campaignbridge/v1/posts' );
		$request->set_param( 'post_type', 'invalid-post-type<script>alert("xss")</script>' );
		$response = rest_do_request( $request );

		// Assert: Should reject invalid post type.
		$this->assertEquals( 400, $response->get_status(), 'Should reject invalid post type' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertStringContainsString( 'invalid', $data['code'], 'Should indicate validation error' );
	}

	/**
	 * Test posts endpoint rejects disallowed post types.
	 */
	public function test_posts_endpoint_rejects_disallowed_post_types(): void {
		// Arrange: Create admin user and configure allowed post types.
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Only allow 'post' type.
		update_option(
			'campaignbridge_included_post_types',
			array( 'included_post_types' => array( 'post' ) )
		);

		// Act: Request disallowed post type.
		$request = new \WP_REST_Request( 'GET', '/campaignbridge/v1/posts' );
		$request->set_param( 'post_type', 'page' );
		$response = rest_do_request( $request );

		// Assert: Should reject disallowed post type with 400 error.
		$this->assertEquals( 400, $response->get_status(), 'Should reject disallowed post type' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertEquals( 'post_type_not_allowed', $data['code'], 'Should indicate post type not allowed' );
	}

	/**
	 * Test posts endpoint returns properly formatted response structure.
	 */
	public function test_posts_endpoint_returns_proper_response_structure(): void {
		// Arrange: Create admin user and test post.
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$post_id = $this->create_test_post(
			array(
				'post_title'   => 'Structured Test Post',
				'post_content' => 'This is test content with <strong>HTML</strong>',
				'post_status'  => 'publish',
				'post_excerpt' => 'Test excerpt',
			)
		);

		// Act: Request posts.
		$request  = new \WP_REST_Request( 'GET', '/campaignbridge/v1/posts' );
		$response = rest_do_request( $request );

		// Assert: Verify complete response structure.
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data['items'] );
		$this->assertNotEmpty( $data['items'] );

		$post_data = $data['items'][0];

		// Verify the simplified response structure (id and label only).
		$this->assertArrayHasKey( 'id', $post_data, 'Post should have id field' );
		$this->assertArrayHasKey( 'label', $post_data, 'Post should have label field' );

		// Verify data types.
		$this->assertIsInt( $post_data['id'], 'ID should be integer' );
		$this->assertIsString( $post_data['label'], 'Label should be string' );

		// Verify actual data.
		$this->assertEquals( $post_id, $post_data['id'] );
		$this->assertEquals( 'Structured Test Post', $post_data['label'] );
	}

	/**
	 * Test rate limiting uses different identifiers for different users.
	 */
	public function test_rate_limiting_uses_different_identifiers_per_user(): void {
		// Arrange: Create two different admin users.
		$user1_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		$user2_id = $this->create_test_user( array( 'role' => 'administrator' ) );

		// Act & Assert: User 1 makes requests.
		wp_set_current_user( $user1_id );
		for ( $i = 0; $i < 5; $i++ ) {
			$request  = new \WP_REST_Request( 'GET', '/campaignbridge/v1/posts' );
			$response = rest_do_request( $request );
			$this->assertEquals( 200, $response->get_status(), "User 1 request {$i} should succeed" );
		}

		// User 2 should still be able to make requests (separate rate limit).
		wp_set_current_user( $user2_id );
		$request  = new \WP_REST_Request( 'GET', '/campaignbridge/v1/posts' );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status(), 'User 2 should not be rate limited by User 1' );
	}

	/**
	 * CRITICAL SECURITY TEST: Ensure no API keys leak through decrypt-field endpoint.
	 */
	public function test_decrypt_field_endpoint_does_not_leak_api_keys(): void {
		// Arrange: Create admin user and test API key.
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$test_api_key = 'sk-test-12345678901234567890123456789012';
		$this->store_mailchimp_api_key( $test_api_key );

		// Act: Make decrypt request with valid nonce.
		$request = new \WP_REST_Request( 'POST', '/campaignbridge/v1/decrypt-field' );
		$request->set_param( 'field_id', 'mailchimp_api_key' );
		$request->set_param( '_wpnonce', wp_create_nonce( 'campaignbridge_encrypted_fields' ) );
		$response = rest_do_request( $request );

		// Assert: Should return decrypted data in secure format.
		$this->assertEquals( 200, $response->get_status(), 'Should successfully decrypt' );

		$data = $response->get_data();
		$this->assertIsArray( $data, 'Should return array response' );
		$this->assertArrayHasKey( 'success', $data, 'Should have success flag' );
		$this->assertArrayHasKey( 'data', $data, 'Should have data wrapper' );
		$this->assertArrayHasKey( 'decrypted', $data['data'], 'Should have decrypted value' );

		// CRITICAL: Verify the decrypted value is exactly what we encrypted.
		$this->assertEquals( $test_api_key, $data['data']['decrypted'], 'Should return exact decrypted value' );

		// Verify response structure doesn't expose sensitive metadata.
		$this->assertArrayNotHasKey( 'encryption_key', $data, 'Should not expose encryption key' );
		$this->assertArrayNotHasKey( 'algorithm', $data, 'Should not expose algorithm details' );
		$this->assertArrayNotHasKey( 'timestamp', $data, 'Should not expose timing information' );
	}

	/**
	 * CRITICAL SECURITY TEST: Ensure non-admin users cannot decrypt sensitive data.
	 */
	public function test_non_admin_users_cannot_decrypt_sensitive_data(): void {
		// Arrange: Create subscriber user and test API key.
		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$test_api_key = 'sk-test-12345678901234567890123456789012';

		// Act: Try to decrypt as non-admin user.
		$request = new \WP_REST_Request( 'POST', '/campaignbridge/v1/decrypt-field' );
		$request->set_param( 'field_id', 'mailchimp_api_key' );
		$request->set_param( '_wpnonce', wp_create_nonce( 'campaignbridge_encrypted_fields' ) );
		$response = rest_do_request( $request );

		// Assert: Should be forbidden.
		$this->assertEquals( 403, $response->get_status(), 'Non-admin should not be able to decrypt' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data, 'Should have error code' );
		$this->assertEquals( 'rest_forbidden', $data['code'], 'Should indicate permission denied' );

		// CRITICAL: Ensure no decrypted data is returned.
		$this->assertArrayNotHasKey( 'decrypted', $data, 'Should not return decrypted data' );
		$this->assertStringNotContainsString( $test_api_key, json_encode( $data ), 'Should not leak API key in any form' );
	}

	/**
	 * CRITICAL SECURITY TEST: Reveal must ignore caller-supplied ciphertext.
	 */
	public function test_decrypt_field_ignores_caller_supplied_ciphertext(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$stored_api_key = 'stored-mailchimp-key-us1';
		$this->store_mailchimp_api_key( $stored_api_key );
		$caller_ciphertext = Encryption::encrypt( 'different-secret-that-must-not-be-revealed' );

		$request = new \WP_REST_Request( 'POST', '/campaignbridge/v1/decrypt-field' );
		$request->set_param( 'field_id', 'mailchimp_api_key' );
		$request->set_param( 'encrypted_value', $caller_ciphertext );
		$request->set_param( '_wpnonce', wp_create_nonce( 'campaignbridge_encrypted_fields' ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $stored_api_key, $response->get_data()['data']['decrypted'] );
		$this->assertStringNotContainsString( 'different-secret-that-must-not-be-revealed', wp_json_encode( $response->get_data() ) );
	}

	public function test_encrypt_field_endpoint_validates_input_securely(): void {
		// Arrange: Create admin user.
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$test_data = array(
			'field_id'  => 'mailchimp_api_key',
			'new_value' => 'valid-test-value-123',
		);

		// Act: Make encrypt request.
		$request = new \WP_REST_Request( 'POST', '/campaignbridge/v1/encrypt-field' );
		$request->set_param( 'field_id', $test_data['field_id'] );
		$request->set_param( 'new_value', $test_data['new_value'] );
		$request->set_param( '_wpnonce', wp_create_nonce( 'campaignbridge_encrypted_fields' ) );
		$response = rest_do_request( $request );

		// Assert: Should encrypt successfully.
		$this->assertEquals( 200, $response->get_status(), 'Should encrypt successfully' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'success', $data, 'Should have success flag' );
		$this->assertArrayHasKey( 'data', $data, 'Should have data wrapper' );

		// CRITICAL: Should return encrypted data, not the original value.
		$this->assertArrayHasKey( 'encrypted', $data['data'], 'Should return encrypted value' );
		$this->assertArrayHasKey( 'masked', $data['data'], 'Should return masked value' );

		// CRITICAL: Encrypted value should be different from original.
		$this->assertNotEquals( $test_data['new_value'], $data['data']['encrypted'], 'Encrypted value should differ from original' );

		// CRITICAL: Original value should not appear in response.
		$this->assertStringNotContainsString( $test_data['new_value'], json_encode( $data ), 'Should not leak original value' );
	}

	/**
	 * CRITICAL SECURITY TEST: Ensure encrypt-field rejects malicious input.
	 */
	public function test_encrypt_field_rejects_malicious_input(): void {
		// Arrange: Create admin user.
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$malicious_inputs = array(
			'<script>alert("xss")</script>'  => 'script_tag',
			'javascript:alert(1)'            => 'javascript_url',
			'onclick=alert(1)'               => 'event_handler',
			'"><img src=x onerror=alert(1)>' => 'html_injection',
		);

		foreach ( $malicious_inputs as $malicious_value => $test_name ) {
			// Act: Try to encrypt malicious value.
			$request = new \WP_REST_Request( 'POST', '/campaignbridge/v1/encrypt-field' );
			$request->set_param( 'field_id', 'mailchimp_api_key' );
			$request->set_param( 'new_value', $malicious_value );
			$request->set_param( '_wpnonce', wp_create_nonce( 'campaignbridge_encrypted_fields' ) );
			$response = rest_do_request( $request );

			// Assert: Should reject malicious input.
			$this->assertEquals( 400, $response->get_status(), "Should reject malicious input: {$test_name}" );

			$data = $response->get_data();
			$this->assertArrayHasKey( 'code', $data, 'Should have error code' );

			// CRITICAL: Should not encrypt malicious content.
			$this->assertArrayNotHasKey( 'encrypted', $data, 'Should not return encrypted malicious content' );
			$this->assertArrayNotHasKey( 'masked', $data, 'Should not return masked malicious content' );

			// CRITICAL: Malicious content should not appear in response.
			$this->assertStringNotContainsString( $malicious_value, json_encode( $data ), 'Should not echo malicious content' );
		}
	}

	/**
	 * CRITICAL SECURITY TEST: Ensure rate limiting protects encrypt/decrypt endpoints.
	 */
	public function test_encrypted_field_endpoints_are_rate_limited(): void {
		// Arrange: Create admin user.
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$test_value = 'rate-limit-test-value';
		$this->store_mailchimp_api_key( $test_value );

		// Act: Make multiple decrypt requests to trigger rate limiting.
		for ( $i = 0; $i < 15; $i++ ) { // More than the 10 request limit
			$request = new \WP_REST_Request( 'POST', '/campaignbridge/v1/decrypt-field' );
			$request->set_param( 'field_id', 'mailchimp_api_key' );
			$request->set_param( '_wpnonce', wp_create_nonce( 'campaignbridge_encrypted_fields' ) );
			$response = rest_do_request( $request );

			if ( $i < 10 ) {
				// First 10 requests should succeed.
				$this->assertEquals( 200, $response->get_status(), "Request {$i} should succeed" );
			} else {
				// Subsequent requests should be rate limited.
				$this->assertEquals( 429, $response->get_status(), "Request {$i} should be rate limited" );
			}
		}
	}

	/**
	 * CRITICAL SECURITY TEST: Ensure unauthenticated requests are rejected.
	 */
	public function test_encrypted_field_endpoints_require_authentication(): void {
		// Arrange: No user logged in.
		wp_set_current_user( 0 );

		$test_value = 'auth-test-value';

		// Test decrypt endpoint.
		$request = new \WP_REST_Request( 'POST', '/campaignbridge/v1/decrypt-field' );
		$request->set_param( 'field_id', 'mailchimp_api_key' );
		$request->set_param( '_wpnonce', wp_create_nonce( 'campaignbridge_encrypted_fields' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status(), 'Decrypt should require authentication' );

		// Test encrypt endpoint.
		$request = new \WP_REST_Request( 'POST', '/campaignbridge/v1/encrypt-field' );
		$request->set_param( 'field_id', 'mailchimp_api_key' );
		$request->set_param( 'new_value', $test_value );
		$request->set_param( '_wpnonce', wp_create_nonce( 'campaignbridge_encrypted_fields' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status(), 'Encrypt should require authentication' );
	}

	/**
	 * CRITICAL SECURITY TEST: Ensure CSRF protection works.
	 */
	public function test_encrypted_field_endpoints_require_valid_nonces(): void {
		// Arrange: Create admin user but use invalid nonce.
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$test_value = 'csrf-test-value';

		// Test decrypt with invalid nonce.
		$request = new \WP_REST_Request( 'POST', '/campaignbridge/v1/decrypt-field' );
		$request->set_param( 'field_id', 'mailchimp_api_key' );
		$request->set_param( '_wpnonce', 'invalid_nonce' );
		$response = rest_do_request( $request );

		$this->assertEquals( 403, $response->get_status(), 'Decrypt should reject invalid nonce' );

		// Test encrypt with invalid nonce.
		$request = new \WP_REST_Request( 'POST', '/campaignbridge/v1/encrypt-field' );
		$request->set_param( 'field_id', 'mailchimp_api_key' );
		$request->set_param( 'new_value', $test_value );
		$request->set_param( '_wpnonce', 'invalid_nonce' );
		$response = rest_do_request( $request );

		$this->assertEquals( 403, $response->get_status(), 'Encrypt should reject invalid nonce' );
	}

	/**
	 * CRITICAL SECURITY TEST: Only registered server-owned field IDs are accepted.
	 */
	public function test_encrypted_field_endpoints_reject_unknown_field_ids(): void {
		$user_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$decrypt = new \WP_REST_Request( 'POST', '/campaignbridge/v1/decrypt-field' );
		$decrypt->set_param( 'field_id', 'unknown_secret' );
		$decrypt->set_param( '_wpnonce', wp_create_nonce( 'campaignbridge_encrypted_fields' ) );
		$this->assertSame( 400, rest_do_request( $decrypt )->get_status() );

		$encrypt = new \WP_REST_Request( 'POST', '/campaignbridge/v1/encrypt-field' );
		$encrypt->set_param( 'field_id', 'unknown_secret' );
		$encrypt->set_param( 'new_value', 'value' );
		$encrypt->set_param( '_wpnonce', wp_create_nonce( 'campaignbridge_encrypted_fields' ) );
		$this->assertSame( 400, rest_do_request( $encrypt )->get_status() );
	}
}
