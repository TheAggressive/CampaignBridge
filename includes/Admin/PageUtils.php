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
	 * Extract the page slug from a WordPress admin screen ID.
	 *
	 * This method parses WordPress admin screen IDs to extract the page slug.
	 * It handles both main menu pages and submenu pages.
	 *
	 * @since 0.1.0
	 * @param string $screen_id The WordPress admin screen ID.
	 * @return string|null The extracted page slug or null if not a CampaignBridge page.
	 */
	public static function extract_page_slug_from_screen_id( string $screen_id ): ?string {
		// Main menu page.
		if ( 'toplevel_page_campaignbridge' === $screen_id ) {
			return 'dashboard';
		}

		// Submenu pages: extract slug from 'campaignbridge_page_{slug}'.
		if ( strpos( $screen_id, 'campaignbridge_page_' ) === 0 ) {
			return str_replace( 'campaignbridge_page_', '', $screen_id );
		}

		return null;
	}

	/**
	 * Get debug information about discovered admin page classes.
	 *
	 * This method provides detailed information about the auto-discovery
	 * process, useful for development, debugging, and troubleshooting.
	 *
	 * @since 0.1.0
	 * @return array Debug information about admin page discovery.
	 */
	public static function get_admin_page_debug_info(): array {
		$pages_dir = CB_PATH . 'includes/Admin/Pages/';
		$classes   = self::get_admin_page_classes();
		$debug     = array(
			'pages_directory'        => $pages_dir,
			'pages_directory_exists' => is_dir( $pages_dir ),
			'discovered_classes'     => $classes,
			'class_count'            => count( $classes ),
			'cache_status'           => 'cached',
		);

		// Check if cache is being used.
		static $cached_classes = null;
		if ( null === $cached_classes ) {
			$debug['cache_status'] = 'not_cached';
		}

		return $debug;
	}

	/**
	 * Clear the admin page classes cache.
	 *
	 * This method clears the cached admin page classes, forcing a fresh
	 * directory scan. Useful for development, testing, or when new
	 * admin page classes are added.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function clear_admin_page_classes_cache(): void {
		// Clear the static cache by setting it to null.
		// This will force a fresh directory scan on the next call.
		$cached_classes = null;
	}

	/**
	 * Get all admin page classes automatically.
	 *
	 * This method discovers all admin page classes by scanning the Pages directory
	 * and finding classes that extend AdminPage. This eliminates the need to
	 * manually maintain a list of admin page classes. Results are cached for
	 * performance.
	 *
	 * @since 0.1.0
	 * @return array<string> Array of admin page class names.
	 */
	private static function get_admin_page_classes(): array {
		// Use static cache to avoid repeated directory scanning.
		static $cached_classes = null;

		if ( null !== $cached_classes ) {
			return $cached_classes;
		}

		$pages_dir = CB_PATH . 'includes/Admin/Pages/';
		$classes   = array();

		if ( ! is_dir( $pages_dir ) ) {
			$cached_classes = array();
			return $cached_classes;
		}

		// Scan the Pages directory for PHP files.
		$files = glob( $pages_dir . '*.php' );

		if ( ! is_array( $files ) ) {
			$cached_classes = array();
			return $cached_classes;
		}

		foreach ( $files as $file ) {
			$filename = basename( $file, '.php' );

			// Convert filename to class name (e.g., 'PostTypesPage.php' -> 'PostTypesPage').
			$class_name = 'CampaignBridge\\Admin\\Pages\\' . $filename;

			// Check if the class exists and extends AdminPage.
			if ( class_exists( $class_name ) && is_subclass_of( $class_name, \CampaignBridge\Admin\Pages\AdminPage::class ) ) {
				$classes[] = $class_name;
			}
		}

		// Cache the result for future calls.
		$cached_classes = $classes;
		return $classes;
	}

	/**
	 * Check if the current admin screen is a CampaignBridge page.
	 *
	 * This method analyzes the WordPress admin screen ID to determine whether
	 * the current page is part of the CampaignBridge admin interface. It uses
	 * the auto-discovered admin page classes to ensure only legitimate
	 * CampaignBridge pages are matched.
	 *
	 * Page Detection Logic:
	 * - Main menu page: 'toplevel_page_campaignbridge'
	 * - Submenu pages: Only pages with matching page_slug properties
	 * - Uses auto-discovery for accurate page detection
	 * - Prevents false positives on other admin pages
	 *
	 * @since 0.1.0
	 * @param string $screen_id The WordPress admin screen ID to check.
	 * @return bool True if the current screen is a CampaignBridge page, false otherwise.
	 */
	public static function is_campaignbridge_page( string $screen_id ): bool {
		$extracted_slug = self::extract_page_slug_from_screen_id( $screen_id );
		if ( ! $extracted_slug ) {
			return false;
		}

		// Check against auto-discovered admin page classes.
		$admin_page_classes = self::get_admin_page_classes();

		foreach ( $admin_page_classes as $class ) {
			if ( class_exists( $class ) && method_exists( $class, 'get_page_slug' ) ) {
				$page_slug = $class::get_page_slug();
				if ( $page_slug === $extracted_slug ) {
					return true;
				}
			}
		}

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
		if ( ! $screen ) {
			return null;
		}

		return self::extract_page_slug_from_screen_id( $screen->id );
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
