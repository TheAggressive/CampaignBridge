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
		if ( ! $screen || ! self::is_campaignbridge_page( $screen->id ) ) {
			return;
		}

		self::enqueue_admin_styles();
		self::enqueue_admin_scripts();
	}

	/**
	 * Check if current screen is a CampaignBridge page.
	 *
	 * @param string $screen_id Current screen ID.
	 * @return bool True if CampaignBridge page.
	 */
	private static function is_campaignbridge_page( string $screen_id ): bool {
		// Main menu page.
		if ( 'toplevel_page_campaignbridge' === $screen_id ) {
			return true;
		}

		// Any submenu page starting with 'campaignbridge_page_'
		if ( strpos( $screen_id, 'campaignbridge_page_' ) === 0 ) {
			return true;
		}

		// Future: Add any other page patterns your plugin might use.
		// For example: custom post types, custom taxonomies, etc.

		return false;
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @return void
	 */
	private static function enqueue_admin_styles(): void {
		wp_enqueue_style(
			'campaignbridge-admin',
			CB_URL . 'dist/styles/styles.css',
			array(),
			CB_VERSION
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @return void
	 */
	private static function enqueue_admin_scripts(): void {
		wp_enqueue_script(
			'campaignbridge-admin',
			CB_URL . 'dist/scripts/templates.js',
			array( 'jquery' ),
			CB_VERSION,
			true
		);
	}
}
