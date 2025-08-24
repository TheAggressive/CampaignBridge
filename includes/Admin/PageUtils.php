<?php
/**
 * Page Utilities for CampaignBridge Admin Interface.
 *
 * This class provides utility functions for detecting, identifying, and managing
 * CampaignBridge admin pages throughout the WordPress admin interface. It serves
 * as a central location for page-related logic, making it easy to determine
 * which page is currently being viewed and perform page-specific operations.
 *
 * This class is designed to be stateless and thread-safe, making it suitable
 * for use throughout the plugin without initialization concerns.
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
 * Page Utilities: provides common page detection and management functions.
 */
class PageUtils {
	/**
	 * Check if the current admin screen is a CampaignBridge page.
	 *
	 * This method analyzes the WordPress admin screen ID to determine whether
	 * the current page is part of the CampaignBridge admin interface. It uses
	 * a systematic approach to identify both main menu and submenu pages.
	 *
	 * Page Detection Logic:
	 * - Main menu page: 'toplevel_page_campaignbridge'
	 * - Submenu pages: 'campaignbridge_page_{slug}' pattern
	 * - Extensible for future page types and custom post types
	 * - Handles both direct access and page transitions
	 *
	 * @since 0.1.0
	 * @param string $screen_id The WordPress admin screen ID to check.
	 * @return bool True if the current screen is a CampaignBridge page, false otherwise.
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
	 * Extract and return the current CampaignBridge admin page slug.
	 *
	 * This method analyzes the current WordPress admin screen to determine
	 * which specific CampaignBridge page is being viewed. It extracts the
	 * page identifier from the screen ID and returns a human-readable slug
	 * for use in conditional logic and page-specific functionality.
	 *
	 * @since 0.1.0
	 * @return string|null The current page slug (e.g., 'dashboard', 'settings') or null if not a CampaignBridge page.
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
