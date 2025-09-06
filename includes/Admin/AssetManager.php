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
		add_action( 'admin_init', array( __CLASS__, 'add_security_headers' ) );
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
		// wp_register_script(
		// 'campaignbridge-admin-base',
		// CB_URL . 'dist/scripts/admin-base.js',
		// array(),
		// CB_VERSION,
		// true
		// );
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
			'campaignbridge-styles',
			CB_URL . 'dist/styles/styles.css',
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

		// Register post types page script.
		// wp_register_script(
		// 'campaignbridge-post-types',
		// CB_URL . 'dist/scripts/template-editor/editor.js',
		// array( 'campaignbridge-editor' ),
		// CB_VERSION,
		// true
		// );

		// Register template manager page script.
		wp_register_script(
			'campaignbridge-block-editor-script',
			CB_URL . 'dist/scripts/template-editor/editor.js',
			array(
				'wp-block-editor',
				'wp-edit-post',
				'wp-components',
				'wp-element',
				'wp-data',
				'wp-core-data',
				'wp-blocks',
				'wp-keycodes',
				'wp-i18n',
				'wp-compose',
				'wp-primitives',
				'wp-format-library',
				'wp-block-library',
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

		// Register global style.
		wp_register_style(
			'campaignbridge-styles',
			CB_URL . 'dist/styles/styles.css',
			array(),
			CB_VERSION
		);

		// Register post types page style.
		wp_register_style(
			'campaignbridge-post-types',
			CB_URL . 'dist/styles/pages/post-types.css',
			array(),
			CB_VERSION
		);

		// Register status page style.
		wp_register_style(
			'campaignbridge-status',
			CB_URL . 'dist/styles/pages/status.css',
			array(),
			CB_VERSION
		);

		// Register block editor style for standalone editor.
		wp_register_style(
			'campaignbridge-block-editor-style',
			CB_URL . 'dist/styles/pages/editor.css',
			array(
				'wp-block-editor',
				'wp-edit-post',
			),
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
		wp_enqueue_style( 'campaignbridge-styles' );
	}

	/**
	 * Add security headers for CampaignBridge admin pages.
	 *
	 * This method adds Content Security Policy headers to CampaignBridge admin pages
	 * to provide additional security against XSS and other client-side attacks.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function add_security_headers(): void {
		// Only add headers on CampaignBridge pages to avoid interfering with other plugins.
		if ( ! \CampaignBridge\Admin\PageUtils::is_campaignbridge_page( get_current_screen()?->id ?? '' ) ) {
			return;
		}

		// Content Security Policy for admin pages.
		$csp = array(
			"default-src 'self'",
			"script-src 'self' 'unsafe-inline' 'unsafe-eval' https://*.wordpress.org https://*.wp.com",
			"style-src 'self' 'unsafe-inline' https://*.wordpress.org https://*.wp.com https://fonts.googleapis.com",
			"font-src 'self' https://fonts.gstatic.com https://*.wordpress.org",
			"img-src 'self' data: https: blob:",
			"connect-src 'self' https://*.wordpress.org https://*.wp.com",
			"frame-src 'self'",
			"object-src 'none'",
			"base-uri 'self'",
			"form-action 'self'",
		);

		$csp_header = 'Content-Security-Policy: ' . implode( '; ', $csp );

		// Add CSP header if not already set.
		if ( ! headers_sent() && ! self::has_csp_header() ) {
			header( $csp_header );
		}
	}

	/**
	 * Check if CSP header is already set.
	 *
	 * @return bool True if CSP header exists.
	 */
	private static function has_csp_header(): bool {
		$headers = headers_list();
		foreach ( $headers as $header ) {
			if ( stripos( $header, 'Content-Security-Policy:' ) === 0 ) {
				return true;
			}
		}
		return false;
	}
}
