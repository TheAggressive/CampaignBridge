<?php
/**
 * CampaignBridge provider interface.
 *
 * @package CampaignBridge
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
	 * Unique slug for the provider (e.g., 'mailchimp', 'html').
	 *
	 * @return string
	 */
	public function slug();

	/**
	 * Human-readable label for the provider.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Whether the provider has sufficient settings to operate.
	 *
	 * @param array $settings Plugin settings array.
	 * @return bool
	 */
	public function is_configured( $settings );

	/**
	 * Render provider-specific settings fields (within a table row context).
	 *
	 * @param array  $settings    Plugin settings array.
	 * @param string $option_name Root option name used for field names.
	 * @return void
	 */
	public function render_settings_fields( $settings, $option_name );

	/**
	 * Create/update a campaign with the given blocks (Mailchimp)/export HTML (HTML provider).
	 *
	 * @param array $blocks   Associative array of section_key => HTML string.
	 * @param array $settings Plugin settings array.
	 * @return bool True on success, false on error (and provider should surface notices).
	 */
	public function send_campaign( $blocks, $settings );

	/**
	 * Return a list of template section keys (if supported by provider), used for mapping.
	 *
	 * @param array $settings Plugin settings array.
	 * @return array|\WP_Error Array of strings (keys) or WP_Error on failure/unsupported.
	 */
	public function get_section_keys( $settings );
}
