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
	 * Default option key for plugin settings.
	 */
	private const DEFAULT_OPTION_NAME = 'campaignbridge_settings';

	/**
	 * Settings update parameter name.
	 */
	private const SETTINGS_UPDATED_PARAM = 'settings-updated';

	/**
	 * Settings messages group name.
	 */
	private const SETTINGS_MESSAGES_GROUP = 'campaignbridge_messages';

	/**
	 * Option key used to store plugin settings.
	 *
	 * @var string
	 */
	protected static string $option_name = self::DEFAULT_OPTION_NAME;

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
	 * Initialize shared state and configuration for all admin pages.
	 *
	 * @since 0.1.0
	 * @param string $option_name The WordPress option key for settings storage.
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
	 * Get the WordPress option name for settings storage.
	 *
	 * @since 0.1.0
	 * @return string The option name.
	 */
	protected static function get_option_name(): string {
		return self::$option_name;
	}

	/**
	 * Get the map of registered email service providers.
	 *
	 * @since 0.1.0
	 * @return array<string,object> Map of provider slugs to provider instances.
	 */
	protected static function get_providers(): array {
		return self::$providers;
	}

	/**
	 * Get the current plugin settings from WordPress options API.
	 *
	 * @since 0.1.0
	 * @return array The current settings array, or empty array if no settings exist.
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
	 * Display WordPress admin notices for settings updates.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	protected static function display_messages(): void {
		if ( isset( $_GET[ self::SETTINGS_UPDATED_PARAM ] ) && 'true' === $_GET[ self::SETTINGS_UPDATED_PARAM ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error( self::SETTINGS_MESSAGES_GROUP, 'campaignbridge_message', __( 'Settings saved.', 'campaignbridge' ), 'updated' );
		}
		settings_errors( self::SETTINGS_MESSAGES_GROUP );
	}
}
