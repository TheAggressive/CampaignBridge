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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Handler Class
 *
 * Manages plugin settings sanitization and validation.
 */
class SettingsHandler {
	/**
	 * Default email service provider.
	 */
	private const DEFAULT_PROVIDER = 'mailchimp';

	/**
	 * API key minimum length.
	 */
	private const API_KEY_MIN_LENGTH = 10;

	/**
	 * API key maximum length.
	 */
	private const API_KEY_MAX_LENGTH = 100;
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
		// Verify nonce for CSRF protection.
		check_admin_referer( 'campaignbridge-options' );

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
		$clean['provider']           = $this->sanitize_provider( $input, $previous );
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
	 * @param array $previous Previous settings.
	 * @return string Sanitized provider name.
	 */
	private function sanitize_provider( array $input, array $previous ): string {
		$provider = $input['provider'] ?? self::DEFAULT_PROVIDER;
		return sanitize_key( $provider );
	}

	/**
	 * Sanitize and validate the API key with length and security checks.
	 *
	 * @param array $input Raw input array.
	 * @param array $previous Previous settings.
	 * @return string Sanitized API key or empty string.
	 */
	private function sanitize_api_key( array $input, array $previous ): string {
		$posted_api_key = $input['api_key'] ?? '';

		// Handle API key with additional security measures.
		if ( '' === $posted_api_key && isset( $previous['api_key'] ) ) {
			return $previous['api_key'];
		}

		$sanitized_key = sanitize_text_field( $posted_api_key );

		// Validate API key format and length.
		if ( ! empty( $sanitized_key ) ) {
			// Basic length check to prevent abuse.
			if ( strlen( $sanitized_key ) < self::API_KEY_MIN_LENGTH || strlen( $sanitized_key ) > self::API_KEY_MAX_LENGTH ) {
				add_settings_error(
					'campaignbridge_messages',
					'campaignbridge_api_key_length',
					sprintf(
						// translators: %1$d is minimum length, %2$d is maximum length.
						__( 'API key must be between %1$d and %2$d characters.', 'campaignbridge' ),
						self::API_KEY_MIN_LENGTH,
						self::API_KEY_MAX_LENGTH
					),
					'error'
				);
				return isset( $previous['api_key'] ) ? $previous['api_key'] : '';
			}
			return $sanitized_key;
		}

		return '';
	}

	/**
	 * Sanitize the audience/list ID.
	 *
	 * @param array $input Raw input array.
	 * @return string Sanitized audience ID.
	 */
	private function sanitize_audience_id( array $input ): string {
		return sanitize_text_field( $input['audience_id'] ?? '' );
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
	 * Sanitize the default sender email.
	 *
	 * @param array $input Raw input array.
	 * @return string Sanitized sender email.
	 */
	private function sanitize_from_email( array $input ): string {
		$email = sanitize_email( $input['from_email'] ?? '' );

		// Validate email format.
		if ( ! empty( $email ) && ! is_email( $email ) ) {
			add_settings_error(
				'campaignbridge_messages',
				'campaignbridge_from_email_invalid',
				__( 'Please enter a valid email address.', 'campaignbridge' ),
				'error'
			);
			return '';
		}

		return $email;
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
}
