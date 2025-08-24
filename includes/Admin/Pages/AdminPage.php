<?php
/**
 * Base Admin Page Class for CampaignBridge Admin Interface.
 *
 * This abstract class serves as the foundation for all CampaignBridge admin pages,
 * providing shared functionality, state management, and common patterns that
 * eliminate code duplication across different page implementations.
 *
 * Key Features:
 * - Shared state management for plugin settings and providers
 * - Common settings retrieval and validation methods
 * - Standardized message display and error handling
 * - Abstract methods for page-specific rendering and titles
 * - Consistent option name and provider access patterns
 *
 * Design Principles:
 * - Follows the Template Method pattern for consistent page structure
 * - Provides default implementations for common functionality
 * - Requires subclasses to implement page-specific logic
 * - Maintains clean separation between shared and page-specific code
 * - Ensures consistent user experience across all admin pages
 *
 * Usage:
 * - Extend this class for all new admin pages
 * - Implement required abstract methods (render, get_page_title)
 * - Use shared state methods for consistent data access
 * - Leverage common utility methods for standard functionality
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
	 * Shared State Components:
	 * - Option name for plugin settings storage and retrieval
	 * - Registered providers map for email service integration
	 * - Consistent data access patterns across all pages
	 * - Centralized configuration management
	 *
	 * State Management:
	 * - Static properties for shared data access
	 * - Consistent option name across all admin pages
	 * - Provider instances available to all pages
	 * - Centralized state initialization and management
	 *
	 * Usage Benefits:
	 * - Eliminates duplicate configuration code
	 * - Ensures consistent data access patterns
	 * - Centralizes state management and updates
	 * - Simplifies admin page implementation
	 * - Maintains data consistency across pages
	 *
	 * Integration Features:
	 * - Works with WordPress options API
	 * - Supports provider system integration
	 * - Maintains plugin configuration state
	 * - Enables cross-page data sharing
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
	 * Retrieve the current WordPress option name used for plugin settings storage.
	 *
	 * This method provides access to the centralized option name that all admin
	 * pages use for storing and retrieving plugin settings. It ensures consistent
	 * data access patterns across the entire admin interface.
	 *
	 * Option Name Usage:
	 * - WordPress options API integration for settings storage
	 * - Consistent option key across all admin pages
	 * - Centralized settings management and retrieval
	 * - Plugin configuration persistence and updates
	 *
	 * Data Access Benefits:
	 * - Eliminates hardcoded option names throughout the codebase
	 * - Ensures consistent option key usage across all pages
	 * - Centralizes option name management and updates
	 * - Simplifies settings access and modification
	 *
	 * Integration Features:
	 * - Works with WordPress get_option() and update_option() functions
	 * - Supports plugin settings API integration
	 * - Maintains settings consistency across sessions
	 * - Enables centralized settings management
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
	 * Provider Map Structure:
	 * - Indexed by provider slug (e.g., 'mailchimp', 'html')
	 * - Contains provider instance objects implementing ProviderInterface
	 * - Provides access to provider-specific functionality and settings
	 * - Enables dynamic provider selection and configuration
	 *
	 * Provider Access Benefits:
	 * - Eliminates hardcoded provider references throughout the codebase
	 * - Ensures consistent provider access patterns across all pages
	 * - Centralizes provider management and configuration
	 * - Simplifies provider integration and customization
	 *
	 * Integration Features:
	 * - Works with CampaignBridge provider system
	 * - Supports provider-specific settings and configuration
	 * - Maintains provider consistency across sessions
	 * - Enables dynamic provider switching and management
	 *
	 * Usage Examples:
	 * - Provider selection in settings pages
	 * - Provider-specific configuration fields
	 * - Email campaign creation and management
	 * - Provider validation and testing
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
	 * Settings Structure:
	 * - Provider selection and configuration
	 * - API keys and authentication credentials
	 * - Email campaign configuration options
	 * - Post type inclusion/exclusion settings
	 * - Provider-specific configuration data
	 *
	 * Data Access Benefits:
	 * - Eliminates duplicate settings retrieval code throughout the codebase
	 * - Ensures consistent settings access patterns across all pages
	 * - Centralizes settings management and updates
	 * - Simplifies configuration access and modification
	 *
	 * WordPress Integration:
	 * - Uses WordPress get_option() function for data retrieval
	 * - Supports default value fallbacks for missing settings
	 * - Maintains settings consistency across sessions
	 * - Enables centralized settings management
	 *
	 * Usage Examples:
	 * - Display current configuration in admin pages
	 * - Validate user input against existing settings
	 * - Provide default values for form fields
	 * - Check feature availability based on configuration
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
	 * This method handles the display of user feedback messages after form
	 * submissions, including success messages for saved settings and error
	 * messages for validation failures. It integrates with WordPress's
	 * built-in admin notice system for consistent user experience.
	 *
	 * Message Types:
	 * - Success messages for successful settings updates
	 * - Error messages for validation failures and processing errors
	 * - Warning messages for potential issues and recommendations
	 * - Info messages for general information and guidance
	 *
	 * WordPress Integration:
	 * - Uses WordPress settings_errors() function for message display
	 * - Integrates with WordPress admin notice system
	 * - Supports custom message styling and positioning
	 * - Maintains consistent admin interface appearance
	 *
	 * User Experience Benefits:
	 * - Clear feedback on form submission results
	 * - Professional appearance consistent with WordPress admin
	 * - Accessible message display and formatting
	 * - Consistent messaging across all admin pages
	 *
	 * Message Sources:
	 * - Settings API validation errors
	 * - Custom validation and processing errors
	 * - Success confirmations for user actions
	 * - System status and information messages
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
