<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Settings Manager for CampaignBridge Admin Interface.
 *
 * Centralized settings management with proper validation, sanitization,
 * and tab-aware saving to prevent data loss between tabs.
 *
 * @package CampaignBridge\Admin\Pages
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages;

use CampaignBridge\Admin\Pages\Tabs\Settings_Tab_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Manager class.
 *
 * Handles all settings-related operations including validation,
 * sanitization, and tab-aware saving.
 */
class Settings_Manager {
	/**
	 * Option key for storing settings.
	 */
	private const OPTION_NAME = 'campaignbridge_settings';

	/**
	 * Settings field name for forms.
	 */
	private const SETTINGS_FIELD = 'campaignbridge_settings';

	/**
	 * Nonce action for security - matches the old Settings_Handler logic.
	 */
	private const NONCE_ACTION_GENERAL   = 'campaignbridge_general-options';
	private const NONCE_ACTION_PROVIDERS = 'campaignbridge_providers-options';

	/**
	 * Get the WordPress option name for settings storage.
	 *
	 * @since 0.1.0
	 * @return string The option name.
	 */
	public static function get_option_name(): string {
		return self::OPTION_NAME;
	}

	/**
	 * Get the settings field name for forms.
	 *
	 * @since 0.1.0
	 * @return string The settings field name.
	 */
	public static function get_settings_field(): string {
		return self::SETTINGS_FIELD;
	}

	/**
	 * Get the nonce action for security based on current tab.
	 *
	 * @since 0.1.0
	 * @param string $current_tab Current tab slug.
	 * @return string The nonce action.
	 */
	public static function get_nonce_action( string $current_tab = '' ): string {
		if ( empty( $current_tab ) ) {
			$current_tab = Settings_Tab_Manager::get_current_tab();
		}

		return 'general' === $current_tab ? self::NONCE_ACTION_GENERAL : self::NONCE_ACTION_PROVIDERS;
	}

	/**
	 * Get current plugin settings.
	 *
	 * @since 0.1.0
	 * @return array Current settings or empty array if none exist.
	 */
	public static function get_settings(): array {
		return get_option( self::OPTION_NAME, array() );
	}

	/**
	 * Validate general settings for WordPress Settings API.
	 *
	 * @since 0.1.0
	 * @param array $input Input data to validate.
	 * @return array Validated and sanitized settings.
	 */
	public static function validate_general_settings_callback( array $input ): array {
		$errors = self::validate_general_settings( $input );

		if ( ! empty( $errors ) ) {
			// Add validation errors using WordPress Settings API
			foreach ( $errors as $field => $message ) {
				add_settings_error(
					'campaignbridge_general',
					"campaignbridge_{$field}",
					$message,
					'error'
				);
			}
		}

		return self::sanitize_settings( $input );
	}

	/**
	 * Validate provider settings for WordPress Settings API.
	 *
	 * @since 0.1.0
	 * @param array $input Input data to validate.
	 * @return array Validated and sanitized settings.
	 */
	public static function validate_provider_settings_callback( array $input ): array {
		$errors = self::validate_provider_settings( $input );

		if ( ! empty( $errors ) ) {
			// Add validation errors using WordPress Settings API
			foreach ( $errors as $field => $message ) {
				add_settings_error(
					'campaignbridge_providers',
					"campaignbridge_{$field}",
					$message,
					'error'
				);
			}
		}

		return self::sanitize_settings( $input );
	}



	/**
	 * Validate general settings fields.
	 *
	 * @since 0.1.0
	 * @param array $settings Settings to validate.
	 * @return array Validation errors.
	 */
	private static function validate_general_settings( array $settings ): array {
		$errors = array();

		// Validate from name.
		if ( isset( $settings['from_name'] ) ) {
			$from_name = trim( $settings['from_name'] );
			if ( empty( $from_name ) ) {
				$errors['from_name'] = __( 'From Name is required.', 'campaignbridge' );
			} elseif ( strlen( $from_name ) > 100 ) {
				$errors['from_name'] = __( 'From Name must be less than 100 characters.', 'campaignbridge' );
			}
		}

		// Validate from email.
		if ( isset( $settings['from_email'] ) ) {
			$from_email = trim( $settings['from_email'] );
			if ( empty( $from_email ) ) {
				$errors['from_email'] = __( 'From Email is required.', 'campaignbridge' );
			} elseif ( ! is_email( $from_email ) ) {
				$errors['from_email'] = __( 'Please enter a valid email address.', 'campaignbridge' );
			}
		}

		return $errors;
	}

