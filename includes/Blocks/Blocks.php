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
		\add_action( 'init', array( __CLASS__, 'register' ) );
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
		self::register_blocks_from_directories( $block_directories );
	}

	/**
	 * Get the build directory path for blocks.
	 *
	 * @return string The full path to the blocks build directory.
	 */
	private static function get_build_directory(): string {
		return trailingslashit( \CampaignBridge_Plugin::path() ) . self::BUILD_DIR;
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
