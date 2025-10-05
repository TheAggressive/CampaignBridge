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
use CampaignBridge\Core\Api_Key_Encryption;
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
	 * Render the tab content using WordPress Settings API.
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
	 * Get field configuration for validation rules.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @return array Field configuration array.
	 */
	protected static function get_field_config( string $field_name ): array {
		$config = array(
			'provider' => array(
				'label' => __( 'Email Service Provider', 'campaignbridge' ),
				'type' => 'select',
				'required' => true,
				'options' => array_keys( Admin::get_providers() ),
				'validate_callback' => array( __CLASS__, 'validate_provider_selection' ),
				'sanitize_callback' => 'sanitize_key',
			),
		);

		return $config[ $field_name ] ?? parent::get_field_config( $field_name );
	}

	/**
	 * Validate provider selection.
	 *
	 * @since 0.1.0
	 * @param string $value Provider value.
	 * @param string $field_name Field name.
	 * @return string|null Error message or null if valid.
	 */
	public static function validate_provider_selection( $value, string $field_name ): ?string {
		$available_providers = array_keys( Admin::get_providers() );

		if ( ! in_array( $value, $available_providers, true ) ) {
			return __( 'Please select a valid email service provider.', 'campaignbridge' );
		}

		return null;
	}

	/**
	 * Sanitize settings for this tab.
	 *
	 * @since 0.1.0
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( array $settings ): array {
		$sanitized = array();

		foreach ( static::get_tab_fields() as $field_name ) {
			$value = $settings[ $field_name ] ?? '';
			$config = static::get_field_config( $field_name );

			if ( isset( $config['sanitize_callback'] ) && is_callable( $config['sanitize_callback'] ) ) {
				$sanitized[ $field_name ] = call_user_func( $config['sanitize_callback'], $value );
			} else {
				$sanitized[ $field_name ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
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
	 * Render the provider field using WordPress Settings API.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render_provider_field(): void {
		$settings  = \CampaignBridge\Admin\Pages\Admin::get_decrypted_settings();
		$providers = Admin::get_providers();
		$provider  = self::get_selected_provider( $settings, $providers );

		// Check for view parameter for provider preview (new parameter)
		// Also check for provider_preview for backward compatibility (old parameter)
		$preview_provider = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View parameter, no security risk

		// Fallback to old parameter if new one not set
		if ( ! $preview_provider && isset( $_GET['provider_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Backward compatibility, no security risk
			$preview_provider = sanitize_key( wp_unslash( $_GET['provider_preview'] ) );
		}

		// Validate preview provider exists and is not the example provider
		if ( $preview_provider && ( ! isset( $providers[ $preview_provider ] ) || 'example' === $preview_provider ) ) {
			$preview_provider = null; // Reset invalid preview provider
		}

		$options = array();
		foreach ( $providers as $slug => $obj ) {
			// Skip example provider in production dropdown.
			if ( 'example' === $slug ) {
				continue;
			}
			$options[ $slug ] = $obj->label();
		}

		// Use WordPress Settings API field rendering
		$field_name = Settings_Manager::get_option_name() . '[provider]';
		$current_value = $settings['provider'] ?? '';

		// Use preview value if available, otherwise use saved value
		$display_value = $preview_provider ?? $current_value;

		?>
		<select name="<?php echo esc_attr( $field_name ); ?>" id="<?php echo esc_attr( Settings_Manager::get_option_name() . '_provider' ); ?>" class="regular-text campaignbridge-field campaignbridge-select campaignbridge-provider-select" data-preview-url="<?php echo esc_url( add_query_arg( 'view', '', admin_url( 'admin.php?page=campaignbridge-settings&tab=providers' ) ) ); ?>">
			<?php foreach ( $options as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $display_value, $option_value ); ?>>
					<?php echo esc_html( $option_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Choose which email client or export method to use.', 'campaignbridge' ); ?></p>
		<?php


		// Render provider-specific fields using Settings API
		// Use preview provider if available, otherwise use selected provider
		$display_provider = $preview_provider ?? $provider;
		if ( isset( $providers[ $display_provider ] ) ) {
			echo '<div class="provider-specific-fields" style="margin-top: 16px;">';
			$providers[ $display_provider ]->render_settings_fields( $settings, Settings_Manager::get_option_name() );
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
