<?php
/**
 * Asset Manager for CampaignBridge Admin Interface.
 *
 * Handles registration and enqueuing of CSS and JavaScript assets
 * for the CampaignBridge admin interface with proper dependency management.
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

	// === CONSTANTS ===

	/**
	 * Asset handles.
	 */
	private const GLOBAL_STYLES_HANDLE       = 'campaignbridge-styles';
	private const POST_TYPES_STYLES_HANDLE   = 'campaignbridge-post-types';
	private const STATUS_STYLES_HANDLE       = 'campaignbridge-status';
	private const BLOCK_EDITOR_STYLES_HANDLE = 'campaignbridge-block-editor-style';
	private const BLOCK_EDITOR_SCRIPT_HANDLE = 'campaignbridge-block-editor-script';

	/**
	 * File paths relative to plugin root.
	 */
	private const GLOBAL_STYLES_PATH       = 'dist/styles/styles.css';
	private const POST_TYPES_STYLES_PATH   = 'dist/styles/pages/post-types.css';
	private const STATUS_STYLES_PATH       = 'dist/styles/pages/status.css';
	private const BLOCK_EDITOR_STYLES_PATH = 'dist/styles/pages/editor.css';
	private const BLOCK_EDITOR_SCRIPT_PATH = 'dist/scripts/template-editor/editor.js';

	/**
	 * WordPress dependencies for block editor styles.
	 */
	private const BLOCK_EDITOR_STYLE_DEPS = array(
		'wp-block-editor',
		'wp-edit-post',
	);

	// === INITIALIZATION ===

	/**
	 * Initialize the asset manager.
	 *
	 * Registers WordPress hooks for asset registration and enqueuing.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_global_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_page_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_global_assets' ) );
	}

	// === ASSET REGISTRATION ===

	/**
	 * Register global JavaScript files.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register_global_scripts(): void {
		// Global scripts can be added here when needed.
	}

	/**
	 * Register global CSS files.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register_global_styles(): void {
		wp_register_style(
			self::GLOBAL_STYLES_HANDLE,
			CB_URL . self::GLOBAL_STYLES_PATH,
			array(),
			CB_VERSION
		);
	}

	/**
	 * Register page-specific JavaScript files.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register_scripts(): void {
		// Register template editor page script using generated asset metadata.
		self::register_script_with_asset(
			self::BLOCK_EDITOR_SCRIPT_HANDLE,
			self::BLOCK_EDITOR_SCRIPT_PATH
		);
	}

	// === HELPER METHODS ===

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
		try {
			$plugin_dir       = CB_PATH;
			$script_file_path = $plugin_dir . '/' . $script_rel_path;

			// Validate script file exists.
			if ( ! file_exists( $script_file_path ) ) {
				return array(
					'dependencies' => array(),
					'version'      => null,
				);
			}

			$asset_rel_path  = preg_replace( '/\.js$/', '.asset.php', $script_rel_path );
			$asset_file_path = $plugin_dir . '/' . $asset_rel_path;

			$dependencies = array();
			$version      = defined( 'CB_VERSION' ) ? CB_VERSION : null;

			// Try to load asset metadata file.
			if ( is_string( $asset_rel_path ) && file_exists( $asset_file_path ) ) {
				$asset = require $asset_file_path;

				if ( is_array( $asset ) ) {
					$dependencies = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] )
						? $asset['dependencies']
						: array();

					$version = isset( $asset['version'] )
						? (string) $asset['version']
						: $version;
				}
			} else {
				// Fallback to file modification time.
				$version = (string) filemtime( $script_file_path );
			}

			return array(
				'dependencies' => $dependencies,
				'version'      => $version,
			);
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
				error_log(
					sprintf(
						'[CampaignBridge] Error loading asset info for %s: %s',
						$script_rel_path,
						$e->getMessage()
					)
				);
			}

			return array(
				'dependencies' => array(),
				'version'      => null,
			);
		}
	}

	/**
	 * Register page-specific CSS files.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register_styles(): void {
		$errors = array();

		// Register global styles.
		if ( ! self::register_style( self::GLOBAL_STYLES_HANDLE, self::GLOBAL_STYLES_PATH ) ) {
			$errors[] = 'global styles';
		}

		// Register page-specific styles.
		if ( ! self::register_style( self::POST_TYPES_STYLES_HANDLE, self::POST_TYPES_STYLES_PATH ) ) {
			$errors[] = 'post types styles';
		}
		if ( ! self::register_style( self::STATUS_STYLES_HANDLE, self::STATUS_STYLES_PATH ) ) {
			$errors[] = 'status styles';
		}

		// Register block editor style with dependencies.
		try {
			wp_register_style(
				self::BLOCK_EDITOR_STYLES_HANDLE,
				CB_URL . self::BLOCK_EDITOR_STYLES_PATH,
				self::BLOCK_EDITOR_STYLE_DEPS,
				CB_VERSION
			);
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
				error_log(
					sprintf(
						'[CampaignBridge] Error registering block editor style: %s',
						$e->getMessage()
					)
				);
			}
			$errors[] = 'block editor styles';
		}

		// Log errors if any occurred.
		if ( ! empty( $errors ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'[CampaignBridge] Some styles failed to register: %s',
					implode( ', ', $errors )
				)
			);
		}
	}

	/**
	 * Helper method to register a style with default parameters.
	 *
	 * @param string $handle The style handle.
	 * @param string $path The file path relative to plugin root.
	 * @return void
	 */
	private static function register_style( string $handle, string $path ): void {
		try {
			$file_path = CB_URL . $path;

			if ( ! file_exists( CB_PATH . $path ) ) {
				if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
					error_log(
						sprintf(
							'[CampaignBridge] Style file not found: %s',
							CB_PATH . $path
						)
					);
				}
				return;
			}

			wp_register_style(
				$handle,
				$file_path,
				array(),
				CB_VERSION
			);
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
				error_log(
					sprintf(
						'[CampaignBridge] Error registering style %s: %s',
						$handle,
						$e->getMessage()
					)
				);
			}
		}
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

	// === ASSET ENQUEUING ===

	/**
	 * Enqueue global assets on CampaignBridge admin pages.
	 *
	 * Hooked into WordPress's admin_enqueue_scripts action.
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
		wp_enqueue_style( self::GLOBAL_STYLES_HANDLE );
	}
}
