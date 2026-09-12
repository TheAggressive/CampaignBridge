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

	/**
	 * Test that ensure_registered grants all capabilities to administrator
	 * on a fresh install (no stored schema version).
	 */
	public function test_ensure_registered_grants_all_capabilities_on_fresh_install(): void {
		// Simulate a fresh install: no schema version stored.
		delete_option( 'campaignbridge_capability_schema' );

		// Remove all CampaignBridge capabilities from administrator.
		$role = get_role( 'administrator' );
		foreach ( Capabilities::ALL as $cap ) {
			$role->remove_cap( $cap );
		}

		// Run ensure_registered.
		Capabilities::ensure_registered();

		// Verify all capabilities are granted.
		$role = get_role( 'administrator' );
		foreach ( Capabilities::ALL as $cap ) {
			$this->assertTrue(
				$role->has_cap( $cap ),
				"Administrator should have '{$cap}' capability after ensure_registered"
			);
		}

		// Verify schema version is stored.
		$this->assertSame( Capabilities::SCHEMA_VERSION, get_option( 'campaignbridge_capability_schema' ) );
	}

	/**
	 * Test that ensure_registered repairs an existing installation
	 * that is missing one capability.
	 */
	public function test_ensure_registered_repairs_missing_capability(): void {
		// Simulate an existing install with a stale schema version.
		update_option( 'campaignbridge_capability_schema', 0 );

		// Remove one capability from administrator.
		$role = get_role( 'administrator' );
		$role->remove_cap( Capabilities::MANAGE );

		// Verify it's missing before repair.
		$this->assertFalse( $role->has_cap( Capabilities::MANAGE ) );

		// Run ensure_registered.
		Capabilities::ensure_registered();

		// Verify the missing capability is restored.
		$role = get_role( 'administrator' );
		$this->assertTrue(
			$role->has_cap( Capabilities::MANAGE ),
			"Administrator should have 'campaignbridge_manage' capability after repair"
		);

		// Verify schema version is updated.
		$this->assertSame( Capabilities::SCHEMA_VERSION, get_option( 'campaignbridge_capability_schema' ) );
	}

	/**
	 * Test that ensure_registered is idempotent (safe to call multiple times).
	 */
	public function test_ensure_registered_is_idempotent(): void {
		// Run ensure_registered multiple times.
		Capabilities::ensure_registered();
		Capabilities::ensure_registered();
		Capabilities::ensure_registered();

		// Verify all capabilities are still present.
		$role = get_role( 'administrator' );
		foreach ( Capabilities::ALL as $cap ) {
			$this->assertTrue(
				$role->has_cap( $cap ),
				"Administrator should retain '{$cap}' capability after repeated ensure_registered calls"
			);
		}

		// Verify schema version is correct.
		$this->assertSame( Capabilities::SCHEMA_VERSION, get_option( 'campaignbridge_capability_schema' ) );
	}

	/**
	 * Test that ensure_registered does not grant capabilities to non-admin roles.
	 */
	public function test_ensure_registered_does_not_grant_caps_to_non_admin_roles(): void {
		// Simulate a fresh install.
		delete_option( 'campaignbridge_capability_schema' );

		// Run ensure_registered.
		Capabilities::ensure_registered();

		// Verify non-admin roles do not have any CampaignBridge capabilities.
		$roles_to_check = array( 'editor', 'author', 'contributor', 'subscriber' );
		foreach ( $roles_to_check as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( Capabilities::ALL as $cap ) {
					$this->assertFalse(
						$role->has_cap( $cap ),
						"Role '{$role_name}' should not have '{$cap}' capability"
					);
				}
			}
		}
	}

	/**
	 * Test that the admin menu capability resolves to Capabilities::MANAGE.
	 */
	public function test_admin_menu_uses_manage_capability_constant(): void {
		// Create an administrator user and set as current user.
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		// Ensure the current user has the capability.
		$this->assertTrue( current_user_can( Capabilities::MANAGE ) );

		// Reset the menu global to avoid duplicates.
		global $menu;
		$menu = array();

		// Register the menu.
		$manager = new \CampaignBridge\Admin\Admin_Menu_Manager();
		$manager->add_parent_menu();

		// Verify the menu is registered with the correct capability.
		$found = false;
		foreach ( $menu as $item ) {
			if ( 'campaignbridge' === $item[2] ) {
				$found = true;
				$this->assertSame(
					Capabilities::MANAGE,
					$item[1],
					'Admin menu should use Capabilities::MANAGE'
				);
				break;
			}
		}

		$this->assertTrue( $found, 'CampaignBridge menu should be registered' );
	}
}