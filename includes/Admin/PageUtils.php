<?php
/**
 * Page Utilities for CampaignBridge Admin Interface.
 *
 * This class provides utility functions for detecting, identifying, and managing
 * CampaignBridge admin pages throughout the WordPress admin interface. It serves
 * as a central location for page-related logic, making it easy to determine
 * which page is currently being viewed and perform page-specific operations.
 *
 * Key Features:
 * - Page detection and identification for CampaignBridge admin pages
 * - Current page slug extraction and validation
 * - Page-specific conditional logic support
 * - Future-proof design for additional page types
 * - Consistent page detection across the entire plugin
 *
 * Page Detection Logic:
 * - Main menu page: 'toplevel_page_campaignbridge'
 * - Submenu pages: 'campaignbridge_page_{slug}' pattern
 * - Extensible for custom post types and taxonomies
 * - Handles both current and specific page queries
 *
 * Usage Examples:
 * - Check if current page is any CampaignBridge page
 * - Get current page slug for conditional logic
 * - Determine if specific functionality should be enabled
 * - Route users to appropriate sections
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
	 * Screen ID Patterns:
	 * - toplevel_page_campaignbridge: Main dashboard page
	 * - campaignbridge_page_post-types: Post types configuration
	 * - campaignbridge_page_settings: Plugin settings page
	 * - campaignbridge_page_status: System status page
	 * - Future: Custom post types, taxonomies, etc.
	 *
	 * Usage Examples:
	 * - Conditional asset loading on specific pages
	 * - Page-specific functionality activation
	 * - Context-aware admin interface customization
	 * - Performance optimization for page-specific features
	 *
	 * Performance Notes:
	 * - Lightweight string comparison operations
	 * - No database queries or external API calls
	 * - Fast execution for real-time page detection
	 * - Memory-efficient for frequent calls
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
	 * Page Slug Mapping:
	 * - 'toplevel_page_campaignbridge' → 'dashboard'
	 * - 'campaignbridge_page_post-types' → 'post-types'
	 * - 'campaignbridge_page_settings' → 'settings'
	 * - 'campaignbridge_page_status' → 'status'
	 * - Future patterns will be automatically detected
	 *
	 * Return Values:
	 * - 'dashboard': Main CampaignBridge dashboard page
	 * - 'post-types': Post type configuration page
	 * - 'settings': Plugin settings and provider configuration
	 * - 'status': System status and debugging page
	 * - null: Not on a CampaignBridge page
	 *
	 * Usage Examples:
	 * - Conditional asset loading based on current page
	 * - Page-specific functionality activation
	 * - Context-aware admin interface customization
	 * - Navigation and breadcrumb generation
	 * - Performance optimization for page-specific features
	 *
	 * Error Handling:
	 * - Returns null if not on a CampaignBridge page
	 * - Handles missing or invalid screen objects gracefully
	 * - Provides safe fallback for edge cases
	 * - Maintains consistent return type expectations
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
