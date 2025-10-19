<?php
/**
 * Admin Bootstrap - NEW System
 *
 * @package CampaignBridge\Admin
 */

namespace CampaignBridge\Admin;

use CampaignBridge\Admin\Core\Screen_Registry;

/**
 * Admin Bootstrap - File-based admin system
 *
 * @package CampaignBridge\Admin
 */
class Admin {

	/**
	 * Screen registry instance.
	 *
	 * @var Screen_Registry
	 */
	private Screen_Registry $screen_registry;

	/**
	 * Singleton instance.
	 *
	 * @var Admin|null
	 */
	private static ?Admin $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Admin
	 */
	public static function get_instance(): Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor - initializes admin system only when in admin area.
	 */
	private function __construct() {
		if ( ! is_admin() ) {
			return;
		}

		$this->init();
	}

	/**
	 * Initialize the admin system.
	 */
	private function init(): void {
		// Initialize screen registry.
		$screens_path          = __DIR__ . '/Screens';
		$this->screen_registry = new Screen_Registry( $screens_path, 'campaignbridge' );
		$this->screen_registry->init();

		// Add parent menu.
		add_action( 'admin_menu', array( $this, 'add_parent_menu' ), 9 );
		add_action( 'admin_menu', array( $this, 'remove_parent_from_submenu' ), 10 );

		// Enqueue global assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_global_assets' ) );
	}


	/**
	 * Add the main CampaignBridge menu page.
	 */
	public function add_parent_menu(): void {
		add_menu_page(
			__( 'CampaignBridge', 'campaignbridge' ),
			__( 'CampaignBridge', 'campaignbridge' ),
			'manage_options',
			'campaignbridge',
			array( $this, 'redirect_to_first_submenu' ),
			'dashicons-email-alt',
			30
		);
	}

	/**
	 * Remove the parent menu item from the submenu to avoid duplication.
	 */
	public function remove_parent_from_submenu(): void {
		global $submenu;

		// Remove the parent menu item from submenu array to prevent duplication.
		if ( isset( $submenu['campaignbridge'] ) ) {
			foreach ( $submenu['campaignbridge'] as $key => $item ) {
				if ( isset( $item[2] ) && 'campaignbridge' === $item[2] ) {
					unset( $submenu['campaignbridge'][ $key ] );
					break;
				}
			}
		}
	}

	/**
	 * Redirect parent menu clicks to the first available submenu.
	 */
	public function redirect_to_first_submenu(): void {
		// Get the first submenu under our parent menu.
		global $submenu;

		if ( isset( $submenu['campaignbridge'] ) && is_array( $submenu['campaignbridge'] ) ) {
			$first_submenu = reset( $submenu['campaignbridge'] );

			if ( isset( $first_submenu[2] ) ) {
				// Redirect to the first submenu.
				wp_safe_redirect( admin_url( 'admin.php?page=' . $first_submenu[2] ) );
				exit;
			}
		}

		// Fallback: redirect to dashboard if no submenus found.
		wp_safe_redirect( admin_url() );
		exit;
	}

	/**
	 * Enqueue global admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_global_assets( string $hook ): void {
		// Only on CampaignBridge pages.
		if ( false === strpos( $hook, 'campaignbridge' ) ) {
			return;
		}

		// Global admin assets using Asset_Manager.
		Asset_Manager::enqueue_asset( 'cb-admin-global-styles', 'dist/styles/styles.asset.php' );

		// Global form styles - load on all admin pages since many screens use forms.
		Asset_Manager::enqueue_asset( 'cb-admin-form-styles', 'dist/styles/admin/forms/forms.asset.php' );

		// Localize global data.
		wp_localize_script(
			'cb-admin-global-scripts',
			'campaignBridge',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'restUrl'   => rest_url( 'campaignbridge/v1/' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'pluginUrl' => \CampaignBridge_Plugin::url(),
			)
		);
	}
}

// Initialize.
Admin::get_instance();
