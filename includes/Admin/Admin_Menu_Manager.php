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

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Post_Types\Post_Type_Email_Template;

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
		// After every screen registers: WordPress copies the parent into the
		// submenu when the first entry the user may see is added, which for a
		// user without template access happens after priority 10.
		\add_action( 'admin_menu', array( $this, 'remove_parent_from_submenu' ), PHP_INT_MAX );
		\add_action( 'admin_menu', array( $this, 'add_new_template_submenu' ), 11 );
	}

	/**
	 * Give the new-template screen its own entry under CampaignBridge.
	 *
	 * WordPress adds no "Add New" item for a post type shown under another
	 * plugin's menu, so it authorizes post-new.php against Posts → Add New
	 * instead, which needs edit_posts. A template author who holds only the
	 * template capability was then refused the editor. Registering the
	 * screen here makes WordPress check the template capability.
	 *
	 * @return void
	 */
	public function add_new_template_submenu(): void {
		\add_submenu_page(
			self::MENU_SLUG,
			__( 'Add Email Template', 'campaignbridge' ),
			__( 'Add Email Template', 'campaignbridge' ),
			Capabilities::EDIT_TEMPLATES,
			'post-new.php?post_type=' . Post_Type_Email_Template::POST_TYPE
		);
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
			self::parent_capability(),
			self::MENU_SLUG,
			array( $this, 'redirect_to_first_submenu' ),
			Brand_Assets::menu_icon(),
			30
		);
	}

	/**
	 * The capability that shows the CampaignBridge menu to the current user.
	 *
	 * WordPress authorizes a screen whose own entry it cannot match, such as
	 * a post type's list or editor, against the top-level menu. Requiring the
	 * management capability there refused template authors and campaign
	 * authors their own screens, so the menu uses the first CampaignBridge
	 * capability the user holds. Each screen still requires its own.
	 *
	 * @return string
	 */
	private static function parent_capability(): string {
		foreach ( array( Capabilities::MANAGE, Capabilities::CREATE_CAMPAIGNS, Capabilities::EDIT_TEMPLATES ) as $capability ) {
			if ( \current_user_can( $capability ) ) {
				return $capability;
			}
		}

		return Capabilities::MANAGE;
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
