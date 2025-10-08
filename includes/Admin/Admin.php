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
			null,
			'dashicons-email-alt',
			30
		);
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

		// Global admin CSS.
		wp_enqueue_style(
			'cb-admin-global',
			\CampaignBridge_Plugin::url() . 'dist/styles/styles.css',
			array(),
			\CampaignBridge_Plugin::VERSION
		);

		// Global admin JS.
		wp_enqueue_script(
			'cb-admin-global',
			\CampaignBridge_Plugin::url() . 'dist/scripts/admin/settings.js',
			array(),
			\CampaignBridge_Plugin::VERSION,
			true
		);

		// Localize global data.
		wp_localize_script(
			'cb-admin-global',
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
