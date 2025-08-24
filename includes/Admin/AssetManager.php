<?php
/**
 * Asset Manager for CampaignBridge Admin Interface.
 *
 * This class provides a centralized system for managing all JavaScript and CSS assets
 * used throughout the CampaignBridge admin interface. It follows WordPress best practices
 * for asset registration and enqueuing, ensuring optimal performance and proper
 * dependency management.
 *
 * Key Features:
 * - Global asset registration (scripts and styles used across multiple pages)
 * - Page-specific asset registration (scripts and styles unique to individual pages)
 * - Automatic global asset enqueuing on CampaignBridge pages
 * - Dependency management and version control
 * - Conditional asset loading based on current admin page
 *
 * Asset Management Strategy:
 * 1. All assets are registered during admin_init for optimal performance
 * 2. Global assets are automatically enqueued on any CampaignBridge page
 * 3. Page-specific assets are enqueued by individual page classes
 * 4. Dependencies are properly managed to avoid conflicts
 * 5. Version control ensures cache busting when assets are updated
 *
 * Usage:
 * - Call AssetManager::init() during plugin initialization
 * - Global assets are automatically handled
 * - Page classes call their specific enqueue methods
 * - No manual asset management required in most cases
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
	 * Hook Registration:
	 * - admin_init: Registers all global and page-specific assets
	 * - admin_enqueue_scripts: Automatically enqueues global assets
	 *
	 * Asset Registration Strategy:
	 * - Global assets are registered first for dependency management
	 * - Page-specific assets are registered with proper dependencies
	 * - All assets use version control for cache busting
	 * - Dependencies are managed to prevent conflicts
	 *
	 * Performance Considerations:
	 * - Assets are registered early to optimize WordPress processing
	 * - Conditional enqueuing prevents unnecessary asset loading
	 * - Dependency management ensures optimal loading order
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
	 * Global Scripts Registered:
	 * - campaignbridge-admin-base: Core admin functionality and utilities
	 * - jQuery dependency for WordPress compatibility
	 * - Common UI components and interactions
	 * - Shared utility functions and helpers
	 *
	 * Dependencies:
	 * - jQuery: WordPress core jQuery library for DOM manipulation
	 * - WordPress admin scripts for consistent behavior
	 *
	 * Usage:
	 * - Automatically loaded on all CampaignBridge pages
	 * - Provides foundation for page-specific scripts
	 * - Handles common admin functionality and interactions
	 *
	 * Performance Notes:
	 * - Scripts are registered but not automatically enqueued
	 * - Conditional loading prevents unnecessary script execution
	 * - Version control ensures proper cache busting
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
	 * Global Styles Registered:
	 * - campaignbridge-admin-base: Core admin styling and layout
	 * - Consistent color scheme and typography
	 * - Common UI components and form styling
	 * - Responsive design and layout utilities
	 *
	 * Design Features:
	 * - WordPress admin theme compatibility
	 * - Consistent spacing and typography
	 * - Responsive design for various screen sizes
	 * - Accessibility-compliant styling
	 * - Professional visual appearance
	 *
	 * Usage:
	 * - Automatically loaded on all CampaignBridge pages
	 * - Provides consistent visual foundation
	 * - Ensures professional appearance across all pages
	 *
	 * Performance Notes:
	 * - Styles are registered but not automatically enqueued
	 * - Conditional loading prevents unnecessary style processing
	 * - Version control ensures proper cache busting
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
	 * Page-Specific Scripts Registered:
	 * - campaignbridge-dashboard: Main dashboard functionality and overview
	 * - campaignbridge-post-types: Post type configuration and management
	 * - campaignbridge-settings: Plugin settings and provider configuration
	 * - campaignbridge-status: System status and debugging functionality
	 *
	 * Dependencies:
	 * - campaignbridge-admin-base: All page scripts depend on the global base
	 * - WordPress core scripts for admin functionality
	 * - Page-specific functionality and interactions
	 *
	 * Functionality by Page:
	 * - Dashboard: Overview widgets, quick actions, and statistics
	 * - Post Types: Dynamic form handling, validation, and UI interactions
	 * - Settings: Provider integration, API validation, and dynamic forms
	 * - Status: Real-time status updates, system checks, and debugging tools
	 *
	 * Performance Features:
	 * - Scripts are registered but not automatically enqueued
	 * - Conditional loading based on current page
	 * - Dependency management ensures proper loading order
	 * - Version control for cache busting and updates
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
	}

	/**
	 * Register page-specific CSS files for individual CampaignBridge admin pages.
	 *
	 * This method registers CSS files that provide styling specific to individual
	 * admin pages. Each stylesheet is designed to enhance the visual appearance
	 * and user experience of its respective page while maintaining consistency
	 * with the global design system.
	 *
	 * Page-Specific Styles Registered:
	 * - campaignbridge-dashboard: Dashboard layout and widget styling
	 * - campaignbridge-post-types: Post type configuration interface styling
	 * - campaignbridge-settings: Settings form and provider interface styling
	 * - campaignbridge-status: Status page layout and component styling
	 *
	 * Design Features:
	 * - Consistent with global design system
	 * - Page-specific layout optimizations
	 * - Enhanced form styling and interactions
	 * - Responsive design for various screen sizes
	 * - Accessibility-compliant styling
	 *
	 * Styling by Page:
	 * - Dashboard: Grid layouts, widget styling, and overview components
	 * - Post Types: Toggle switches, form layouts, and configuration UI
	 * - Settings: Provider selection, API validation, and form styling
	 * - Status: Status cards, progress indicators, and debugging UI
	 *
	 * Performance Features:
	 * - Styles are registered but not automatically enqueued
	 * - Conditional loading based on current page
	 * - Dependency management ensures proper loading order
	 * - Version control for cache busting and updates
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
		$screen = get_current_screen();
		if ( ! $screen || ! \CampaignBridge\Admin\PageUtils::is_campaignbridge_page( $screen->id ) ) {
			return;
		}

		// Always enqueue global assets on CampaignBridge pages.
		wp_enqueue_style( 'campaignbridge-admin-base' );
		wp_enqueue_script( 'campaignbridge-admin-base' );
	}
}
