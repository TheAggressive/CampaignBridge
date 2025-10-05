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
		$raw_settings = get_option( self::OPTION_NAME, array() );

		// Decrypt sensitive fields for display/use
		return self::decrypt_sensitive_fields( $raw_settings );
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
	 * Validate settings and add errors to WordPress Settings API.
	 *
	 * @since 0.1.0
	 * @param array $settings Settings to validate.
	 * @return void
	 */
	private static function validate_settings( array $settings ): void {
		// General tab validation.
		$general_errors = self::validate_general_settings( $settings );
		foreach ( $general_errors as $field => $message ) {
			add_settings_error(
				'campaignbridge_general',
				"campaignbridge_{$field}",
				$message,
				'error'
			);
		}

		// Providers tab validation.
		$provider_errors = self::validate_provider_settings( $settings );
		foreach ( $provider_errors as $field => $message ) {
			add_settings_error(
				'campaignbridge_providers',
				"campaignbridge_{$field}",
				$message,
				'error'
			);
		}
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
	 * Sanitize settings input with validation and error handling.
	 *
	 * @since 0.1.0
	 * @param array $settings Settings to sanitize and validate.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( array $settings ): array {
		// Get existing settings to preserve other tabs' data
		$existing_settings = get_option( self::OPTION_NAME, array() );

		// Merge new settings with existing ones to preserve all data
		$merged_settings = array_merge( $existing_settings, $settings );

		// Encrypt sensitive fields before saving
		$merged_settings = self::encrypt_sensitive_fields( $merged_settings );

		// Return the complete merged settings for WordPress to save
		return $merged_settings;
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
			error_log( 'CampaignBridge: Api_Key_Encryption class not found' );
			return $settings;
		}

		$security_check = \CampaignBridge\Core\Api_Key_Encryption::security_check();
		if ( ! $security_check['secure'] ) {
			error_log( 'CampaignBridge: Security check failed: ' . print_r( $security_check, true ) );
			return $settings;
		}

		error_log( 'CampaignBridge: Encryption system available and secure' );

		// Fields that should be encrypted
		$sensitive_fields = array( 'api_key', 'secret', 'password', 'token' );

		foreach ( $sensitive_fields as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				$value = $settings[ $field ];
				error_log( "CampaignBridge: Processing field '$field' with value: " . substr( $value, 0, 10 ) . '...' );

				// Skip encryption for empty values or masked values (containing •)
				if ( empty( $value ) || strpos( $value, '•' ) !== false ) {
					// Clear empty or masked values
					$settings[ $field ] = '';
					error_log( "CampaignBridge: Skipped encryption for field '$field' (empty or masked)" );
					continue;
				}

				// Check if it's already encrypted
				if ( ! \CampaignBridge\Core\Api_Key_Encryption::is_encrypted_value( $value ) ) {
					try {
						error_log( "CampaignBridge: Encrypting field '$field'" );
						// Encrypt the value
						$encrypted_value = \CampaignBridge\Core\Api_Key_Encryption::encrypt( $value );
						if ( ! empty( $encrypted_value ) ) {
							$settings[ $field ] = $encrypted_value;
							error_log( "CampaignBridge: Successfully encrypted field '$field'" );
						} else {
							error_log( "CampaignBridge: Encryption returned empty value for field '$field'" );
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
				} else {
					error_log( "CampaignBridge: Field '$field' is already encrypted" );
				}
			}
		}

		return $settings;
	}

	/**
	 * Decrypt sensitive fields when retrieving from database.
	 *
	 * @param array $settings Settings array to decrypt.
	 * @return array Settings with sensitive fields decrypted.
	 */
	private static function decrypt_sensitive_fields( array $settings ): array {
		// Only decrypt if the encryption system is available and secure
		if ( ! class_exists( '\CampaignBridge\Core\Api_Key_Encryption' ) ) {
			error_log( 'CampaignBridge: Api_Key_Encryption class not found for decryption' );
			return $settings;
		}

		$security_check = \CampaignBridge\Core\Api_Key_Encryption::security_check();
		if ( ! $security_check['secure'] ) {
			error_log( 'CampaignBridge: Security check failed for decryption: ' . print_r( $security_check, true ) );
			return $settings;
		}

		error_log( 'CampaignBridge: Decryption system available and secure' );

		// Fields that should be decrypted
		$sensitive_fields = array( 'api_key', 'secret', 'password', 'token' );

		foreach ( $sensitive_fields as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				$value = $settings[ $field ];
				error_log( "CampaignBridge: Processing decryption for field '$field' with value: " . substr( $value, 0, 20 ) . '...' );

				// Skip decryption for empty values
				if ( empty( $value ) ) {
					error_log( "CampaignBridge: Skipped decryption for field '$field' (empty)" );
					continue;
				}

				// Check if it's encrypted
				if ( \CampaignBridge\Core\Api_Key_Encryption::is_encrypted_value( $value ) ) {
					try {
						error_log( "CampaignBridge: Decrypting field '$field'" );
						// Decrypt the value for display
						$decrypted_value = \CampaignBridge\Core\Api_Key_Encryption::decrypt_for_display( $value );
						if ( ! empty( $decrypted_value ) ) {
							$settings[ $field ] = $decrypted_value;
							error_log( "CampaignBridge: Successfully decrypted field '$field'" );
						} else {
							error_log( "CampaignBridge: Decryption returned empty value for field '$field'" );
						}
					} catch ( \Throwable $e ) {
						// Log decryption failure but don't fail the retrieval
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log(
								sprintf(
									'CampaignBridge: Failed to decrypt field "%s": %s',
									$field,
									$e->getMessage()
								)
							);
						}
						// Keep the encrypted value if decryption fails
					}
				} else {
					error_log( "CampaignBridge: Field '$field' is not encrypted" );
				}
			}
		}

		return $settings;
	}

}
