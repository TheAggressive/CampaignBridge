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
	 * Menu manager instance.
	 *
	 * @var Admin_Menu_Manager
	 */
	private Admin_Menu_Manager $menu_manager;

	/**
	 * Security manager instance.
	 *
	 * @var Admin_Security_Manager
	 */
	private Admin_Security_Manager $security_manager;

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
		if ( ! \is_admin() ) {
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

		// Initialize menu and security managers.
		$this->menu_manager     = new Admin_Menu_Manager();
		$this->security_manager = new Admin_Security_Manager();

		// Initialize menu and security systems.
		$this->menu_manager->init();
		$this->security_manager->init();

		// Enqueue global assets.
		\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_global_assets' ) );
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
		Asset_Manager::enqueue_asset( 'campaignbridge-admin-global-styles', 'dist/styles/styles.asset.php' );

		// Global form styles - load on all admin pages since many screens use forms.
		Asset_Manager::enqueue_asset( 'campaignbridge-admin-form-styles', 'dist/styles/admin/forms/forms.asset.php' );

		// Encrypted fields functionality.
		Asset_Manager::enqueue_asset_script(
			'campaignbridge-encrypted-fields',
			'dist/scripts/admin/forms/encrypted-fields/index.asset.php'
		);

		// Real-time form validation functionality.
		Asset_Manager::enqueue_asset_script(
			'campaignbridge-form-validation',
			'dist/scripts/admin/forms/validation/index.asset.php'
		);

		// Form loading state functionality.
		Asset_Manager::enqueue_asset_script(
			'campaignbridge-form-loading',
			'dist/scripts/admin/forms/form-loading/index.asset.php'
		);

		// Localize encrypted fields data.
		\wp_localize_script(
			'campaignbridge-encrypted-fields',
			'campaignbridgeAdmin',
			array(
				'restUrl'  => \rest_url( 'campaignbridge/v1/' ),
				'nonce'    => \wp_create_nonce( 'campaignbridge_encrypted_fields' ),
				'security' => array(
					'revealTimeout'  => 8000, // 8 seconds for reveal timeout
					'maxRetries'     => 1,     // Limit retries to prevent abuse.
					'requestTimeout' => 30000, // 30 seconds max for requests
				),
				'i18n'     => array(
					'loading' => __( 'Loading...', 'campaignbridge' ),
					'saving'  => __( 'Saving...', 'campaignbridge' ),
					'save'    => __( 'Save', 'campaignbridge' ),
				),
			)
		);
	}
}

// Initialize.
Admin::get_instance();
