<?php
/**
 * Provider Interface for CampaignBridge Email Service Providers.
 *
 * This interface defines the contract that all email service providers must
 * implement to integrate with CampaignBridge. It ensures consistent provider
 * behavior, configuration management, and campaign delivery across different
 * email services and export formats.
 *
 * ## Implementation Guide
 *
 * To create a new provider (e.g., SendGrid, Constant Contact, etc.):
 *
 * 1. Create a class that implements this interface
 * 2. Implement all required methods with proper error handling
 * 3. Register the provider in Service_Container.php
 * 4. Add provider-specific settings to admin interface
 * 5. Handle API authentication and rate limiting
 *
 * ## Example Provider Structure:
 *
 * ```php
 * class MyProvider implements Provider_Interface {
 *     public function slug(): string { return 'myprovider'; }
 *     public function label(): string { return 'My Provider'; }
 *
 *     public function is_configured(array $settings): bool {
 *         return !empty($settings['api_key']);
 *     }
 *
 *     // ... implement other methods
 * }
 * ```
 *
 * ## Security Considerations:
 *
 * - Always validate and sanitize settings in settings_schema()
 * - Use redact_settings() to mask sensitive data in logs
 * - Implement proper rate limiting via rate_limit_policy()
 * - Validate all input parameters in send_campaign()
 * - Handle API errors gracefully with user-friendly messages
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
interface Provider_Interface {

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
	 * @param array<string, mixed> $settings Plugin settings array containing provider configuration.
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
	 * @param array<string, mixed> $settings    Current plugin settings array.
	 * @param string               $option_name Root option name for form field namespacing.
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
	 * @param array<string, mixed> $blocks   Associative array mapping section keys to HTML content.
	 *                       Format: ['header' => '<html>...</html>', 'body' => '<html>...</html>'].
	 * @param array<string, mixed> $settings Plugin settings array with provider configuration.
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
	 * @param array<string, mixed> $settings Plugin settings array (for provider-specific logic).
	 * @param bool                 $refresh  Force refresh of cached data.
	 * @return array<string>|\WP_Error Array of section key strings, or WP_Error if unsupported/unavailable.
	 */
	public function get_section_keys( array $settings, bool $refresh = false );

	/**
	 * Get the required capability for this provider.
	 *
	 * @return string Capability slug (e.g., 'campaignbridge_manage').
	 */
	public function required_capability(): string;

	/**
	 * Get rate limiting policy for this provider.
	 *
	 * @return array<string, mixed> Array with 'bucket' and 'max_per_minute' keys.
	 */
	public function rate_limit_policy(): array;

	/**
	 * Get settings schema for validation and redaction.
	 *
	 * @return array<string, mixed> Schema array with field definitions.
	 */
	public function settings_schema(): array;

	/**
	 * Redact sensitive settings for display/logging.
	 *
	 * @param array<string, mixed> $settings Raw settings array.
	 * @return array<string, mixed> Redacted settings array.
	 */
	public function redact_settings( array $settings ): array;

	/**
	 * Get provider capabilities and supported features.
	 *
	 * Returns an array of features this provider supports, which can be used
	 * to conditionally show/hide UI elements or functionality.
	 *
	 * @return array<string, mixed> Array of supported features. Examples:
	 *               ['audiences' => true, 'templates' => true, 'scheduling' => false]
	 */
	public function get_capabilities(): array;

	/**
	 * Get API key validation pattern for this provider.
	 *
	 * Returns a regex pattern used to validate API keys specific to this provider.
	 * This ensures that only valid API keys for the provider are accepted during
	 * configuration and migration processes.
	 *
	 * @return string Regex pattern for API key validation.
	 */
	public function get_api_key_pattern(): string;

	/**
	 * Sanitize provider-specific settings based on schema.
	 *
	 * Validates and sanitizes settings according to the provider's schema definition.
	 * This ensures that only valid, properly formatted settings are stored and used.
	 *
	 * @param array<string, mixed> $settings Raw settings array to sanitize.
	 * @return array<string, mixed> Sanitized settings array.
	 */
	public function sanitize_settings( array $settings ): array;
}
