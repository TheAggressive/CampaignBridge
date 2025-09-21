<?php
/**
 * Provider Interface for CampaignBridge Email Service Providers.
 *
 * This interface defines the contract that all email service providers must
 * implement to integrate with CampaignBridge. It ensures consistent provider
 * behavior, configuration management, and campaign delivery across different
 * email services and export formats.
 *
 * This interface ensures that all providers follow consistent patterns
 * and provide reliable email campaign functionality.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName, Generic.WhiteSpace.DisallowSpaceIndent
/**
 * Provider interface for CampaignBridge providers.
 */
interface ProviderInterface {
	/**
	 * Default provider slug constant.
	 */
	public const DEFAULT_SLUG = 'default';

	/**
	 * Configuration status constants.
	 */
	public const CONFIGURED      = 'configured';
	public const NOT_CONFIGURED  = 'not_configured';
	public const CONFIG_REQUIRED = 'config_required';

	/**
	 * Common setting keys used by providers.
	 */
	public const SETTING_API_KEY     = 'api_key';
	public const SETTING_AUDIENCE_ID = 'audience_id';
	public const SETTING_PROVIDER    = 'provider';

	/**
	 * Get unique slug for the provider.
	 *
	 * This slug is used as an identifier throughout the system and should
	 * be unique across all providers. Examples: 'mailchimp', 'html', 'sendgrid'.
	 *
	 * @return string Provider slug identifier.
	 */
	public function slug(): string;

	/**
	 * Get human-readable label for the provider.
	 *
	 * This label is displayed in the admin interface and should be
	 * user-friendly. Examples: 'Mailchimp', 'HTML Export', 'SendGrid'.
	 *
	 * @return string Provider display name.
	 */
	public function label(): string;

	/**
	 * Check if the provider has sufficient settings to operate.
	 *
	 * Validates that all required configuration is present and valid.
	 * This method should check for API keys, endpoints, and other
	 * provider-specific requirements.
	 *
	 * @param array $settings Plugin settings array containing provider configuration.
	 * @return bool True if provider is ready to send campaigns.
	 */
	public function is_configured( array $settings ): bool;

	/**
	 * Render provider-specific settings fields in the admin interface.
	 *
	 * Outputs HTML form fields for provider configuration within a table row.
	 * Should include all necessary inputs for provider setup including
	 * API keys, audience selection, and other provider-specific options.
	 *
	 * @param array  $settings    Current plugin settings array.
	 * @param string $option_name Root option name for form field namespacing.
	 * @return void Outputs HTML directly to the page.
	 */
	public function render_settings_fields( array $settings, string $option_name ): void;

	/**
	 * Send campaign or export content based on provider type.
	 *
	 * For email service providers (Mailchimp, etc.): Creates or updates
	 * a campaign with the provided content blocks.
	 *
	 * For export providers (HTML): Generates static HTML files or exports
	 * content to the specified format.
	 *
	 * @param array $blocks   Associative array mapping section keys to HTML content.
	 *                       Format: ['header' => '<html>...</html>', 'body' => '<html>...</html>']
	 * @param array $settings Plugin settings array with provider configuration.
	 * @return bool|\WP_Error True on success, WP_Error on failure with details.
	 */
	public function send_campaign( array $blocks, array $settings );

	/**
	 * Get available template section keys for content mapping.
	 *
	 * Returns an array of section identifiers that this provider supports
	 * for template mapping. These keys correspond to sections in email
	 * templates where dynamic content can be inserted.
	 *
	 * Examples: ['header', 'body', 'footer'] or ['content', 'sidebar']
	 *
	 * @param array $settings Plugin settings array (for provider-specific logic).
	 * @return array|\WP_Error Array of section key strings, or WP_Error if unsupported/unavailable.
	 */
	public function get_section_keys( array $settings );
}
