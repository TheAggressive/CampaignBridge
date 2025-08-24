<?php
/**
 * Asset Manager for CampaignBridge Admin Interface.
 *
 * This class provides a centralized system for managing all JavaScript and CSS assets
 * used throughout the CampaignBridge admin interface. It follows WordPress best practices
 * for asset registration and enqueuing, ensuring optimal performance and proper
 * dependency management.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset Manager: provides methods to register scripts and styles.
 */
class AssetManager {
	/**
	 * Initialize the asset manager and set up all necessary WordPress hooks.
	 *
	 * This method sets up the complete asset management system by registering
	 * WordPress hooks for asset registration and enqueuing. It ensures that
	 * all assets are properly registered during admin initialization and
	 * automatically enqueues global assets on CampaignBridge pages.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_global_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_page_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_global_assets' ) );
	}

	/**
	 * Register global JavaScript files used across multiple CampaignBridge admin pages.
	 *
	 * This method registers the core JavaScript files that provide common functionality
	 * shared across all CampaignBridge admin pages. These scripts are automatically
	 * enqueued on any CampaignBridge page and serve as the foundation for
	 * page-specific functionality.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register_global_scripts(): void {
		wp_register_script(
			'campaignbridge-admin-base',
			CB_URL . 'dist/scripts/admin-base.js',
			array( 'jquery' ),
			CB_VERSION,
			true
		);
	}

	/**
	 * Register global CSS files used across multiple CampaignBridge admin pages.
	 *
	 * This method registers the core CSS files that provide consistent styling
	 * and visual appearance across all CampaignBridge admin pages. These styles
	 * establish the visual foundation and ensure consistent user experience.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register_global_styles(): void {
		wp_register_style(
			'campaignbridge-admin-base',
			CB_URL . 'dist/styles/admin-base.css',
			array(),
			CB_VERSION
		);
	}

	/**
	 * Register page-specific JavaScript files for individual CampaignBridge admin pages.
	 *
	 * This method registers JavaScript files that provide functionality specific to
	 * individual admin pages. Each script is designed to handle the unique requirements
	 * of its respective page while building upon the global admin foundation.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register_scripts(): void {
		// Register dashboard page script.
		wp_register_script(
			'campaignbridge-dashboard',
			CB_URL . 'dist/scripts/dashboard.js',
			array( 'campaignbridge-admin-base' ),
			CB_VERSION,
			true
		);

		// Register post types page script.
		wp_register_script(
			'campaignbridge-post-types',
			CB_URL . 'dist/scripts/post-types.js',
			array( 'campaignbridge-admin-base' ),
			CB_VERSION,
			true
		);

		// Register settings page script.
		wp_register_script(
			'campaignbridge-settings',
			CB_URL . 'dist/scripts/settings.js',
			array( 'campaignbridge-admin-base' ),
			CB_VERSION,
			true
		);

		// Register status page script.
		wp_register_script(
			'campaignbridge-status',
			CB_URL . 'dist/scripts/status.js',
			array( 'campaignbridge-admin-base' ),
			CB_VERSION,
			true
		);

		// Register template manager page script.
		wp_register_script(
			'campaignbridge-template-manager',
			CB_URL . 'dist/scripts/admin/template-manager.js',
			array(
				'wp-edit-post',
				'wp-block-editor',
				'wp-blocks',
				'wp-components',
				'wp-element',
				'wp-data',
				'wp-core-data',
				'wp-plugins',
				'wp-notices',
				'wp-api-fetch',
				'wp-i18n',
				'wp-url',
				'wp-keyboard-shortcuts',
				'wp-dom-ready',
				'wp-editor',
				'wp-format-library',
			),
			CB_VERSION,
			true
		);
	}

	/**
	 * Register page-specific CSS files for individual CampaignBridge admin pages.
	 *
	 * This method registers CSS files that provide styling specific to individual
	 * admin pages. Each stylesheet is designed to enhance the visual appearance
	 * and user experience of its respective page while maintaining consistency
	 * with the global design system.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register_styles(): void {
		// Register dashboard page style.
		wp_register_style(
			'campaignbridge-dashboard',
			CB_URL . 'dist/styles/dashboard.css',
			array( 'campaignbridge-admin-base' ),
			CB_VERSION
		);

		// Register post types page style.
		wp_register_style(
			'campaignbridge-post-types',
			CB_URL . 'dist/styles/post-types.css',
			array( 'campaignbridge-admin-base' ),
			CB_VERSION
		);

		// Register settings page style.
		wp_register_style(
			'campaignbridge-settings',
			CB_URL . 'dist/styles/settings.css',
			array( 'campaignbridge-admin-base' ),
			CB_VERSION
		);

		// Register status page style.
		wp_register_style(
			'campaignbridge-status',
			CB_URL . 'dist/styles/status.css',
			array( 'campaignbridge-admin-base' ),
			CB_VERSION
		);

		// Register template manager page style.
		wp_register_style(
			'campaignbridge-template-manager',
			CB_URL . 'dist/styles/template-manager.css',
			array( 'campaignbridge-admin-base' ),
			CB_VERSION
		);
	}

	/**
	 * Register global assets (scripts and styles).
	 *
	 * @return void
	 */
	public static function register_global_assets(): void {
		self::register_global_scripts();
		self::register_global_styles();
	}

	/**
	 * Register page-specific assets (scripts and styles).
	 *
	 * @return void
	 */
	public static function register_page_assets(): void {
		self::register_scripts();
		self::register_styles();
	}

	/**
	 * Automatically enqueue global assets on CampaignBridge admin pages.
	 *
	 * This method is hooked into WordPress's admin_enqueue_scripts action and
	 * automatically loads the global CSS and JavaScript files on any page that
	 * is identified as a CampaignBridge admin page. It ensures that the
	 * foundational styling and functionality are always available.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function enqueue_global_assets(): void {
		// Only enqueue global assets on CampaignBridge pages.
		if ( ! \CampaignBridge\Admin\PageUtils::is_campaignbridge_page( get_current_screen()?->id ?? '' ) ) {
			return;
		}

		// Always enqueue global assets on CampaignBridge pages.
		wp_enqueue_style( 'campaignbridge-admin-base' );
		wp_enqueue_script( 'campaignbridge-admin-base' );
	}
}
