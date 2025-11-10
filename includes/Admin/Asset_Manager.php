<?php // phpcs:disable CampaignBridge.Standard.Sniffs.Assets.AssetEnqueue.DirectAssetEnqueue -- Internal Asset_Manager method doing the actual enqueuing work.

/**
 * Asset Manager - Centralized asset loading with dependency and version management
 *
 * Provides comprehensive asset loading utilities for WordPress plugins using the
 * standard WordPress build system with .asset.php files for dependency management
 * and cache busting.
 *
 * @package CampaignBridge\Admin
 */

namespace CampaignBridge\Admin;

/**
 * Asset Manager Class
 *
 * Handles automatic discovery and enqueuing of CSS and JS assets with proper
 * dependency management and version hashing.
 */
class Asset_Manager {
	/**
	 * Enqueue all assets found in a directory.
	 *
	 * Automatically discovers and enqueues all CSS/JS assets with their
	 * corresponding .asset.php files found in the specified directory.
	 *
	 * @param string $directory_path Relative path from plugin root (e.g., 'dist/styles/admin').
	 * @return void
	 */
	public static function enqueue_assets( string $directory_path ): void {
		$plugin_path = \CampaignBridge_Plugin::path();
		$plugin_url  = \CampaignBridge_Plugin::url();
		$full_dir    = $plugin_path . $directory_path;

		if ( ! is_dir( $full_dir ) ) {
			return;
		}

		// Find all .asset.php files in the directory (exclude RTL versions).
		$asset_files = glob( $full_dir . '/*.asset.php' );

		if ( ! is_array( $asset_files ) ) {
			return;
		}

		foreach ( $asset_files as $asset_file ) {
			$asset_name = basename( $asset_file, '.asset.php' );

			// Skip RTL versions - WordPress loads them automatically when needed.
			if ( strpos( $asset_name, '-rtl' ) !== false ) {
				continue;
			}

			$asset_path = $directory_path . '/' . $asset_name . '.asset.php';

			// Determine if it's a style or script based on directory path.
			if ( strpos( $directory_path, '/styles/' ) !== false ) {
				self::enqueue_asset_style_internal( $asset_name, $asset_path );
			} elseif ( strpos( $directory_path, '/scripts/' ) !== false ) {
				self::enqueue_asset_script_internal( $asset_name, $asset_path );
			}
		}
	}

	/**
	 * Enqueue a single asset with its dependencies and version.
	 *
	 * @param string $handle     Asset handle for WordPress.
	 * @param string $asset_path Relative path to .asset.php file from plugin root.
	 * @return void
	 */
	public static function enqueue_asset( string $handle, string $asset_path ): void {
		// Determine asset type from the asset path.
		if ( strpos( $asset_path, '/styles/' ) !== false ) {
			self::enqueue_asset_style_internal( $handle, $asset_path );
		} elseif ( strpos( $asset_path, '/scripts/' ) !== false ) {
			self::enqueue_asset_script_internal( $handle, $asset_path );
		}
	}

	/**
	 * Enqueue a style asset (public wrapper for private method).
	 *
	 * @param string               $handle     Asset handle.
	 * @param string               $asset_path Asset path.
	 * @param array<string, mixed> $asset_data Asset data override.
	 * @return void
	 */
	public static function enqueue_asset_style( string $handle, string $asset_path, array $asset_data = array() ): void {
		self::enqueue_asset_style_internal( $handle, $asset_path, $asset_data );
	}

	/**
	 * Enqueue a script asset (public wrapper for private method).
	 *
	 * @param string               $handle     Asset handle.
	 * @param string               $asset_path Asset path.
	 * @param array<string, mixed> $asset_data Asset data override.
	 * @return void
	 */
	public static function enqueue_asset_script( string $handle, string $asset_path, array $asset_data = array() ): void {
		self::enqueue_asset_script_internal( $handle, $asset_path, $asset_data );
	}

	/**
	 * Enqueue assets from a configuration array.
	 *
	 * @param array<string, mixed> $config Configuration array with 'styles' and 'scripts' keys.
	 * @return void
	 */
	public static function enqueue_from_config( array $config ): void {
		// Enqueue styles.
		if ( isset( $config['styles'] ) && is_array( $config['styles'] ) ) {
			foreach ( $config['styles'] as $handle => $asset_path ) {
				self::enqueue_asset_style_internal( $handle, $asset_path );
			}
		}

		// Enqueue scripts.
		if ( isset( $config['scripts'] ) && is_array( $config['scripts'] ) ) {
			foreach ( $config['scripts'] as $handle => $asset_config ) {
				$asset_path = is_array( $asset_config ) ? $asset_config['src'] : $asset_config;
				self::enqueue_asset_script_internal( $handle, $asset_path );
			}
		}
	}

