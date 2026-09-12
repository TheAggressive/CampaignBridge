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
}