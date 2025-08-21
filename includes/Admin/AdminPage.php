<?php
/**
 * Base Admin Page Class for CampaignBridge
 *
 * Provides common functionality and state management for admin pages.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

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
	 * Registered providers map indexed by slug.
	 *
	 * @var array<string,object>
	 */
	protected static array $providers = array();

	/**
	 * Initialize shared state for all admin pages.
	 *
	 * @param string $option_name Options key used by the plugin.
	 * @param array  $providers   Registered providers map.
	 * @return void
	 */
	public static function init_shared_state( string $option_name, array $providers ): void {
		self::$option_name = $option_name;
		self::$providers   = $providers;
	}

	/**
	 * Get the current option name.
	 *
	 * @return string
	 */
	protected static function get_option_name(): string {
		return self::$option_name;
	}

	/**
	 * Get the current providers.
	 *
	 * @return array<string,object>
	 */
	protected static function get_providers(): array {
		return self::$providers;
	}

	/**
	 * Get the current settings.
	 *
	 * @return array
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
	 * Display settings errors and success messages.
	 *
	 * @return void
	 */
	protected static function display_messages(): void {
		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error( 'campaignbridge_messages', 'campaignbridge_message', __( 'Settings saved.', 'campaignbridge' ), 'updated' );
		}
		settings_errors( 'campaignbridge_messages' );
	}
}
