<?php
/**
 * Settings Handler for CampaignBridge.
 *
 * Handles sanitization and validation of plugin settings with comprehensive
 * security checks and data integrity validation.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

use CampaignBridge\Core\Api_Key_Encryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Handler Class
 *
 * Manages plugin settings sanitization and validation.
 */
class Settings_Handler {
	/**
	 * Default email service provider.
	 */
	private const DEFAULT_PROVIDER = 'html';

	/**
	 * Sanitize and validate submitted plugin settings.
	 *
	 * This method processes all submitted form data to ensure data integrity,
	 * security, and consistency. It applies WordPress sanitization functions,
	 * validates data types, and provides intelligent fallbacks for missing
	 * or invalid data.
	 *
	 * @since 0.1.0
	 * @param array $input Raw submitted form values from the settings form.
	 * @return array Cleaned, validated, and sanitized settings array ready for storage.
	 */
	public function sanitize( array $input ): array {
		// Skip nonce verification only during migration to avoid wp_die() errors.
		if ( ! ( isset( $GLOBALS['campaignbridge_migration_mode'] ) && $GLOBALS['campaignbridge_migration_mode'] ) ) {
			// Verify nonce for CSRF protection.
			// Check for the appropriate nonce action based on the current request.
			$nonce_action = 'campaignbridge-options'; // fallback.

			// Check if this is from a specific tab.
			if ( isset( $_POST['option_page'] ) ) {
				$option_page = sanitize_key( wp_unslash( $_POST['option_page'] ) );
				if ( 'campaignbridge_general' === $option_page ) {
					$nonce_action = 'campaignbridge_general-options';
				} elseif ( 'campaignbridge_providers' === $option_page ) {
					$nonce_action = 'campaignbridge_providers-options';
				}
			}

			check_admin_referer( $nonce_action );
		}

		return $this->sanitize_settings( $input );
	}

	/**
	 * Sanitize settings array with comprehensive validation.
	 *
	 * @param array $input Raw input array.
	 * @return array Sanitized settings array.
	 */
	private function sanitize_settings( array $input ): array {
		$clean                       = array();
		$previous                    = get_option( 'campaignbridge_settings', array() );
		$clean['provider']           = $this->sanitize_provider( $input );
		$clean['api_key']            = $this->sanitize_api_key( $input, $previous );
		$clean['audience_id']        = $this->sanitize_audience_id( $input );
		$clean['from_name']          = $this->sanitize_from_name( $input );
		$clean['from_email']         = $this->sanitize_from_email( $input );
		$clean['exclude_post_types'] = $this->sanitize_post_types( $input );

		return $clean;
	}

	/**
	 * Sanitize and validate the email provider selection.
	 *
	 * @param array $input Raw input array.
	 * @return string Sanitized provider name.
	 */
	private function sanitize_provider( array $input ): string {
		$provider = $input['provider'] ?? self::DEFAULT_PROVIDER;
		return sanitize_key( $provider );
	}

