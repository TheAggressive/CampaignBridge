<?php
/**
 * Admin Menu Manager - Handles WordPress admin menu operations
 *
 * Manages the creation, modification, and navigation of WordPress admin menus
 * for the CampaignBridge plugin, including parent menus, submenus, and redirects.
 *
 * @package CampaignBridge\Admin
 */

namespace CampaignBridge\Admin;

/**
 * Admin Menu Manager Class
 *
 * Handles all WordPress admin menu operations including parent menu creation,
 * submenu management, and navigation redirects.
 *
 * @package CampaignBridge\Admin
 */
class Admin_Menu_Manager {

	/**
	 * Menu slug prefix.
	 *
	 * @var string
	 */
	private const MENU_SLUG = 'campaignbridge';

	/**
	 * Initialize the menu system.
	 *
	 * @return void
	 */
	public function init(): void {
		\add_action( 'admin_menu', array( $this, 'add_parent_menu' ), 9 );
		\add_action( 'admin_menu', array( $this, 'remove_parent_from_submenu' ), 10 );
	}

	/**
	 * Add the main CampaignBridge menu page.
	 *
	 * @return void
	 */
	public function add_parent_menu(): void {
		add_menu_page(
			__( 'CampaignBridge', 'campaignbridge' ),
			__( 'CampaignBridge', 'campaignbridge' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'redirect_to_first_submenu' ),
			'dashicons-email-alt',
			30
		);
	}

	/**
	 * Remove the parent menu item from the submenu to avoid duplication.
	 *
	 * @return void
	 */
	public function remove_parent_from_submenu(): void {
		global $submenu;

		// Remove the parent menu item from submenu array to prevent duplication.
		if ( isset( $submenu[ self::MENU_SLUG ] ) ) {
			foreach ( $submenu[ self::MENU_SLUG ] as $key => $item ) {
				if ( isset( $item[2] ) && self::MENU_SLUG === $item[2] ) {
					unset( $submenu[ self::MENU_SLUG ][ $key ] );
					break;
				}
			}
		}
	}

	/**
	 * Redirect parent menu clicks to the first available submenu.
	 *
	 * @return void
	 */
	public function redirect_to_first_submenu(): void {
		// Get the first submenu under our parent menu.
		global $submenu;

		if ( isset( $submenu[ self::MENU_SLUG ] ) && is_array( $submenu[ self::MENU_SLUG ] ) ) {
			$first_submenu = reset( $submenu[ self::MENU_SLUG ] );

			if ( isset( $first_submenu[2] ) ) {
				// Redirect to the first submenu.
				\wp_safe_redirect( \admin_url( 'admin.php?page=' . $first_submenu[2] ) );
				exit;
			}
		}

		// Fallback: redirect to dashboard if no submenus found.
		\wp_safe_redirect( \admin_url() );
		exit;
	}
}
