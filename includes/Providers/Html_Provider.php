<?php
/**
 * HTML Export Provider for CampaignBridge.
 *
 * Provides HTML export functionality for email campaigns, allowing users to
 * export their email templates as static HTML files for use with any email
 * service provider or for manual distribution.
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

/**
 * HTML Export Provider: handles static HTML export functionality.
 *
 * This provider allows users to export their email campaigns as static HTML
 * files that can be used with any email service provider or distributed manually.
 * It provides a simple way to generate email-safe HTML without requiring
 * external API integrations.
 */
class Html_Provider extends Abstract_Provider {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'html', __( 'HTML Export', 'campaignbridge' ) );

		// Set custom API key pattern for HTML provider (not applicable).
		$this->api_key_pattern = '/^$/'; // Empty pattern since no API key needed.
	}

	/**
	 * Check if the provider has sufficient settings to operate.
	 *
	 * HTML export doesn't require any configuration, so it's always ready.
	 *
	 * @param array<string, mixed> $settings Plugin settings array.
	 * @return bool Always true for HTML export.
	 */
	public function is_configured( array $settings ): bool {
		return true; // HTML export requires no configuration.
	}

	/**
	 * Verify that the local export provider is available.
	 *
	 * @param array<string, mixed> $settings Provider settings (unused).
	 */
	public function verify_connection( array $settings ): Connection_Result {
		return Connection_Result::success(
			array( 'provider' => $this->slug() )
		);
	}

	/**
	 * Get available template section keys for content mapping.
	 *
	 * HTML export supports all standard email template sections.
	 *
	 * @param array<string, mixed> $settings Plugin settings array (unused for HTML export).
	 * @param bool                 $refresh  Force refresh of cached data (unused for HTML export).
	 * @return array<string> Array of section key strings.
	 */
	public function get_section_keys( array $settings, bool $refresh = false ): array {
		return array(
			'header',
			'body',
			'footer',
			'content',
			'sidebar',
		);
	}

	/**
	 * Get settings schema for validation and redaction.
	 *
	 * @return array<string, mixed> Schema array with field definitions.
	 */
	public function settings_schema(): array {
		return array(
			'export_format' => array(
				'type'        => 'string',
				'default'     => 'html',
				'description' => __( 'Export format for HTML files', 'campaignbridge' ),
			),
			'include_css'   => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Include inline CSS in exported HTML', 'campaignbridge' ),
			),
		);
	}

	/**
	 * Redact sensitive settings for display/logging.
	 *
	 * HTML export doesn't have sensitive settings, so returns as-is.
	 *
	 * @param array<string, mixed> $settings Raw settings array.
	 * @return array<string, mixed> Redacted settings array.
	 */
	public function redact_settings( array $settings ): array {
		// HTML export has no sensitive data to redact.
		return $settings;
	}

	/**
	 * Get provider capabilities and supported features.
	 *
	 * @return array<string, mixed> Array of supported features.
	 */
	public function get_capabilities(): array {
		return array(
			'verify_connection'          => true,
			'discover_template_sections' => true,
			'export'                     => true,
			'discover_audiences'         => false,
			'create_draft'               => false,
			'send_test'                  => false,
			'schedule'                   => false,
			'send'                       => false,
			'reconcile'                  => false,
			'reports'                    => false,
		);
	}
}
