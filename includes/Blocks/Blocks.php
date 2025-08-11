<?php
/**
 * Auto-register built blocks from the plugin's dist directory.
 *
 * @package CampaignBridge
 */

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName

namespace CampaignBridge\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Registers CampaignBridge block types.
 */
class Blocks {
	/**
	 * Register block types used by CampaignBridge.
	 *
	 * @return void
	 */
	public static function register(): void {
		$build_dir = plugin_dir_path( __FILE__ ) . '/dist/blocks';

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
}
