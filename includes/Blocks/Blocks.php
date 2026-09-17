<?php
/**
 * Block System Manager for CampaignBridge.
 *
 * Handles automatic discovery and registration of CampaignBridge blocks
 * from the build directory with utilities for block validation.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName

namespace CampaignBridge\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Registers CampaignBridge block types.
 */
class Blocks {
	/**
	 * Build directory path relative to plugin root.
	 */
	private const BUILD_DIR = 'dist/blocks/';

	/**
	 * CampaignBridge block namespace prefix.
	 */
	private const BLOCK_NAMESPACE = 'campaignbridge/';

	// === INITIALIZATION ===

	/**
	 * Initialize the CampaignBridge block system.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		if ( did_action( 'init' ) ) {
			self::register();
		} else {
			\add_action( 'init', array( __CLASS__, 'register' ) );
		}

		\add_filter( 'block_categories_all', array( __CLASS__, 'register_email_category' ) );
	}

	/**
	 * Register the dedicated email block inserter category once.
	 *
	 * @param array<int, array<string, mixed>> $categories Existing block categories.
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_email_category( array $categories ): array {
		foreach ( $categories as $category ) {
			if ( 'campaignbridge-email' === ( $category['slug'] ?? null ) ) {
				return $categories;
			}
		}

		$categories[] = array(
			'slug'  => 'campaignbridge-email',
			'title' => __( 'CampaignBridge Email', 'campaignbridge' ),
		);

		return $categories;
	}

	/**
	 * Automatically discover and register all CampaignBridge blocks.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register(): void {
		$build_dir = self::get_build_directory();

		if ( ! is_dir( $build_dir ) ) {
			return;
		}

		$block_directories = self::get_block_directories( $build_dir );
		self::register_editor_scripts( $block_directories );
		self::register_blocks_from_directories( $block_directories );
	}

	/**
	 * Get the build directory path for blocks.
	 *
	 * @return string The full path to the blocks build directory.
	 */
	private static function get_build_directory(): string {
		$plugin_basename = \CampaignBridge_Plugin::basename();
		$plugin_dirname  = dirname( $plugin_basename );
		$installed_path  = '.' === $plugin_dirname
			? WP_PLUGIN_DIR
			: WP_PLUGIN_DIR . '/' . $plugin_dirname;

		return trailingslashit( $installed_path ) . self::BUILD_DIR;
	}

	/**
	 * Get all block directories from the build directory.
	 *
	 * @param string $build_dir The build directory path.
	 * @return array<string> Array of block directory paths.
	 */
	private static function get_block_directories( string $build_dir ): array {
		$directories = array();

		foreach ( scandir( $build_dir ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$block_path = $build_dir . $item;
			if ( is_dir( $block_path ) ) {
				$directories[] = $block_path;
			}
		}

		return $directories;
	}

	/**
	 * Register local editor scripts before WordPress processes block metadata.
	 *
	 * WordPress resolves block metadata paths with realpath(). In symlinked plugin
	 * installs that discards the installed plugin path, so its asset URL fallback
	 * can point at the active theme. Pre-registering the generated handles lets
	 * core reuse the canonical CampaignBridge plugin URLs instead.
	 *
	 * @param array<string> $block_directories Array of block directory paths.
	 * @return void
	 */
	private static function register_editor_scripts( array $block_directories ): void {
		foreach ( $block_directories as $block_directory ) {
			$metadata_file = trailingslashit( $block_directory ) . 'block.json';
			$metadata      = wp_json_file_decode( $metadata_file, array( 'associative' => true ) );

			if ( ! is_array( $metadata ) || empty( $metadata['name'] ) || ! is_string( $metadata['name'] ) ) {
				continue;
			}

			$block_name     = $metadata['name'];
			$editor_scripts = $metadata['editorScript'] ?? array();
			$editor_scripts = is_array( $editor_scripts ) ? $editor_scripts : array( $editor_scripts );

			foreach ( array_values( $editor_scripts ) as $index => $editor_script ) {
				if ( ! is_string( $editor_script ) ) {
					continue;
				}

				$script_path = remove_block_asset_path_prefix( $editor_script );
				if ( $script_path === $editor_script ) {
					continue;
				}

				$asset_path = trailingslashit( $block_directory )
					. substr_replace( $script_path, '.asset.php', -strlen( '.js' ) );
				/**
				 * Generated script dependency metadata.
				 *
				 * @var array{handle?: string, dependencies?: array<string>, version?: string|false|null} $asset
				 */
				$asset        = file_exists( $asset_path ) ? require $asset_path : array();
				$handle       = $asset['handle'] ?? generate_block_asset_handle( $block_name, 'editorScript', $index );
				$dependencies = array_values(
					array_filter(
						$asset['dependencies'] ?? array(),
						static fn( $dependency ): bool => is_string( $dependency ) && '' !== $dependency
					)
				);

				if ( '' === $handle || wp_script_is( $handle, 'registered' ) ) {
					continue;
				}

				$script_url = \CampaignBridge_Plugin::url()
					. self::BUILD_DIR
					. basename( $block_directory )
					. '/'
					. $script_path;

				wp_register_script(
					$handle,
					$script_url,
					$dependencies,
					$asset['version'] ?? ( $metadata['version'] ?? false ),
					false
				);

				if ( ! empty( $metadata['textdomain'] ) && in_array( 'wp-i18n', $dependencies, true ) ) {
					wp_set_script_translations( $handle, (string) $metadata['textdomain'] );
				}
			}
		}
	}

	/**
	 * Register blocks from directory paths.
	 *
	 * @param array<string> $block_directories Array of block directory paths.
	 * @return void
	 */
	private static function register_blocks_from_directories( array $block_directories ): void {
		foreach ( $block_directories as $block_location ) {
			register_block_type_from_metadata( $block_location );
		}
	}


	/**
	 * Check if a specific block is registered.
	 *
	 * @param string $block_name The block name to check.
	 * @return bool True if block is registered.
	 */
	public static function is_block_registered( string $block_name ): bool {
		return \WP_Block_Type_Registry::get_instance()->is_registered( $block_name );
	}

	/**
	 * Get all registered CampaignBridge blocks.
	 *
	 * @return array<string> Array of registered block names.
	 */
	public static function get_registered_blocks(): array {
		$blocks   = array();
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( $registry->get_all_registered() as $block_name => $block_type ) {
			if ( strpos( $block_type->name, self::BLOCK_NAMESPACE ) === 0 ) {
				$blocks[] = $block_name;
			}
		}

		return $blocks;
	}

	/**
	 * Check if blocks are built and available.
	 *
	 * @return bool True if blocks directory exists and contains blocks.
	 */
	public static function blocks_available(): bool {
		$build_dir = self::get_build_directory();

		if ( ! is_dir( $build_dir ) ) {
			return false;
		}

		$files = scandir( $build_dir );
		return is_array( $files ) && count( $files ) > 2; // More than just . and ..
	}
}
