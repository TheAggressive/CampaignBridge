<?php
/**
 * Auto-register built blocks from the plugin's dist directory.
 *
 * @package CampaignBridge
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
	 * Initialize the blocks system.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
	}



	/**
	 * Register block types used by CampaignBridge.
	 *
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
			if ( strpos( $block_name, 'campaignbridge/' ) === 0 ) {
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