	/**
	 * Validate provider settings fields.
	 *
	 * @since 0.1.0
	 * @param array $settings Settings to validate.
	 * @return array Validation errors.
	 */
	private static function validate_provider_settings( array $settings ): array {
		$errors = array();

		// Validate provider selection.
		if ( isset( $settings['provider'] ) ) {
			$provider            = sanitize_key( $settings['provider'] );
			$available_providers = array_keys( Admin::get_providers() );

			if ( ! in_array( $provider, $available_providers, true ) ) {
				$errors['provider'] = __( 'Please select a valid email service provider.', 'campaignbridge' );
			}
		}

		// Provider-specific validation can be added here.
		// For now, we'll let individual providers handle their own validation.

		return $errors;
	}

	/**
	 * Sanitize settings input.
	 *
	 * @since 0.1.0
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( array $settings ): array {

		$sanitized = array();

		// Sanitize general settings.
		if ( isset( $settings['from_name'] ) ) {
			$sanitized['from_name'] = sanitize_text_field( $settings['from_name'] );
		}

		if ( isset( $settings['from_email'] ) ) {
			$sanitized['from_email'] = sanitize_email( $settings['from_email'] );
		}

		// Sanitize provider settings.
		if ( isset( $settings['provider'] ) ) {
			$sanitized['provider'] = sanitize_key( $settings['provider'] );
		}

		// Sanitize API key field.
		if ( isset( $settings['api_key'] ) ) {
			$sanitized['api_key'] = sanitize_text_field( $settings['api_key'] );
		}

		// Sanitize provider-specific settings.
		$providers = Admin::get_providers();
		if ( isset( $settings['provider'] ) && isset( $providers[ $settings['provider'] ] ) ) {
			$provider          = $providers[ $settings['provider'] ];
			$provider_settings = $provider->sanitize_settings( $settings );
			$sanitized         = array_merge( $sanitized, $provider_settings );
		}

		// Preserve any other existing settings that weren't in the current submission.
		$existing_settings = self::get_settings();
		foreach ( $existing_settings as $key => $value ) {
			if ( ! isset( $sanitized[ $key ] ) ) {
				$sanitized[ $key ] = $value;
			}
		}

		// Encrypt sensitive fields before saving.
		$sanitized = self::encrypt_sensitive_fields( $sanitized );

		return $sanitized;
	}

	/**
	 * Encrypt sensitive fields before saving to database.
	 *
	 * @param array $settings Settings array to encrypt.
	 * @return array Settings with sensitive fields encrypted.
	 */
	private static function encrypt_sensitive_fields( array $settings ): array {
		// Only encrypt if the encryption system is available and secure
		if ( ! class_exists( '\CampaignBridge\Core\Api_Key_Encryption' ) ) {
			return $settings;
		}

		$security_check = \CampaignBridge\Core\Api_Key_Encryption::security_check();
		if ( ! $security_check['secure'] ) {
			return $settings;
		}

		// Fields that should be encrypted
		$sensitive_fields = array( 'api_key', 'secret', 'password', 'token' );

		foreach ( $sensitive_fields as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				$value = $settings[ $field ];

				// Skip encryption for empty values or masked values (containing •)
				if ( empty( $value ) || strpos( $value, '•' ) !== false ) {
					// Clear empty or masked values
					$settings[ $field ] = '';
					continue;
				}

				// Check if it's already encrypted
				if ( ! \CampaignBridge\Core\Api_Key_Encryption::is_encrypted_value( $value ) ) {
					try {
						// Encrypt the value
						$encrypted_value = \CampaignBridge\Core\Api_Key_Encryption::encrypt( $value );
						if ( ! empty( $encrypted_value ) ) {
							$settings[ $field ] = $encrypted_value;
						}
					} catch ( \Throwable $e ) {
						// Log encryption failure but don't fail the save
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log(
								sprintf(
									'CampaignBridge: Failed to encrypt field "%s": %s',
									$field,
									$e->getMessage()
								)
							);
						}
					}
				}
			}
		}

		return $settings;
	}

}
