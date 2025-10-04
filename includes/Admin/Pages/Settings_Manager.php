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
			// Store validation errors for display using WordPress Settings API
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
			// Store validation errors for display using WordPress Settings API
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
	 * Update plugin settings with validation and sanitization.
	 * This method is kept for backward compatibility but delegates to Settings API.
	 *
	 * @since 0.1.0
	 * @param array  $new_settings New settings to save.
	 * @param string $current_tab  Current active tab for context-aware validation.
	 * @return bool True on success, false on failure.
	 * @deprecated Use WordPress Settings API instead.
	 */
	public static function update_settings( array $new_settings, string $current_tab = '' ): bool {
		// For backward compatibility, still handle direct calls
		// but prefer Settings API for new implementations

		// Get existing settings to preserve other tabs' data.
		$existing_settings = self::get_settings();

		// Merge new settings with existing ones to preserve all data.
		$merged_settings = self::merge_settings_by_tab( $existing_settings, $new_settings );

		// Validate settings based on current tab.
		$validation_errors = self::validate_settings( $merged_settings, $current_tab );

		if ( ! empty( $validation_errors ) ) {
			// Store validation errors for display.
			self::store_validation_errors( $validation_errors );
			return false;
		}

		// Sanitize the merged settings.
		$sanitized_settings = self::sanitize_settings( $merged_settings );

		// Update the option.
		$success = update_option( self::OPTION_NAME, $sanitized_settings );

		// update_option returns false if the new value is the same as the existing value.
		// In this case, we should still consider it a success if no validation errors occurred.
		if ( ! $success && empty( $validation_errors ) ) {
			$existing_settings = self::get_settings();
			$success           = $existing_settings === $sanitized_settings;
		}

		if ( $success ) {
			// Clear validation errors on successful save.
			self::clear_validation_errors();
		}

		return $success;
	}

	/**
	 * Merge settings to prevent data loss between tabs.
	 *
	 * @since 0.1.0
	 * @param array $existing_settings Existing settings from database.
	 * @param array $new_settings      New settings being submitted.
	 * @return array Merged settings.
	 */
	private static function merge_settings_by_tab( array $existing_settings, array $new_settings ): array {
		// Simply merge all settings to preserve everything.
		// This prevents one tab from clearing another's data.
		return array_merge( $existing_settings, $new_settings );
	}

	/**
	 * Validate settings based on current tab context.
	 *
	 * @since 0.1.0
	 * @param array  $settings    Settings to validate.
	 * @param string $current_tab Current tab for context-aware validation.
	 * @return array Array of validation errors, empty if no errors.
	 */
	private static function validate_settings( array $settings, string $current_tab ): array {
		$errors = array();

		// General tab validation.
		if ( 'general' === $current_tab || empty( $current_tab ) ) {
			$errors = array_merge( $errors, self::validate_general_settings( $settings ) );
		}

		// Providers tab validation.
		if ( 'providers' === $current_tab || empty( $current_tab ) ) {
			$errors = array_merge( $errors, self::validate_provider_settings( $settings ) );
		}

		return $errors;
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

	/**
	 * Store validation errors for display.
	 *
	 * @since 0.1.0
	 * @param array $errors Validation errors.
	 * @return void
	 */
	private static function store_validation_errors( array $errors ): void {
		set_transient( 'campaignbridge_validation_errors', $errors, 300 ); // 5 minutes
	}

	/**
	 * Clear stored validation errors.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private static function clear_validation_errors(): void {
		delete_transient( 'campaignbridge_validation_errors' );
	}

	/**
	 * Get stored validation errors.
	 *
	 * @since 0.1.0
	 * @return array Validation errors or empty array.
	 */
	public static function get_validation_errors(): array {
		return get_transient( 'campaignbridge_validation_errors' ) ?: array();
	}

	/**
	 * Display validation errors as admin notices.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function display_validation_errors(): void {
		$errors = self::get_validation_errors();

		if ( empty( $errors ) ) {
			return;
		}

		foreach ( $errors as $field => $message ) {
			add_settings_error(
				'campaignbridge_validation',
				"campaignbridge_{$field}",
				$message,
				'error'
			);
		}

		settings_errors( 'campaignbridge_validation' );
	}
}
