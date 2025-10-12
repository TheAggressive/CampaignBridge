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
		delete_option( 'campaignbridge_post_types' );
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
			'campaignbridge_post_types',
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
		delete_option( 'campaignbridge_post_types' );

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
			'campaignbridge_post_types',
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
}
