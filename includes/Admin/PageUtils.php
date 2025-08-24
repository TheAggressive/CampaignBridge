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
	 * Check if the current admin screen is a CampaignBridge page.
	 *
	 * This method analyzes the WordPress admin screen ID to determine whether
	 * the current page is part of the CampaignBridge admin interface. It uses
	 * the static page_slug properties from each admin page class to ensure
	 * only legitimate CampaignBridge pages are matched.
	 *
	 * Page Detection Logic:
	 * - Main menu page: 'toplevel_page_campaignbridge'
	 * - Submenu pages: Only pages with matching page_slug properties
	 * - Uses static properties for accurate page detection
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

		// Check against each admin page class's page_slug property.
		$admin_page_classes = array(
			\CampaignBridge\Admin\Pages\PostTypesPage::class,
			\CampaignBridge\Admin\Pages\SettingsPage::class,
			\CampaignBridge\Admin\Pages\StatusPage::class,
			\CampaignBridge\Admin\Pages\TemplateManagerPage::class,
		);

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

	/**
	 * Check if the current page matches a specific admin page class.
	 *
	 * This method checks if the current page matches the page_slug
	 * property of a specific admin page class.
	 *
	 * @since 0.1.0
	 * @param string $admin_page_class The admin page class to check.
	 * @return bool True if current page matches the class's page_slug.
	 */
	public static function is_current_page_class( string $admin_page_class ): bool {
		if ( ! class_exists( $admin_page_class ) || ! method_exists( $admin_page_class, 'get_page_slug' ) ) {
			return false;
		}

		$current_slug = self::get_current_page_slug();
		$class_slug   = $admin_page_class::get_page_slug();

		return $current_slug === $class_slug;
	}
}
