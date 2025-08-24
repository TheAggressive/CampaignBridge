<?php
/**
 * Base Admin Page Class for CampaignBridge Admin Interface.
 *
 * This abstract class serves as the foundation for all CampaignBridge admin pages,
 * providing shared functionality, state management, and common patterns that
 * eliminate code duplication across different page implementations.
 *
 * The class is designed to be stateless at the instance level, with all
 * methods being static to maintain consistency with the existing codebase.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for admin pages.
 *
 * Handles shared state and provides common functionality for all admin pages.
 */
abstract class AdminPage {
	/**
	 * Option key used to store plugin settings.
	 *
	 * @var string
	 */
	protected static string $option_name = 'campaignbridge_settings';

	/**
	 * Page slug for this admin page.
	 *
	 * @var string
	 */
	protected static string $page_slug = '';

	/**
	 * Registered providers map indexed by slug.
	 *
	 * @var array<string,object>
	 */
	protected static array $providers = array();

	/**
	 * Initialize shared state and configuration for all CampaignBridge admin pages.
	 *
	 * This method sets up the common state that all admin pages share, including
	 * the plugin option name for settings storage and the registered providers map.
	 * It ensures consistent data access and configuration across all admin pages.
	 *
	 * @since 0.1.0
	 * @param string $option_name The WordPress option key used to store plugin settings.
	 * @param array  $providers   Map of registered provider instances indexed by slug.
	 * @return void
	 */
	public static function init_shared_state( string $option_name, array $providers ): void {
		self::$option_name = $option_name;
		self::$providers   = $providers;
	}

	/**
	 * Get the page slug for this admin page.
	 *
	 * @since 0.1.0
	 * @return string The page slug.
	 */
	public static function get_page_slug(): string {
		return static::$page_slug;
	}

	/**
	 * Retrieve the current WordPress option name used for plugin settings storage.
	 *
	 * This method provides access to the centralized option name that all admin
	 * pages use for storing and retrieving plugin settings. It ensures consistent
	 * data access patterns across the entire admin interface.
	 *
	 * @since 0.1.0
	 * @return string The WordPress option name used for plugin settings storage.
	 */
	protected static function get_option_name(): string {
		return self::$option_name;
	}

	/**
	 * Retrieve the map of registered email service providers available to admin pages.
	 *
	 * This method provides access to the centralized providers map that all admin
	 * pages use for email service integration. It ensures consistent provider
	 * access patterns across the entire admin interface.
	 *
	 * @since 0.1.0
	 * @return array<string,object> Map of provider slugs to provider instances.
	 */
	protected static function get_providers(): array {
		return self::$providers;
	}

	/**
	 * Retrieve the current plugin settings from WordPress options API.
	 *
	 * This method provides access to the centralized plugin settings that all admin
	 * pages use for configuration and functionality. It ensures consistent settings
	 * access patterns across the entire admin interface.
	 *
	 * @since 0.1.0
	 * @return array The current plugin settings array, or empty array if no settings exist.
	 */
	protected static function get_settings(): array {
		return get_option( self::$option_name, array() );
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	abstract public static function render(): void;

	/**
	 * Get the page title.
	 *
	 * @return string
	 */
	abstract public static function get_page_title(): string;

	/**
	 * Display WordPress admin notices for settings updates and validation errors.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	protected static function display_messages(): void {
		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error( 'campaignbridge_messages', 'campaignbridge_message', __( 'Settings saved.', 'campaignbridge' ), 'updated' );
		}
		settings_errors( 'campaignbridge_messages' );
	}
}
