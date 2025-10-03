<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Email Providers Settings Tab for CampaignBridge Admin Interface.
 *
 * Handles the email provider settings tab content including provider selection,
 * API configuration, and provider-specific settings for email service integration.
 *
 * @package CampaignBridge\Admin\Pages
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages\Tabs;

use CampaignBridge\Admin\Pages\Admin;
use CampaignBridge\Admin\Pages\Settings_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email Providers Settings Tab class.
 *
 * Handles the email provider settings tab functionality for configuring
 * email service provider integrations including API keys, audience settings,
 * and provider-specific configuration options using the tab architecture.
 */
class Email_Providers_Settings_Tab extends Abstract_Settings_Tab {
	/**
	 * Default provider slug.
	 */
	private const DEFAULT_PROVIDER = 'html';

	/**
	 * Provider field name attribute.
	 */
	private const PROVIDER_FIELD_NAME = 'provider';

	/**
	 * Get the tab slug (used as identifier and URL parameter).
	 *
	 * @since 0.1.0
	 * @return string The tab slug.
	 */
	public static function get_slug(): string {
		return 'providers';
	}

	/**
	 * Get the tab label (display name).
	 *
	 * @since 0.1.0
	 * @return string The tab label.
	 */
	public static function get_label(): string {
		return __( 'Providers', 'campaignbridge' );
	}

	/**
	 * Get the tab description.
	 *
	 * @since 0.1.0
	 * @return string The tab description.
	 */
	public static function get_description(): string {
		return __( 'Configure email service provider settings and API connections.', 'campaignbridge' );
	}

	/**
	 * Register settings sections and fields for this tab.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register_settings(): void {
		// Provider settings section.
		add_settings_section(
			'campaignbridge_providers',
			__( 'Provider Settings', 'campaignbridge' ),
			array( __CLASS__, 'render_provider_section' ),
			'campaignbridge_providers'
		);

		// Provider settings fields.
		add_settings_field(
			'provider',
			__( 'Provider', 'campaignbridge' ),
			array( __CLASS__, 'render_provider_field' ),
			'campaignbridge_providers',
			'campaignbridge_providers'
		);

		// Provider-specific fields will be added dynamically in render_provider_field.
	}

	/**
	 * Render the tab content.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render(): void {
		?>
		<div class="tab-content">
			<?php do_settings_sections( 'campaignbridge_providers' ); ?>
		</div>
		<?php
	}

	/**
	 * Get the fields that belong to this tab.
	 *
	 * @since 0.1.0
	 * @return array Array of field names.
	 */
	public static function get_tab_fields(): array {
		return array( 'provider' );
	}

	/**
	 * Validate settings for this tab.
	 *
	 * @since 0.1.0
	 * @param array $settings Settings to validate.
	 * @return array Validation errors or empty array if valid.
	 */
	public static function validate_settings( array $settings ): array {
		$errors = array();

		// Validate provider selection
		if ( isset( $settings['provider'] ) ) {
			$provider            = sanitize_key( $settings['provider'] );
			$available_providers = array_keys( Admin::get_providers() );

			if ( ! in_array( $provider, $available_providers, true ) ) {
				$errors['provider'] = __( 'Please select a valid email service provider.', 'campaignbridge' );
			}
		}

		return $errors;
	}

	/**
	 * Render the provider settings section description.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render_provider_section(): void {
		echo '<p>' . esc_html__( 'Select your email service provider and configure the connection settings.', 'campaignbridge' ) . '</p>';
	}

	/**
	 * Render the provider field.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render_provider_field(): void {
		self::display_field_errors( 'provider' );

		$settings  = Settings_Manager::get_settings();
		$providers = Admin::get_providers();
		$provider  = self::get_selected_provider( $settings, $providers );

		$options = array();
		foreach ( $providers as $slug => $obj ) {
			// Skip example provider in production dropdown.
			if ( 'example' === $slug ) {
				continue;
			}
			$options[ $slug ] = $obj->label();
		}

		self::render_select_field(
			'provider',
			$options,
			array(
				'description' => __( 'Choose which email client or export method to use.', 'campaignbridge' ),
			)
		);

		// Render provider-specific fields.
		if ( isset( $providers[ $provider ] ) ) {
			echo '<div class="provider-specific-fields" style="margin-top: 16px;">';
			$providers[ $provider ]->render_settings_fields( $settings, Settings_Manager::get_option_name() );
			echo '</div>';
		}
	}

	/**
	 * Get the selected provider or default to html.
	 *
	 * @param array $settings  Current settings.
	 * @param array $providers Available providers.
	 * @return string The selected provider slug.
	 */
	private static function get_selected_provider( array $settings, array $providers ): string {
		$current_provider = $settings['provider'] ?? self::DEFAULT_PROVIDER;

		return ( isset( $providers[ $current_provider ] ) )
			? $current_provider
			: self::DEFAULT_PROVIDER;
	}
}
