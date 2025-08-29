<?php
/**
 * Block System Manager for CampaignBridge.
 *
 * This class manages the registration, discovery, and management of all
 * CampaignBridge blocks used for email template creation and content
 * management. It automatically discovers and registers blocks from the
 * plugin's build directory and provides utilities for block validation
 * and status checking.
 *
 * This class is essential for the block-based email template system
 * and provides the foundation for visual email campaign creation.
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
	 * Initialize the CampaignBridge block system and register all necessary hooks.
	 *
	 * This method sets up the complete block management system by registering
	 * WordPress hooks for block registration, editor assets, and frontend
	 * asset management. It ensures that all blocks are properly discovered,
	 * registered, and available for use in the block editor.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Automatically discover and register all CampaignBridge blocks from the build directory.
	 *
	 * This method scans the plugin's dist/blocks directory to discover all available
	 * blocks and automatically registers them with WordPress using the block
	 * registration system. It provides a dynamic and maintainable approach to
	 * block management without manual registration requirements.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register(): void {
		$plugin_root_dir = CB_PATH;
		$build_dir       = trailingslashit( $plugin_root_dir ) . 'dist/blocks/';

		if ( ! is_dir( $build_dir ) ) {
			return;
		}

		foreach ( scandir( $build_dir ) as $result ) {
			if ( '.' === $result || '..' === $result ) {
				continue;
			}

			$block_location = $build_dir . $result;

			if ( is_dir( $block_location ) ) {
				register_block_type( $block_location );
			}
		}
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @return void
	 */
	public static function enqueue_editor_assets(): void {
		// Enqueue any additional editor-specific assets here
		// This could include custom block styles, editor scripts, etc.
	}

	/**
	 * Enqueue frontend assets for blocks.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_assets(): void {
		// Enqueue any frontend assets needed for blocks
		// This could include block styles, frontend scripts, etc.
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
	 * @return array Array of registered block names.
	 */
	public static function get_registered_blocks(): array {
		$blocks   = array();
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( $registry->get_all_registered() as $block_name => $block_type ) {
			if ( strpos( $block_type->name, 'campaignbridge/' ) === 0 ) {
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
		$build_dir = trailingslashit( CB_PATH ) . 'dist/blocks/';
		if ( ! is_dir( $build_dir ) ) {
			return false;
		}

		$files = scandir( $build_dir );
		return is_array( $files ) && count( $files ) > 2; // More than just . and ..
	}
}