	/**
	 * Sanitize and validate the API key with proper Mailchimp format validation.
	 *
	 * Handles both plaintext input (new keys) and encrypted storage (existing keys).
	 * New API keys are encrypted before storage for maximum security.
	 *
	 * @param array $input Raw input array.
	 * @param array $previous Previous settings with potentially encrypted API key.
	 * @return string Encrypted API key for storage or empty string.
	 * @throws \RuntimeException If encryption/decryption integrity check fails.
	 */
	private function sanitize_api_key( array $input, array $previous ): string {
		$posted_api_key    = $input['api_key'] ?? '';
		$selected_provider = $input['provider'] ?? '';

		// Skip API key processing for providers that don't need it (like HTML export).
		if ( 'html' === $selected_provider ) {
			return ''; // HTML provider doesn't need API keys.
		}

		// If no new API key provided, return existing encrypted key (if any).
		if ( '' === $posted_api_key ) {
			if ( isset( $previous['api_key'] ) && ! empty( $previous['api_key'] ) ) {
				// Verify existing key can be decrypted (validates it's properly encrypted).
				try {
					$decrypted = Api_Key_Encryption::decrypt( $previous['api_key'] );
					if ( ! empty( $decrypted ) ) {
						return $previous['api_key']; // Return encrypted version for storage.
					}
				} catch ( \Throwable $e ) {
					// Log error but don't expose details.
					error_log( 'CampaignBridge: Invalid encrypted API key detected in settings' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security event logging.
					add_settings_error(
						'campaignbridge_messages',
						'campaignbridge_api_key_corrupted',
						__( 'Your stored API key appears to be corrupted. Please enter it again.', 'campaignbridge' ),
						'error'
					);
				}
			}
			return '';
		}

		// Sanitize the input API key.
		$sanitized_key = sanitize_text_field( $posted_api_key );

		// Validate API key format and length.
		if ( ! empty( $sanitized_key ) ) {
			// Get provider-specific validation pattern.
			$provider_pattern = $this->get_provider_api_key_pattern( $input['provider'] ?? '' );

			if ( ! Api_Key_Encryption::is_valid_api_key_format( $sanitized_key, $provider_pattern ) ) {
				add_settings_error(
					'campaignbridge_messages',
					'campaignbridge_api_key_invalid',
					__( 'Invalid API key format for the selected provider.', 'campaignbridge' ),
					'error'
				);
				return isset( $previous['api_key'] ) ? $previous['api_key'] : '';
			}

			// Encrypt the API key before storage for ultra-secure handling.
			try {
				$encrypted_key = Api_Key_Encryption::encrypt( $sanitized_key );
				if ( ! empty( $encrypted_key ) ) {
					// Verify encryption/decryption round-trip for integrity.
					$decrypted_check = Api_Key_Encryption::decrypt( $encrypted_key );
					if ( $decrypted_check === $sanitized_key ) {
						return $encrypted_key;
					} else {
						throw new \RuntimeException( 'Encryption/decryption integrity check failed' );
					}
				}
			} catch ( \Throwable $e ) {
				// Log error for debugging but don't expose sensitive details.
				error_log(
					sprintf(
						'CampaignBridge API key encryption failed: %s',
						$e->getMessage()
					)
				);

				add_settings_error(
					'campaignbridge_messages',
					'campaignbridge_encryption_error',
					__( 'Failed to securely store API key. Please try again or contact support.', 'campaignbridge' ),
					'error'
				);
				return isset( $previous['api_key'] ) ? $previous['api_key'] : '';
			}
		}

		return '';
	}

	/**
	 * Sanitize the audience/list ID with alphanumeric validation.
	 *
	 * @param array $input Raw input array.
	 * @return string Sanitized audience ID.
	 */
	private function sanitize_audience_id( array $input ): string {
		$audience_id = $input['audience_id'] ?? '';

		// Validate that audience ID is alphanumeric (Mailchimp audience IDs are alphanumeric).
		if ( ! empty( $audience_id ) && ! preg_match( '/^[A-Za-z0-9]+$/', $audience_id ) ) {
			add_settings_error(
				'campaignbridge_messages',
				'campaignbridge_audience_id_invalid',
				__( 'Invalid audience ID format. Audience IDs should contain only letters and numbers.', 'campaignbridge' ),
				'error'
			);
			return '';
		}

		return sanitize_text_field( $audience_id );
	}

	/**
	 * Sanitize the default sender name.
	 *
	 * @param array $input Raw input array.
	 * @return string Sanitized sender name.
	 */
	private function sanitize_from_name( array $input ): string {
		return sanitize_text_field( $input['from_name'] ?? '' );
	}

	/**
	 * Sanitize the default sender email with enhanced validation.
	 *
	 * @param array $input Raw input array.
	 * @return string Sanitized sender email.
	 */
	private function sanitize_from_email( array $input ): string {
		$email = $input['from_email'] ?? '';

		// Early validation: check for basic email format before sanitization.
		if ( ! empty( $email ) && ! preg_match( '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email ) ) {
			add_settings_error(
				'campaignbridge_messages',
				'campaignbridge_from_email_invalid',
				__( 'Please enter a valid email address.', 'campaignbridge' ),
				'error'
			);
			return '';
		}

		$sanitized_email = sanitize_email( $email );

		// Additional validation after sanitization.
		if ( ! empty( $sanitized_email ) && ! is_email( $sanitized_email ) ) {
			add_settings_error(
				'campaignbridge_messages',
				'campaignbridge_from_email_invalid',
				__( 'Please enter a valid email address.', 'campaignbridge' ),
				'error'
			);
			return '';
		}

		return $sanitized_email;
	}

	/**
	 * Sanitize and validate post type exclusions.
	 *
	 * @param array $input Raw input array.
	 * @return array Array of excluded post types.
	 */
	private function sanitize_post_types( array $input ): array {
		$excluded = array();

		if ( isset( $input['included_post_types'] ) && is_array( $input['included_post_types'] ) ) {
			$included = array();
			foreach ( $input['included_post_types'] as $pt ) {
				$pt = sanitize_key( $pt );
				if ( post_type_exists( $pt ) ) {
					$included[] = $pt;
				}
			}

			$public_types = get_post_types( array( 'public' => true ), 'names' );
			foreach ( $public_types as $pt ) {
				if ( ! in_array( $pt, $included, true ) ) {
					$excluded[] = $pt;
				}
			}
		}

		return $excluded;
	}

	/**
	 * Get provider-specific API key validation pattern.
	 *
	 * @param string $provider_slug The provider slug.
	 * @return string The regex pattern for the provider.
	 */
	private function get_provider_api_key_pattern( string $provider_slug ): string {
		// Get providers from global plugin instance.
		global $campaignbridge_plugin;
		$providers = $campaignbridge_plugin->providers ?? array();

		if ( isset( $providers[ $provider_slug ] ) && method_exists( $providers[ $provider_slug ], 'get_api_key_pattern' ) ) {
			return $providers[ $provider_slug ]->get_api_key_pattern();
		}

		// Fallback to generic pattern if provider not found.
		return '/^[a-zA-Z0-9_-]{20,}$/';
	}
}
