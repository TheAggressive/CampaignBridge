<?php
/**
 * General REST API Routes for CampaignBridge Admin Operations.
 *
 * Provides REST API endpoints for posts and post types management
 * with rate limiting and security features.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use CampaignBridge\REST\Helpers\Response_Formatter;
use CampaignBridge\REST\Helpers\Input_Validator;
use CampaignBridge\Admin\REST\Form_Rest_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName
/**
 * REST API routes for CampaignBridge admin operations.
 */
class Routes extends Abstract_Rest_Controller {
	/**
	 * Endpoint paths
	 */
	private const ENDPOINT_POSTS         = '/posts';
	private const ENDPOINT_POST_TYPES    = '/post-types';
	private const ENDPOINT_DECRYPT_FIELD = '/decrypt-field';
	private const ENDPOINT_ENCRYPT_FIELD = '/encrypt-field';



	/**
	 * Form REST controller instance.
	 *
	 * @var Form_Rest_Controller
	 */
	private static Form_Rest_Controller $form_controller;

	/**
	 * Editor settings routes instance.
	 *
	 * @var Editor_Settings_Routes
	 */
	private static Editor_Settings_Routes $editor_settings_routes;

	/**
	 * Initialize shared state.
	 *
	 * @param string               $option_name Options key used by the plugin.
	 * @param array<string, mixed> $_providers  Registered providers map (reserved for future use).
	 * @return void
	 */
	public static function init( string $option_name, array $_providers ): void {
		// $_providers parameter accepted for future extensibility but not currently used.

		self::$editor_settings_routes = new Editor_Settings_Routes();
		self::$form_controller        = new Form_Rest_Controller();

		// Register AJAX handlers.
		\add_action( 'wp_ajax_campaignbridge_evaluate_conditions', array( self::$form_controller, 'handle_ajax_evaluate_conditions' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register() {
		static $registered = false;

		// Use static variable for function-level idempotency.
		if ( $registered ) {
			return;
		}
		$registered = true;

		self::register_posts_route();
		self::register_post_types_route();

		// Register encrypted field routes.
		self::register_encrypted_field_routes();

		// Mapping slots endpoint removed (block-based workflow).

		// Register editor settings routes.
		self::$editor_settings_routes->register();
	}

	/**
	 * Register the posts endpoint.
	 *
	 * @return void
	 */
	private static function register_posts_route(): void {
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::ENDPOINT_POSTS,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_posts' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'post_type' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => array( __CLASS__, 'sanitize_post_type' ),
						'validate_callback' => array( __CLASS__, 'validate_post_type' ),
					),
				),
			)
		);
	}

	/**
	 * Register the post types endpoint.
	 *
	 * @return void
	 */
	private static function register_post_types_route(): void {
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::ENDPOINT_POST_TYPES,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_post_types' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	/**
	 * Register the encrypted field endpoints.
	 *
	 * @return void
	 */
	private static function register_encrypted_field_routes(): void {
		// Decrypt field endpoint.
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::ENDPOINT_DECRYPT_FIELD,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'r_decrypt_field' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'encrypted_value' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( __CLASS__, 'validate_encrypted_value' ),
					),
				),
			)
		);

		// Encrypt field endpoint.
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::ENDPOINT_ENCRYPT_FIELD,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'r_encrypt_field' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'field_id'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'new_value' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( __CLASS__, 'validate_field_value' ),
					),
				),
			)
		);
	}

	/**
	 * GET /posts endpoint.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $req Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function r_posts(
		WP_REST_Request $req
	): \WP_REST_Response|\WP_Error {
		// Rate limiting check.
		$rate_limit = Rate_Limiter::check_rate_limit( 'posts' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		// Validate and get post type.
		$raw_post_type = $req->get_param( 'post_type' );
		$post_type     = $raw_post_type ? $raw_post_type : Rest_Constants::DEFAULT_POST_TYPE;

		$validated_post_type = Input_Validator::validate_post_type_secure( $post_type );
		if ( is_wp_error( $validated_post_type ) ) {
			return $validated_post_type;
		}
		$post_type = $validated_post_type;

		// Check if post type is allowed.
		$allowed_check = Input_Validator::validate_post_type_allowed( $post_type, 'campaignbridge_included_post_types' );
		if ( is_wp_error( $allowed_check ) ) {
			return $allowed_check;
		}

		// Get and format posts.
		$post_ids = get_posts( self::get_posts_query_args( $post_type ) );
		// Since we use 'fields' => 'ids' in query args, all values should be integers.

		/**
		 * Post IDs from the query.
		 *
		 * @var array<int> $post_ids
		 */
		$int_post_ids = array_map( 'intval', $post_ids );
		$items        = Response_Formatter::format_posts_response( $int_post_ids );

		return self::ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Get query arguments for posts endpoint.
	 *
	 * @param string $post_type Post type to query.
	 * @return array<string, mixed> Query arguments array.
	 */
	private static function get_posts_query_args( string $post_type ): array {
		return array(
			'post_type'              => $post_type,
			'posts_per_page'         => Rest_Constants::POSTS_PER_PAGE,
			'post_status'            => 'publish',
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'ignore_sticky_posts'    => true,
			'suppress_filters'       => true,
		);
	}

	/**
	 * GET /post-types endpoint.
	 * Returns allowed public post types based on settings (excludes unchecked types).
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	/**
	 * GET /post-types endpoint.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function r_post_types() {
		// Rate limiting check.
		$rate_limit = Rate_Limiter::check_rate_limit( 'post_types' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		// Get Post_Types settings instead of main settings.
		$post_types_settings = \CampaignBridge\Core\Storage::get_option( 'campaignbridge_included_post_types', array() );

		// Get included post types (default to all public if none specified).
		$included_types = array();
		if ( isset( $post_types_settings['included_post_types'] ) && is_array( $post_types_settings['included_post_types'] ) ) {
			foreach ( $post_types_settings['included_post_types'] as $post_type ) {
				$sanitized = sanitize_key( $post_type );
				// Additional validation: ensure post type contains only alphanumeric characters and underscores.
				if ( preg_match( '/^[a-zA-Z0-9_]+$/', $sanitized ) ) {
					$included_types[] = $sanitized;
				}
			}
		}

		// If no post types are explicitly included, include all public post types (excluding page and attachment).
		if ( empty( $included_types ) ) {
			$all_public = get_post_types( array( 'public' => true ), 'names' );
			// Exclude page and attachment (media) post types.
			$excluded_types = array( 'page', 'attachment' );
			$all_public     = array_diff( $all_public, $excluded_types );
			$included_types = array_values( $all_public );
		}

		// Get post type objects and filter.
		$objs = get_post_types( array( 'public' => true ), 'objects' );
		$objs = array_filter(
			$objs,
			function ( $obj ) use ( $included_types ) {
				return in_array( $obj->name, $included_types, true ) &&
						! in_array( $obj->name, array( 'page', 'attachment' ), true );
			}
		);

		// Format response.
		$items = array();
		foreach ( $objs as $obj ) {
			$items[] = array(
				'id'    => (string) $obj->name,
				'label' => esc_html( (string) $obj->labels->singular_name ),
			);
		}

		usort( $items, fn( $a, $b ) => strcasecmp( (string) $a['label'], (string) $b['label'] ) );

		return self::ensure_response( array( 'items' => $items ) );
	}

	/**
	 * POST /decrypt-field endpoint.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The REST request.
	 * @return \WP_REST_Response|\WP_Error Response or error.
	 * @throws \RuntimeException When decryption fails.
	 */
	public static function r_decrypt_field( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Verify nonce for CSRF protection.
		$nonce = $request->get_param( '_wpnonce' );
		if ( ! \wp_verify_nonce( $nonce, 'campaignbridge_encrypted_fields' ) ) {
			\CampaignBridge\Core\Error_Handler::error( 'Invalid nonce in decrypt request' );
			return new WP_Error( 'invalid_nonce', 'Security validation failed', array( 'status' => 403 ) );
		}

		// Rate limiting to prevent brute force attacks (max 10 requests per minute per user).
		$user_id        = get_current_user_id();
		$rate_limit_key = "decrypt_rate_limit_{$user_id}";
		$requests       = \CampaignBridge\Core\Storage::get_transient( $rate_limit_key );
		$requests       = $requests ?? 0;

		if ( $requests >= 10 ) {
			\CampaignBridge\Core\Error_Handler::warning( 'Rate limit exceeded for decrypt requests', array( 'user_id' => $user_id ) );
			return new WP_Error( 'rate_limit_exceeded', 'Too many requests. Please try again later.', array( 'status' => 429 ) );
		}

		\CampaignBridge\Core\Storage::set_transient( $rate_limit_key, $requests + 1, 60 ); // 1 minute window

		$encrypted_value = $request->get_param( 'encrypted_value' );
		try {
			// For admin operations, try multiple contexts since we don't know the original context.
			// Admin users should be able to decrypt fields they have access to.
			$decrypted  = null;
			$last_error = null;

			// Try contexts in order of restrictiveness.
			$contexts_to_try = array( 'public', 'personal', 'sensitive', 'api_key' );

			foreach ( $contexts_to_try as $context ) {
				try {
					$decrypted = \CampaignBridge\Core\Encryption::decrypt_for_context( $encrypted_value, $context );
					break; // Success - use this decrypted value.
				} catch ( \RuntimeException $e ) {
					$last_error = $e;
					continue; // Try next context.
				}
			}

			// If no context worked, throw the last error.
			if ( null === $decrypted ) {
				throw $last_error ?? new \RuntimeException( 'Unable to decrypt with any available context' );
			}

			// Ensure the decrypted value is safe for JSON transmission.
			// Remove any potential binary data or problematic characters.
			$safe_decrypted = self::sanitize_decrypted_value( $decrypted );

			// Add small random delay to prevent timing attacks (10-50ms).
			usleep( wp_rand( 10000, 50000 ) );

			// Return response in the format JavaScript expects.
			return self::ensure_response(
				array(
					'success' => true,
					'data'    => array( 'decrypted' => $safe_decrypted ),
				)
			);
		} catch ( \RuntimeException $e ) {
			\CampaignBridge\Core\Error_Handler::error(
				'Failed to decrypt field value via REST API',
				array( 'error' => $e->getMessage() )
			);
			// Sanitize error message for production - don't expose internal details.
			$error_message = WP_DEBUG ? $e->getMessage() : 'Unable to process the encrypted data';
			return new WP_Error( 'decryption_failed', $error_message, array( 'status' => 400 ) );
		}
	}

	/**
	 * Sanitize decrypted value for safe JSON transmission.
	 *
	 * @param string $value The decrypted value.
	 * @return string The sanitized value.
	 */
	private static function sanitize_decrypted_value( string $value ): string {
		// Remove null bytes and other control characters that can break JSON.
		$value = str_replace( "\x00", '', $value );

		// Ensure the value is valid UTF-8.
		if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
			$value = mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
		}

		// Additional safety: limit length and remove potentially problematic characters.
		$value = substr( $value, 0, 10000 ); // Reasonable limit for field values.

		return $value;
	}

	/**
	 * POST /encrypt-field endpoint.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The REST request.
	 * @return \WP_REST_Response|\WP_Error Response or error.
	 * @throws \InvalidArgumentException When validation fails.
	 */
	public static function r_encrypt_field( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Verify nonce for CSRF protection.
		$nonce = $request->get_param( '_wpnonce' );
		if ( ! \wp_verify_nonce( $nonce, 'campaignbridge_encrypted_fields' ) ) {
			\CampaignBridge\Core\Error_Handler::error( 'Invalid nonce in encrypt request' );
			return new WP_Error( 'invalid_nonce', 'Security validation failed', array( 'status' => 403 ) );
		}

		// Rate limiting to prevent abuse (max 20 requests per minute per user).
		$user_id        = get_current_user_id();
		$rate_limit_key = "encrypt_rate_limit_{$user_id}";
		$requests       = \CampaignBridge\Core\Storage::get_transient( $rate_limit_key );
		$requests       = $requests ?? 0;

		if ( $requests >= 20 ) {
			\CampaignBridge\Core\Error_Handler::warning( 'Rate limit exceeded for encrypt requests', array( 'user_id' => $user_id ) );
			return new WP_Error( 'rate_limit_exceeded', 'Too many requests. Please try again later.', array( 'status' => 429 ) );
		}

		\CampaignBridge\Core\Storage::set_transient( $rate_limit_key, $requests + 1, 60 ); // 1 minute window

		$field_id  = $request->get_param( 'field_id' );
		$new_value = $request->get_param( 'new_value' );

		try {
			// Validate the new value.
			$new_value = sanitize_text_field( $new_value );

			if ( empty( $new_value ) ) {
				throw new \InvalidArgumentException( 'New value cannot be empty' );
			}

			// Encrypt the new value using sensitive context for admin operations.
			$encrypted = \CampaignBridge\Core\Encryption::encrypt( $new_value );

			// Generate masked version for display.
			$masked = self::mask_value( $new_value );

			// Add small random delay to prevent timing attacks (10-50ms).
			usleep( wp_rand( 10000, 50000 ) );

			// Return response in the format JavaScript expects.
			return self::ensure_response(
				array(
					'success' => true,
					'data'    => array(
						'encrypted' => $encrypted,
						'masked'    => $masked,
					),
				)
			);
		} catch ( \RuntimeException $e ) {
			\CampaignBridge\Core\Error_Handler::error(
				'Failed to encrypt field value via REST API',
				array( 'error' => $e->getMessage() )
			);
			// Sanitize error message for production - don't expose internal details.
			$error_message = WP_DEBUG ? $e->getMessage() : 'Unable to process the data for encryption';
			return new WP_Error( 'encryption_failed', $error_message, array( 'status' => 400 ) );
		}
	}

	/**
	 * Validate encrypted value parameter.
	 *
	 * @param string $value The value to validate.
	 * @return bool True if valid.
	 */
	public static function validate_encrypted_value( string $value ): bool {
		return ! empty( $value ) && \CampaignBridge\Core\Encryption::is_encrypted_value( $value );
	}

	/**
	 * Validate field value parameter.
	 *
	 * @param string $value The value to validate.
	 * @return bool True if valid.
	 */
	public static function validate_field_value( string $value ): bool {
		// Check length (reasonable limit for field values).
		if ( strlen( $value ) > 1000 ) {
			return false;
		}

		// Check for potentially dangerous content.
		$suspicious = array(
			'<script',
			'javascript:',
			'onclick=',
			'onload=',
			'onerror=',
			'vbscript:',
			'data:text/html',
		);

		foreach ( $suspicious as $pattern ) {
			if ( stripos( $value, $pattern ) !== false ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Mask a value for display (show last 4 characters).
	 *
	 * @param string $value The value to mask.
	 * @return string The masked value.
	 */
	private static function mask_value( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$length = strlen( $value );

		if ( $length <= 4 ) {
			return str_repeat( '•', $length );
		}

		// For very long values (like encrypted data), limit the mask length to 20 chars + last 4.
		$max_mask_length = 20;
		$visible         = substr( $value, -4 );

		if ( $length > $max_mask_length + 4 ) {
			$masked = str_repeat( '•', $max_mask_length );
		} else {
			$masked = str_repeat( '•', $length - 4 );
		}

		return $masked . $visible;
	}
}
