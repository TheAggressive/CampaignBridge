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

		// Register template manager page script using generated asset metadata.
		self::register_script_with_asset(
			'campaignbridge-block-editor-script',
			'dist/scripts/template-editor/editor.js',
		);
	}

	/**
	 * Helper: Register a script using its generated *.asset.php for deps/version.
	 *
	 * @param string $handle            Script handle.
	 * @param string $script_rel_path   Script path relative to plugin root.
	 * @param array  $extra_deps        Additional dependencies to merge (optional).
	 * @param bool   $in_footer         Whether to load in footer.
	 * @return void
	 */
	private static function register_script_with_asset( string $handle, string $script_rel_path, array $extra_deps = array(), bool $in_footer = true ): void {
		$asset = self::get_asset_info( $script_rel_path );
		$deps  = array_values( array_unique( array_merge( $asset['dependencies'], $extra_deps ) ) );

		wp_register_script(
			$handle,
			CB_URL . $script_rel_path,
			$deps,
			$asset['version'],
			$in_footer
		);
	}

	/**
	 * Helper: Read WP build asset info (dependencies, version) for a built script.
	 *
	 * Expects an accompanying file with the same basename and `.asset.php`,
	 * e.g. `editor.js` → `editor.asset.php` in the same directory.
	 * Falls back to filemtime for version and empty dependencies when missing.
	 *
	 * @param string $script_rel_path   Script path relative to plugin root.
	 * @return array{dependencies: array, version: string|null}
	 */
	private static function get_asset_info( string $script_rel_path ): array {
		$plugin_dir       = dirname( __DIR__, 2 ); // plugin root
		$script_file_path = $plugin_dir . '/' . $script_rel_path;
		$asset_rel_path   = preg_replace( '/\.js$/', '.asset.php', $script_rel_path );
		$asset_file_path  = $plugin_dir . '/' . $asset_rel_path;

		$dependencies = array();
		$version      = defined( 'CB_VERSION' ) ? CB_VERSION : null;

		if ( is_string( $asset_rel_path ) && file_exists( $asset_file_path ) ) {
			$asset = require $asset_file_path;
			if ( is_array( $asset ) ) {
				$dependencies = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array();
				$version      = isset( $asset['version'] ) ? (string) $asset['version'] : $version;
			}
		} elseif ( file_exists( $script_file_path ) ) {
			$version = (string) filemtime( $script_file_path );
		}

		return array(
			'dependencies' => $dependencies,
			'version'      => $version,
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
}
