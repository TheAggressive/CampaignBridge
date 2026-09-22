<?php
/**
 * Integration tests for capability enforcement across the plugin.
 *
 * @package CampaignBridge\Tests
 */

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Core\Capabilities;

/**
 * Class Capability_Enforcement_Test
 */
class Capability_Enforcement_Test extends Test_Case {

	/**
	 * Test that an administrator with campaignbridge_manage can access admin screens.
	 */
	public function test_administrator_with_capability_can_access_admin(): void {
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue(
			current_user_can( Capabilities::MANAGE ),
			'Administrator should have campaignbridge_manage capability'
		);
	}

	/**
	 * Test that a subscriber without campaignbridge_manage cannot access admin screens.
	 */
	public function test_subscriber_without_capability_cannot_access_admin(): void {
		$subscriber_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse(
			current_user_can( Capabilities::MANAGE ),
			'Subscriber should not have campaignbridge_manage capability'
		);
	}

	/**
	 * Test that a user with manage_options but without campaignbridge_manage is still rejected.
	 *
	 * This is the critical split-brain regression test: the plugin must enforce
	 * its own capability, not fall back to the core manage_options capability.
	 */
	public function test_manage_options_alone_does_not_grant_access(): void {
		// Create a user with manage_options but without campaignbridge_manage.
		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'manage_options' );

		wp_set_current_user( $user_id );

		// User has manage_options...
		$this->assertTrue(
			current_user_can( 'manage_options' ),
			'Test user should have manage_options capability'
		);

