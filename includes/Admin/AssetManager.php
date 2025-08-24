<?php
/**
 * Asset Manager for CampaignBridge Admin
 *
 * Provides methods to register global and page-specific scripts and styles.
 * Pages enqueue what they need from these registered assets.
 *
 * @package CampaignBridge
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
	 * Initialize the asset manager.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_global_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_page_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_global_assets' ) );
	}

	/**
	 * Register global scripts used across many pages.
	 *
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
	 * Register global styles used across many pages.
	 *
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
	 * Register page-specific scripts.
	 *
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
	 * Register page-specific styles.
	 *
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
	 * Enqueue global assets on CampaignBridge pages.
	 *
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
