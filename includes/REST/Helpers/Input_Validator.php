<?php
/**
 * Input Validator for CampaignBridge REST API.
 *
 * Handles input validation for REST API endpoints with security-focused
 * validation rules and error handling.
 *
 * @package CampaignBridge\REST\Helpers
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\REST\Helpers;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Input Validator class.
 *
 * Provides validation methods for REST API input parameters
 * with proper error handling and security considerations.
 */
class Input_Validator {
	/**
	 * Validate post type with additional security checks.
	 *
	 * @param string $post_type Raw post type value.
	 * @return string|WP_Error Validated post type or error.
	 */
	public static function validate_post_type_secure( string $post_type ): string|WP_Error {
		// Sanitize first.
		$sanitized = sanitize_key( $post_type );

		// Additional validation: ensure post type contains only alphanumeric characters and underscores.
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $sanitized ) ) {
			return new WP_Error(
				'invalid_post_type_format',
				__( 'Post type contains invalid characters.', 'campaignbridge' ),
				array( 'status' => 400 )
			);
		}

		if ( ! post_type_exists( $sanitized ) ) {
			return new WP_Error(
				'invalid_post_type',
				__( 'Invalid post type.', 'campaignbridge' ),
				array( 'status' => 400 )
			);
		}

		return $sanitized;
	}

	/**
	 * Validate that a post type is allowed based on settings.
	 *
	 * @param string $post_type   Post type to check.
	 * @param string $option_name Settings option name.
	 * @return bool|WP_Error True if allowed, error if not.
	 */
	public static function validate_post_type_allowed( string $post_type, string $option_name ): bool|WP_Error {
		$post_types_settings = \CampaignBridge\Core\Storage::get_option( $option_name, array() );
		$allowed_types       = isset( $post_types_settings['included_post_types'] ) && is_array( $post_types_settings['included_post_types'] )
			? array_map( 'sanitize_key', $post_types_settings['included_post_types'] )
			: array(); // If no specific types configured, allow all.

		// If specific types are configured, check if the requested type is allowed.
		if ( ! empty( $allowed_types ) && ! in_array( $post_type, $allowed_types, true ) ) {
			return new WP_Error(
				'post_type_not_allowed',
				__( 'Post type not allowed for this operation.', 'campaignbridge' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}
}