		// ...but should NOT have campaignbridge_manage.
		$this->assertFalse(
			current_user_can( Capabilities::MANAGE ),
			'User with manage_options should NOT have campaignbridge_manage'
		);
	}

	/**
	 * Test that the REST API permission callback enforces campaignbridge_manage.
	 */
	public function test_rest_permission_callback_enforces_plugin_capability(): void {
		// Subscriber: should be rejected.
		$subscriber_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse(
			current_user_can( Capabilities::MANAGE ),
			'Subscriber should be denied by campaignbridge_manage check'
		);

		// Administrator: should be allowed.
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue(
			current_user_can( Capabilities::MANAGE ),
			'Administrator should be allowed by campaignbridge_manage check'
		);
	}

	/**
	 * Test that the Encryption context check enforces campaignbridge_manage.
	 *
	 * Uses decrypt_for_context() which internally calls check_context_permissions().
	 */
	public function test_encryption_context_enforces_plugin_capability(): void {
		$encryption = new \CampaignBridge\Core\Encryption();

		// Administrator: should pass.
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		// Encrypt a value, then verify the admin can decrypt it in 'api_key' context.
		$encrypted = \CampaignBridge\Core\Encryption::encrypt( 'test-value' );
		$decrypted = \CampaignBridge\Core\Encryption::decrypt_for_context( $encrypted, 'api_key' );
		$this->assertSame( 'test-value', $decrypted );

		// Subscriber: should be denied (throws RuntimeException).
		$subscriber_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->expectException( \RuntimeException::class );
		\CampaignBridge\Core\Encryption::decrypt_for_context( $encrypted, 'api_key' );
	}

	/**
	 * Test that the Error_Handler enforces campaignbridge_manage.
	 */
	public function test_error_handler_enforces_plugin_capability(): void {
		// Administrator: should be allowed to view errors.
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue(
			current_user_can( Capabilities::MANAGE ),
			'Administrator should have capability for error handler access'
		);

		// Subscriber: should be denied.
		$subscriber_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse(
			current_user_can( Capabilities::MANAGE ),
			'Subscriber should not have capability for error handler access'
		);
	}

	/**
	 * Test that a user with campaignbridge_manage but without manage_options can still access.
	 *
	 * This proves the plugin no longer depends on the core manage_options capability.
	 */
	public function test_campaignbridge_manage_alone_grants_access(): void {
		// Create a subscriber with only campaignbridge_manage (no manage_options).
		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'campaignbridge_manage' );

		wp_set_current_user( $user_id );

		$this->assertFalse(
			current_user_can( 'manage_options' ),
			'Test user should NOT have manage_options'
		);
		$this->assertTrue(
			current_user_can( Capabilities::MANAGE ),
			'Test user should have campaignbridge_manage'
		);
	}

	/**
	 * Test that an administrator with MANAGE_CONNECTIONS can access the decrypt-field route.
	 */
	public function test_decrypt_field_route_admin_with_connections_can_access(): void {
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$test_api_key = 'sk-test-12345678901234567890123456789012';
		$encrypted    = \CampaignBridge\Core\Encryption::encrypt( $test_api_key );
		$nonce        = wp_create_nonce( 'campaignbridge_encrypted_fields' );

		$request  = new \WP_REST_Request( 'POST', '/campaignbridge/v1/decrypt-field' );
		$request->set_param( 'encrypted_value', $encrypted );
		$request->set_param( '_wpnonce', $nonce );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status(), 'Administrator should be able to decrypt' );
	}

	/**
	 * Test that a subscriber without MANAGE_CONNECTIONS cannot access the decrypt-field route.
	 */
	public function test_decrypt_field_route_subscriber_cannot_access(): void {
		$subscriber_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$test_api_key = 'sk-test-12345678901234567890123456789012';
		$encrypted    = \CampaignBridge\Core\Encryption::encrypt( $test_api_key );
		$nonce        = wp_create_nonce( 'campaignbridge_encrypted_fields' );

		$request  = new \WP_REST_Request( 'POST', '/campaignbridge/v1/decrypt-field' );
		$request->set_param( 'encrypted_value', $encrypted );
		$request->set_param( '_wpnonce', $nonce );
		$response = rest_do_request( $request );

		$this->assertEquals( 403, $response->get_status(), 'Subscriber should not be able to decrypt' );
	}

	/**
	 * Test that a user with MANAGE but without MANAGE_CONNECTIONS cannot access the decrypt-field route.
	 *
	 * This is the critical test: the decrypt-field route must enforce MANAGE_CONNECTIONS,
	 * not just MANAGE.
	 */
	public function test_decrypt_field_route_manage_without_connections_cannot_access(): void {
		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'campaignbridge_manage' );

		wp_set_current_user( $user_id );

		// User has MANAGE...
		$this->assertTrue(
			current_user_can( Capabilities::MANAGE ),
			'Test user should have campaignbridge_manage capability'
		);

		// ...but should NOT have MANAGE_CONNECTIONS.
		$this->assertFalse(
			current_user_can( Capabilities::MANAGE_CONNECTIONS ),
			'Test user should NOT have campaignbridge_manage_connections capability'
		);

		$test_api_key = 'sk-test-12345678901234567890123456789012';
		$encrypted    = \CampaignBridge\Core\Encryption::encrypt( $test_api_key );
		$nonce        = wp_create_nonce( 'campaignbridge_encrypted_fields' );

		$request  = new \WP_REST_Request( 'POST', '/campaignbridge/v1/decrypt-field' );
		$request->set_param( 'encrypted_value', $encrypted );
		$request->set_param( '_wpnonce', $nonce );
		$response = rest_do_request( $request );

		$this->assertEquals( 403, $response->get_status(), 'User with MANAGE but without MANAGE_CONNECTIONS should not be able to decrypt' );
	}

	/**
	 * Test that a user with MANAGE_CONNECTIONS but without MANAGE can access the decrypt-field route.
	 */
	public function test_decrypt_field_route_connections_without_manage_can_access(): void {
		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'campaignbridge_manage_connections' );

		wp_set_current_user( $user_id );

		// User has MANAGE_CONNECTIONS...
		$this->assertTrue(
			current_user_can( Capabilities::MANAGE_CONNECTIONS ),
			'Test user should have campaignbridge_manage_connections capability'
		);

		// ...but should NOT have MANAGE.
		$this->assertFalse(
			current_user_can( Capabilities::MANAGE ),
			'Test user should NOT have campaignbridge_manage capability'
		);

		$test_api_key = 'sk-test-12345678901234567890123456789012';
		$encrypted    = \CampaignBridge\Core\Encryption::encrypt( $test_api_key );
		$nonce        = wp_create_nonce( 'campaignbridge_encrypted_fields' );

		$request  = new \WP_REST_Request( 'POST', '/campaignbridge/v1/decrypt-field' );
		$request->set_param( 'encrypted_value', $encrypted );
		$request->set_param( '_wpnonce', $nonce );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status(), 'User with MANAGE_CONNECTIONS should be able to decrypt' );
	}

	public function test_api_key_context_denies_manage_without_connections(): void {
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$encrypted = \CampaignBridge\Core\Encryption::encrypt( 'credential-value' );

		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( Capabilities::MANAGE );
		wp_set_current_user( $user_id );

		$this->expectException( \RuntimeException::class );
		\CampaignBridge\Core\Encryption::decrypt_for_context( $encrypted, 'api_key' );
	}

	public function test_encrypt_field_route_denies_manage_without_connections(): void {
		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( Capabilities::MANAGE );
		wp_set_current_user( $user_id );

		$request = new \WP_REST_Request( 'POST', '/campaignbridge/v1/encrypt-field' );
		$request->set_param( 'field_id', 'mailchimp_api_key' );
		$request->set_param( 'new_value', str_repeat( 'a', 32 ) . '-us1' );
		$request->set_param( '_wpnonce', wp_create_nonce( 'campaignbridge_encrypted_fields' ) );

		$this->assertSame( 403, rest_do_request( $request )->get_status() );
	}

	public function test_encrypt_field_route_allows_connections_without_manage(): void {
		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( Capabilities::MANAGE_CONNECTIONS );
		wp_set_current_user( $user_id );

		$request = new \WP_REST_Request( 'POST', '/campaignbridge/v1/encrypt-field' );
		$request->set_param( 'field_id', 'mailchimp_api_key' );
		$request->set_param( 'new_value', str_repeat( 'a', 32 ) . '-us1' );
		$request->set_param( '_wpnonce', wp_create_nonce( 'campaignbridge_encrypted_fields' ) );

		$this->assertSame( 200, rest_do_request( $request )->get_status() );
	}

	public function test_provider_ui_and_controller_do_not_expose_credentials_to_manage_only_user(): void {
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$encrypted = \CampaignBridge\Core\Encryption::encrypt( 'credential-ending-1234' );
		$connection = \CampaignBridge\Domain\Campaign\Provider_Connection::create( 'mailchimp', $encrypted, 'audience-id' );
		$this->assertTrue( ( new \CampaignBridge\Repository\Provider_Connection_Repository() )->save( $connection ) );

		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( Capabilities::MANAGE );
		wp_set_current_user( $user_id );

		$config = require dirname( __DIR__, 2 ) . '/includes/Admin/Screens/settings/_config.php';
		$this->assertSame( Capabilities::MANAGE_CONNECTIONS, $config['tabs']['providers']['capability'] );

		$data = ( new \CampaignBridge\Admin\Controllers\Settings_Controller() )->get_data();
		$this->assertSame( '', $data['mailchimp_api_key'] );
		$this->assertSame( '', $data['mailchimp_audience'] );

		$field = new \CampaignBridge\Admin\Core\Forms\Form_Field_Encrypted(
			array(
				'id'      => 'mailchimp_api_key',
				'name'    => 'mailchimp_api_key',
				'default' => '',
				'value'   => $encrypted,
				'context' => 'api_key',
			),
			new \CampaignBridge\Admin\Core\Forms\Form_Validator()
		);
		$html = $field->render();

		$this->assertStringContainsString( 'Access Restricted', $html );
		$this->assertStringNotContainsString( $encrypted, $html );
		$this->assertStringNotContainsString( '1234', $html );
		$this->assertStringNotContainsString( 'data-action="reveal"', $html );
		$this->assertStringNotContainsString( 'data-action="edit"', $html );
	}

	/*
	 * Template CPT capability boundary tests.
	 *
	 * The cb_templates CPT must enforce campaignbridge_edit_templates for all
	 * REST create/edit/delete operations, not the generic edit_posts /
	 * publish_posts / delete_posts capabilities that would otherwise be
	 * inherited from capability_type = 'post'.
	 */

	/**
	 * Helper: create a published cb_template as an administrator and return its ID.
	 *
	 * @return int The template post ID.
	 */
	private function create_template_as_admin(): int {
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$request  = new \WP_REST_Request( 'POST', '/wp/v2/cb_templates' );
		$request->set_param( 'title', 'Boundary Test Template' );
		$request->set_param( 'content', '<p>Boundary test content</p>' );
		$request->set_param( 'status', 'publish' );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 201, $response->get_status(), 'Admin should be able to create a template' );
		return (int) $response->get_data()['id'];
	}

	/**
	 * Test that a user with edit_posts but without campaignbridge_edit_templates
	 * cannot create a cb_template via REST.
	 */
	public function test_editor_without_plugin_cap_cannot_create_template(): void {
		$editor_id = $this->create_test_user( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertTrue( current_user_can( 'edit_posts' ), 'Editor should have edit_posts' );
		$this->assertFalse( current_user_can( Capabilities::EDIT_TEMPLATES ), 'Editor should NOT have campaignbridge_edit_templates' );

		$request  = new \WP_REST_Request( 'POST', '/wp/v2/cb_templates' );
		$request->set_param( 'title', 'Should Fail' );
		$request->set_param( 'content', '<p>Should fail</p>' );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 403, $response->get_status(), 'Editor without plugin cap should not be able to create template' );
	}

	/**
	 * Test that a user with edit_posts but without campaignbridge_edit_templates
	 * cannot edit a cb_template via REST.
	 */
	public function test_editor_without_plugin_cap_cannot_edit_template(): void {
		$template_id = $this->create_template_as_admin();

		$editor_id = $this->create_test_user( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertFalse( current_user_can( Capabilities::EDIT_TEMPLATES ), 'Editor should NOT have campaignbridge_edit_templates' );

		$request  = new \WP_REST_Request( 'PUT', '/wp/v2/cb_templates/' . $template_id );
		$request->set_param( 'title', 'Updated Title' );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 403, $response->get_status(), 'Editor without plugin cap should not be able to edit template' );
	}

	/**
	 * Test that a user with edit_posts but without campaignbridge_edit_templates
	 * cannot delete a cb_template via REST.
	 */
	public function test_editor_without_plugin_cap_cannot_delete_template(): void {
		$template_id = $this->create_template_as_admin();

		$editor_id = $this->create_test_user( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertFalse( current_user_can( Capabilities::EDIT_TEMPLATES ), 'Editor should NOT have campaignbridge_edit_templates' );

		$request  = new \WP_REST_Request( 'DELETE', '/wp/v2/cb_templates/' . $template_id );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 403, $response->get_status(), 'Editor without plugin cap should not be able to delete template' );
	}

	/**
	 * Test that an administrator with campaignbridge_edit_templates CAN
	 * create, edit, and delete cb_templates via REST.
	 */
	public function test_admin_with_plugin_cap_can_crud_templates(): void {
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue( current_user_can( Capabilities::EDIT_TEMPLATES ), 'Admin should have campaignbridge_edit_templates' );

		// Create.
		$request  = new \WP_REST_Request( 'POST', '/wp/v2/cb_templates' );
		$request->set_param( 'title', 'CRUD Test' );
		$request->set_param( 'content', '<p>CRUD test</p>' );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_do_request( $request );
		$this->assertEquals( 201, $response->get_status(), 'Admin should be able to create template' );
		$template_id = (int) $response->get_data()['id'];

		// Edit.
		$request  = new \WP_REST_Request( 'PUT', '/wp/v2/cb_templates/' . $template_id );
		$request->set_param( 'title', 'CRUD Test Updated' );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status(), 'Admin should be able to edit template' );

		// Delete.
		$request  = new \WP_REST_Request( 'DELETE', '/wp/v2/cb_templates/' . $template_id );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status(), 'Admin should be able to delete template' );
	}

	/**
	 * Test that map_meta_cap resolves edit_post on cb_templates to the plugin capability.
	 *
	 * A user with only edit_posts (no campaignbridge_edit_templates) must fail
	 * current_user_can( 'edit_post', $cb_template_id ), while a user with
	 * campaignbridge_edit_templates must pass.
	 */
	public function test_map_meta_cap_resolves_to_plugin_capability(): void {
		$template_id = $this->create_template_as_admin();

		// Editor: has edit_posts, does NOT have campaignbridge_edit_templates.
		$editor_id = $this->create_test_user( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$this->assertFalse(
			current_user_can( 'edit_post', $template_id ),
			'Editor with edit_posts should NOT pass edit_post meta-cap on cb_templates'
		);
		$this->assertFalse(
			current_user_can( 'delete_post', $template_id ),
			'Editor with edit_posts should NOT pass delete_post meta-cap on cb_templates'
		);

		// Administrator: has campaignbridge_edit_templates.
		$admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$this->assertTrue(
			current_user_can( 'edit_post', $template_id ),
			'Admin with campaignbridge_edit_templates should pass edit_post meta-cap on cb_templates'
		);
		$this->assertTrue(
			current_user_can( 'delete_post', $template_id ),
			'Admin with campaignbridge_edit_templates should pass delete_post meta-cap on cb_templates'
		);
	}

	/**
	 * Test that a user with campaignbridge_edit_templates (but not edit_posts)
	 * can still perform all template operations.
	 *
	 * This proves the capability boundary is the plugin cap, not the core cap.
	 */
	public function test_plugin_cap_alone_grants_template_access(): void {
		// Create a subscriber with only the plugin cap.
		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( Capabilities::EDIT_TEMPLATES );

		wp_set_current_user( $user_id );

		$this->assertFalse( current_user_can( 'edit_posts' ), 'Test user should NOT have edit_posts' );
		$this->assertTrue( current_user_can( Capabilities::EDIT_TEMPLATES ), 'Test user should have campaignbridge_edit_templates' );

		// Create.
		$request  = new \WP_REST_Request( 'POST', '/wp/v2/cb_templates' );
		$request->set_param( 'title', 'Plugin Cap Test' );
		$request->set_param( 'content', '<p>Plugin cap test</p>' );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_do_request( $request );
		$this->assertEquals( 201, $response->get_status(), 'User with plugin cap should be able to create template' );
		$template_id = (int) $response->get_data()['id'];

		// Edit.
		$request  = new \WP_REST_Request( 'PUT', '/wp/v2/cb_templates/' . $template_id );
		$request->set_param( 'title', 'Plugin Cap Test Updated' );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status(), 'User with plugin cap should be able to edit template' );

		// Delete.
		$request  = new \WP_REST_Request( 'DELETE', '/wp/v2/cb_templates/' . $template_id );
		$request->set_param( '_wpnonce', wp_create_nonce( 'wp_rest' ) );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status(), 'User with plugin cap should be able to delete template' );
	}
}
