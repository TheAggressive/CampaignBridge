<?php
/**
 * Unit tests for the Capabilities class.
 *
 * @package CampaignBridge\Tests
 */

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Core\Capabilities;

/**
 * Class Capabilities_Test
 */
class Capabilities_Test extends Test_Case {

	/**
	 * Test that all expected capability constants are defined.
	 */
	public function test_capability_constants_are_defined(): void {
		$this->assertSame( 'campaignbridge_manage', Capabilities::MANAGE );
		$this->assertSame( 'campaignbridge_manage_connections', Capabilities::MANAGE_CONNECTIONS );
		$this->assertSame( 'campaignbridge_edit_templates', Capabilities::EDIT_TEMPLATES );
		$this->assertSame( 'campaignbridge_create_campaigns', Capabilities::CREATE_CAMPAIGNS );
		$this->assertSame( 'campaignbridge_send_campaigns', Capabilities::SEND_CAMPAIGNS );
		$this->assertSame( 'campaignbridge_view_reports', Capabilities::VIEW_REPORTS );
	}

	/**
	 * Test that the ALL constant contains all expected capabilities.
	 */
	public function test_all_contains_all_capabilities(): void {
		$this->assertSame(
			array(
				'campaignbridge_manage',
				'campaignbridge_manage_connections',
				'campaignbridge_edit_templates',
				'campaignbridge_create_campaigns',
				'campaignbridge_send_campaigns',
				'campaignbridge_view_reports',
			),
			Capabilities::ALL
		);
	}

	/**
	 * Test that capabilities are registered on the administrator role.
	 */
	public function test_register_capabilities_grants_caps_to_administrator(): void {
		// Remove any existing caps first.
		$role = get_role( 'administrator' );
		if ( $role ) {
			foreach ( Capabilities::ALL as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		// Register capabilities.
		Capabilities::register();

		// Verify caps were granted.
		$role = get_role( 'administrator' );
		$this->assertNotNull( $role );

		foreach ( Capabilities::ALL as $cap ) {
			$this->assertTrue(
				$role->has_cap( $cap ),
				"Administrator role should have '{$cap}' capability after registration"
			);
		}
	}

	/**
	 * Test that capabilities are idempotent (registering twice doesn't error).
	 */
	public function test_register_capabilities_is_idempotent(): void {
		Capabilities::register();
		Capabilities::register(); // Should not throw or error.

		$role = get_role( 'administrator' );
		$this->assertNotNull( $role );
		$this->assertTrue( $role->has_cap( Capabilities::MANAGE ) );
	}

	/**
	 * Test that unregister removes capabilities from the administrator role.
	 */
	public function test_unregister_removes_capabilities(): void {
		Capabilities::register();
		Capabilities::unregister();

		$role = get_role( 'administrator' );
		$this->assertNotNull( $role );

		foreach ( Capabilities::ALL as $cap ) {
			$this->assertFalse(
				$role->has_cap( $cap ),
				"Administrator role should not have '{$cap}' capability after unregistration"
			);
		}
	}

	/**
	 * Test that non-administrator roles do not receive capabilities.
	 */
	public function test_non_administrator_roles_do_not_receive_capabilities(): void {
		Capabilities::register();

		$roles_to_check = array( 'editor', 'author', 'contributor', 'subscriber' );
		foreach ( $roles_to_check as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$this->assertFalse(
					$role->has_cap( Capabilities::MANAGE ),
					"Role '{$role_name}' should not have 'campaignbridge_manage' capability"
				);
			}
		}
	}
}