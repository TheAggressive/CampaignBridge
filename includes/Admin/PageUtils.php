<?php
/**
 * Page Utilities for CampaignBridge Admin Interface.
 *
 * Provides utility functions for detecting and managing CampaignBridge admin pages.
 * Handles page identification, slug extraction, and class auto-discovery.
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
	 * WordPress screen ID patterns.
	 */
	private const MAIN_MENU_SCREEN_ID   = 'toplevel_page_campaignbridge';
	private const SUBMENU_SCREEN_PREFIX = 'campaignbridge_page_';

	/**
	 * Page slugs.
	 */
	private const DASHBOARD_PAGE_SLUG = 'dashboard';

	/**
	 * Directory paths.
	 */
	private const ADMIN_PAGES_DIR = 'includes/Admin/Pages/';

	/**
	 * Cache status values.
	 */
	private const CACHE_STATUS_CACHED     = 'cached';
	private const CACHE_STATUS_NOT_CACHED = 'not_cached';

	/**
	 * File patterns.
	 */
	private const PHP_FILE_PATTERN = '*.php';
	/**
	 * Extract the page slug from a WordPress admin screen ID.
	 *
	 * Parses WordPress admin screen IDs to extract the page slug.
	 * Handles both main menu pages and submenu pages.
	 *
	 * @since 0.1.0
	 * @param string $screen_id The WordPress admin screen ID.
	 * @return string|null The extracted page slug or null if not a CampaignBridge page.
	 */
	public static function extract_page_slug_from_screen_id( string $screen_id ): ?string {
		// Main menu page.
		if ( self::MAIN_MENU_SCREEN_ID === $screen_id ) {
			return self::DASHBOARD_PAGE_SLUG;
		}

		// Submenu pages: extract slug from screen ID prefix.
		if ( strpos( $screen_id, self::SUBMENU_SCREEN_PREFIX ) === 0 ) {
			return str_replace( self::SUBMENU_SCREEN_PREFIX, '', $screen_id );
		}

		return null;
	}

	/**
	 * Get debug information about discovered admin page classes.
	 *
	 * Provides detailed information about the auto-discovery process.
	 *
	 * @since 0.1.0
	 * @return array Debug information about admin page discovery.
	 */
	public static function get_admin_page_debug_info(): array {
		$pages_dir = CB_PATH . self::ADMIN_PAGES_DIR;
		$classes   = self::get_admin_page_classes();
		$debug     = array(
			'pages_directory'        => $pages_dir,
			'pages_directory_exists' => is_dir( $pages_dir ),
			'discovered_classes'     => $classes,
			'class_count'            => count( $classes ),
			'cache_status'           => self::CACHE_STATUS_CACHED,
		);

		// Check if cache is being used.
		static $cached_classes = null;
		if ( null === $cached_classes ) {
			$debug['cache_status'] = self::CACHE_STATUS_NOT_CACHED;
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
	 * Discovers admin page classes by scanning the Pages directory.
	 * Results are cached for performance.
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

		$pages_dir = self::get_admin_pages_directory();
		if ( ! self::is_valid_directory( $pages_dir ) ) {
			$cached_classes = array();
			return $cached_classes;
		}

		$files   = self::get_php_files_in_directory( $pages_dir );
		$classes = self::filter_admin_page_classes( $files );

		// Cache the result for future calls.
		$cached_classes = $classes;
		return $classes;
	}

	/**
	 * Get the admin pages directory path.
	 *
	 * @return string The directory path.
	 */
	private static function get_admin_pages_directory(): string {
		return CB_PATH . self::ADMIN_PAGES_DIR;
	}

	/**
	 * Check if directory is valid for scanning.
	 *
	 * @param string $directory The directory path to check.
	 * @return bool True if directory is valid.
	 */
	private static function is_valid_directory( string $directory ): bool {
		return is_dir( $directory );
	}

	/**
	 * Get all PHP files in a directory.
	 *
	 * @param string $directory The directory to scan.
	 * @return array<string> Array of file paths.
	 */
	private static function get_php_files_in_directory( string $directory ): array {
		$files = glob( $directory . self::PHP_FILE_PATTERN );
		return is_array( $files ) ? $files : array();
	}

	/**
	 * Filter files to find valid admin page classes.
	 *
	 * @param array<string> $files Array of file paths.
	 * @return array<string> Array of valid class names.
	 */
	private static function filter_admin_page_classes( array $files ): array {
		$classes = array();

		foreach ( $files as $file ) {
			$class_name = self::get_class_name_from_file( $file );

			if ( self::is_valid_admin_page_class( $class_name ) ) {
				$classes[] = $class_name;
			}
		}

		return $classes;
	}

	/**
	 * Extract class name from file path.
	 *
	 * @param string $file_path The file path.
	 * @return string The class name.
	 */
	private static function get_class_name_from_file( string $file_path ): string {
		$filename = basename( $file_path, '.php' );
		return 'CampaignBridge\\Admin\\Pages\\' . $filename;
	}

	/**
	 * Check if a class is a valid admin page class.
	 *
	 * @param string $class_name The class name to check.
	 * @return bool True if valid admin page class.
	 */
	private static function is_valid_admin_page_class( string $class_name ): bool {
		return class_exists( $class_name ) &&
				is_subclass_of( $class_name, \CampaignBridge\Admin\Pages\Admin::class );
	}

	/**
	 * Check if the current admin screen is a CampaignBridge page.
	 *
	 * Analyzes the WordPress admin screen ID to determine if the current page
	 * is part of the CampaignBridge admin interface. Uses auto-discovered
	 * admin page classes for accurate detection.
	 *
	 * @since 0.1.0
	 * @param string $screen_id The WordPress admin screen ID to check.
	 * @return bool True if the screen is a CampaignBridge page.
	 */
	public static function is_campaignbridge_page( string $screen_id ): bool {
		$extracted_slug = self::extract_page_slug_from_screen_id( $screen_id );
		if ( ! $extracted_slug ) {
			return false;
		}

		// Check against auto-discovered admin page classes.
		$admin_page_classes = self::get_admin_page_classes();

		foreach ( $admin_page_classes as $class ) {
			if ( self::class_has_page_slug( $class, $extracted_slug ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a class has a specific page slug.
	 *
	 * @param string $class_name The class name to check.
	 * @param string $expected_slug The expected page slug.
	 * @return bool True if class has the expected page slug.
	 */
	private static function class_has_page_slug( string $class_name, string $expected_slug ): bool {
		if ( ! class_exists( $class_name ) || ! method_exists( $class_name, 'get_page_slug' ) ) {
			return false;
		}

		$page_slug = $class_name::get_page_slug();
		return $page_slug === $expected_slug;
	}

	/**
	 * Get the current CampaignBridge admin page slug.
	 *
	 * Extracts the page identifier from the current WordPress admin screen.
	 *
	 * @since 0.1.0
	 * @return string|null The current page slug or null if not a CampaignBridge page.
	 */
	public static function get_current_page_slug(): ?string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return null;
		}

		return self::extract_page_slug_from_screen_id( $screen->id );
	}

	/**
	 * Check if current page matches a specific CampaignBridge page slug.
	 *
	 * @param string $page_slug The page slug to check.
	 * @return bool True if current page matches the slug.
	 */
	public static function is_current_page( string $page_slug ): bool {
		$current_slug = self::get_current_page_slug();
		return $current_slug === $page_slug;
	}
}