	/**
	 * Enqueue a style asset with dependency and version management (internal method).
	 *
	 * @param string               $handle     Asset handle.
	 * @param string               $asset_path Relative path to asset file or .asset.php file.
	 * @param array<string, mixed> $asset_data Optional asset data override.
	 * @return void
	 */
	private static function enqueue_asset_style_internal( string $handle, string $asset_path, array $asset_data = array() ): void {
		$asset_info = self::prepare_asset_enqueue( $asset_path, $asset_data, '.css' );

		\wp_enqueue_style(
			$handle,
			$asset_info['url'],
			$asset_info['dependencies'],
			$asset_info['version']
		);
	}

	/**
	 * Enqueue a script asset with dependency and version management (internal method).
	 *
	 * @param string               $handle     Asset handle.
	 * @param string               $asset_path Relative path to asset file or .asset.php file.
	 * @param array<string, mixed> $asset_data Optional asset data override.
	 * @return void
	 */
	private static function enqueue_asset_script_internal( string $handle, string $asset_path, array $asset_data = array() ): void {
		$asset_info = self::prepare_asset_enqueue( $asset_path, $asset_data, '.js' );

				\wp_enqueue_script(
					$handle,
					$asset_info['url'],
					$asset_info['dependencies'],
					$asset_info['version'],
					$asset_info['in_footer'] ?? true
				);
	}

	/**
	 * Prepare asset information for enqueuing (shared logic for styles and scripts).
	 *
	 * @param string               $asset_path      Asset path.
	 * @param array<string, mixed> $asset_data      Optional asset data override.
	 * @param string               $file_extension  File extension (.css or .js).
	 * @return array<string, mixed> Prepared asset information.
	 */
	private static function prepare_asset_enqueue( string $asset_path, array $asset_data, string $file_extension ): array {
		$plugin_url = \CampaignBridge_Plugin::url();

		// If asset_data is provided, it means we're dealing with a .asset.php file.
		if ( ! empty( $asset_data ) ) {
			$asset_path = str_replace( '.asset.php', $file_extension, $asset_path );
			return array(
				'url'          => $plugin_url . $asset_path,
				'dependencies' => $asset_data['dependencies'],
				'version'      => $asset_data['version'],
				'in_footer'    => $asset_data['in_footer'] ?? null,
			);
		}

		// Check if this is a direct file path.
		if ( strpos( $asset_path, $file_extension ) !== false ) {
			return array(
				'url'          => $plugin_url . $asset_path,
				'dependencies' => array(),
				'version'      => \CampaignBridge_Plugin::VERSION,
				'in_footer'    => ( '.js' === $file_extension ) ? true : null,
			);
		}

		// Load asset data from .asset.php file.
		$loaded_asset_data = self::load_asset_data( $asset_path );
		$asset_path        = str_replace( '.asset.php', $file_extension, $asset_path );

		return array(
			'url'          => $plugin_url . $asset_path,
			'dependencies' => $loaded_asset_data['dependencies'],
			'version'      => $loaded_asset_data['version'],
			'in_footer'    => ( '.js' === $file_extension ) ? true : null,
		);
	}

	/**
	 * Load asset data from .asset.php file with fallback (public static version).
	 *
	 * @param string $asset_path Relative path to .asset.php file.
	 * @return array<string, mixed>|null Asset data with 'dependencies' and 'version' keys, or null if file doesn't exist.
	 */
	public static function load_asset_data_static( string $asset_path ): ?array {
		// Only load .asset.php files to avoid including non-PHP files.
		if ( ! str_ends_with( $asset_path, '.asset.php' ) ) {
			return null;
		}

		$plugin_path = \CampaignBridge_Plugin::path();
		$full_path   = $plugin_path . $asset_path;

		if ( file_exists( $full_path ) ) {
			$asset_data = include $full_path;
			return array(
				'dependencies' => $asset_data['dependencies'] ?? array(),
				'version'      => $asset_data['version'] ?? \CampaignBridge_Plugin::VERSION,
			);
		}

		return null;
	}

	/**
	 * Load asset data from .asset.php file with fallback.
	 *
	 * @param string $asset_path Relative path to .asset.php file.
	 * @return array<string, mixed> Asset data with 'dependencies' and 'version' keys.
	 */
	private static function load_asset_data( string $asset_path ): array {
		$asset_data = self::load_asset_data_static( $asset_path );

		if ( $asset_data ) {
			return $asset_data;
		}

		// Fallback if asset file doesn't exist.
		return array(
			'dependencies' => array(),
			'version'      => \CampaignBridge_Plugin::VERSION,
		);
	}
}
