<?php
/**
 * Provider Interface for CampaignBridge Email Service Providers.
 *
 * Provider metadata, settings validation, and connectivity verification.
 * Campaign compilation belongs to the email workflow.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Providers;

use CampaignBridge\Domain\Campaign\Connection_Result;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName, Generic.WhiteSpace.DisallowSpaceIndent
/**
 * Provider interface for CampaignBridge providers.
 *
 * Every provider adapter identifies itself, validates its configuration,
 * and verifies connectivity. Failures are normalized into
 * {@see \CampaignBridge\Domain\Campaign\Provider_Error} values carried
 * inside a {@see Connection_Result}.
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
	 * @return bool True if the required settings are present and well formed.
	 */
	public function is_configured( array $settings ): bool;

	/**
	 * Verify that the configured provider account is reachable and authorized.
	 *
	 * @param array<string, mixed> $settings Validated provider settings (plaintext credentials).
	 * @return Connection_Result Normalized verification outcome.
	 */
	public function verify_connection( array $settings ): Connection_Result;
}
