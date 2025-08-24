<?php
/**
 * Page Utilities for CampaignBridge Admin
 *
 * Provides utility functions for detecting and managing admin pages.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page Utilities: provides common page detection and management functions.
 */
class PageUtils {
	/**
	 * Check if current screen is a CampaignBridge page.
	 *
	 * @param string $screen_id Current screen ID.
	 * @return bool True if CampaignBridge page.
	 */
	public static function is_campaignbridge_page( string $screen_id ): bool {
		// Main menu page.
		if ( 'toplevel_page_campaignbridge' === $screen_id ) {
			return true;
		}

		// Any submenu page starting with 'campaignbridge_page_'.
		if ( strpos( $screen_id, 'campaignbridge_page_' ) === 0 ) {
			return true;
		}

		// Future: Add any other page patterns your plugin might use.
		// For example: custom post types, custom taxonomies, etc.

		return false;
	}

	/**
	 * Get the current admin page slug.
	 *
	 * @return string|null The current page slug or null if not a CampaignBridge page.
	 */
	public static function get_current_page_slug(): ?string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! self::is_campaignbridge_page( $screen->id ) ) {
			return null;
		}

		// Extract the page slug from the screen ID
		if ( 'toplevel_page_campaignbridge' === $screen->id ) {
			return 'dashboard';
		}

		if ( strpos( $screen->id, 'campaignbridge_page_' ) === 0 ) {
			return str_replace( 'campaignbridge_page_', '', $screen->id );
		}

		return null;
	}

	/**
	 * Check if current page is a specific CampaignBridge page.
	 *
	 * @param string $page_slug The page slug to check.
	 * @return bool True if current page matches the slug.
	 */
	public static function is_current_page( string $page_slug ): bool {
		$current_slug = self::get_current_page_slug();
		return $current_slug === $page_slug;
	}
}
