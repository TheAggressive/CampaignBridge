<?php
/**
 * Asset Manager for CampaignBridge Admin
 *
 * Handles enqueuing of scripts and styles for admin pages.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset Manager: handles script and style enqueuing for admin pages.
 */
class AssetManager {
	/**
	 * Enqueue admin scripts and styles on the plugin page.
	 *
	 * @return void
	 */
	public static function enqueue_admin_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! in_array( $screen->id, array( 'toplevel_page_campaignbridge', 'campaignbridge_page_campaignbridge-settings' ), true ) ) {
			return;
		}

		self::enqueue_admin_styles();
		self::enqueue_admin_scripts();
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @return void
	 */
	private static function enqueue_admin_styles(): void {
		$style_version    = '1.0.0';
		$style_asset_path = CB_PATH . 'dist/styles/styles.asset.php';

		if ( file_exists( $style_asset_path ) ) {
			$maybe = include $style_asset_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			if ( is_array( $maybe ) && isset( $maybe['version'] ) ) {
				$style_version = (string) $maybe['version'];
			}
		}

		wp_enqueue_style(
			'campaignbridge-admin',
			CB_URL . 'dist/styles/styles.css',
			array(),
			$style_version
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @return void
	 */
	private static function enqueue_admin_scripts(): void {
		// Currently no scripts are enqueued, but this method is ready for future use.

		// Example of how to enqueue scripts:
		/*
		$script_version    = '1.0.0';
		$script_asset_path = CB_PATH . 'dist/scripts/admin.asset.php';

		if ( file_exists( $script_asset_path ) ) {
			$maybe = include $script_asset_path;
			if ( is_array( $maybe ) && isset( $maybe['version'] ) ) {
				$script_version = (string) $maybe['version'];
			}
		}

		wp_enqueue_script(
			'campaignbridge-admin',
			CB_URL . 'dist/scripts/admin.js',
			array( 'jquery' ),
			$script_version,
			true
		);
		*/
	}
}
