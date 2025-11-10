<?php
/**
 * Abstract REST Controller for CampaignBridge.
 *
 * Base class for REST API controllers providing common functionality
 * like permission checking, input validation, and response formatting.
 *
 * @package CampaignBridge\REST
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract REST Controller class.
 *
 * Provides common functionality for REST API controllers including
 * permission checking, rate limiting, and response formatting.
 */
abstract class Abstract_Rest_Controller {
	/**
	 * Check if current user can manage plugin settings.
	 *
	 * @return bool True if user has required capability.
	 */
	public static function can_manage(): bool {
		return \current_user_can( Rest_Constants::MANAGE_CAPABILITY );
	}

	/**
	 * Validate post type parameter.
	 *
	 * @param string $value Post type value to validate.
	 * @return bool True if valid post type exists.
	 */
	public static function validate_post_type( string $value ): bool {
		// Only allow valid post types that exist in WordPress.
		$post_types = get_post_types( array( 'public' => true ) );
		return array_key_exists( $value, $post_types );
	}

	/**
	 * Sanitize post type parameter.
	 *
	 * @param string $value Raw post type value.
	 * @return string Sanitized post type.
	 */
	public static function sanitize_post_type( string $value ): string {
		return sanitize_key( $value );
	}

	/**
	 * Validate and sanitize post type from request.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request object.
	 * @return string Validated post type or default.
	 */
	protected static function get_validated_post_type( WP_REST_Request $request ): string {
		$raw_post_type = $request->get_param( 'post_type' );
		$post_type     = $raw_post_type ? self::sanitize_post_type( $raw_post_type ) : Rest_Constants::DEFAULT_POST_TYPE;

		// Additional validation: ensure post type contains only alphanumeric characters and underscores.
		if ( ! empty( $raw_post_type ) && ! preg_match( '/^[a-zA-Z0-9_]+$/', $post_type ) ) {
			return Rest_Constants::DEFAULT_POST_TYPE;
		}

		if ( ! post_type_exists( $post_type ) ) {
			return Rest_Constants::DEFAULT_POST_TYPE;
		}

		return $post_type;
	}

	/**
	 * Check if post type is allowed based on settings.
	 *
	 * @param string $post_type Post type to check.
	 * @param string $option_name Settings option name.
	 * @return bool True if post type is allowed.
	 */
	protected static function is_post_type_allowed( string $post_type, string $option_name ): bool {
		$post_types_settings = \CampaignBridge\Core\Storage::get_option( $option_name, array() );
		$allowed_types       = isset( $post_types_settings['included_post_types'] ) && is_array( $post_types_settings['included_post_types'] )
			? array_map( 'sanitize_key', $post_types_settings['included_post_types'] )
			: array(); // If no specific types configured, allow all.

		// If specific types are configured, check if the requested type is allowed.
		if ( ! empty( $allowed_types ) && ! in_array( $post_type, $allowed_types, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Ensure response is properly formatted.
	 *
	 * @param mixed $data Response data.
	 * @return WP_REST_Response|\WP_Error Properly formatted response.
	 */
	protected static function ensure_response( $data ): WP_REST_Response|\WP_Error {
		return rest_ensure_response( $data );
	}

	/**
	 * Create a standardized error response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return \WP_Error Error object.
	 */
	protected static function create_error( string $code, string $message, int $status = Rest_Constants::HTTP_BAD_REQUEST ): \WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
