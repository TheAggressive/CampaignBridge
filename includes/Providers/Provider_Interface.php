<?php
/**
 * Provider Interface for CampaignBridge Email Service Providers.
 *
 * Provider metadata, settings validation, and discovery.
 * Campaign compilation belongs to the email workflow.
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
