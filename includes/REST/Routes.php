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

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName
/**
 * REST API routes for CampaignBridge admin operations.
 */
class Routes {
	/**
	 * API namespace
	 */
	private const API_NAMESPACE = 'campaignbridge/v1';

	/**
	 * Endpoint paths
	 */
	private const ENDPOINT_POSTS      = '/posts';
	private const ENDPOINT_POST_TYPES = '/post-types';

	/**
	 * Default post type
	 */
	private const DEFAULT_POST_TYPE = 'post';

	/**
	 * Query defaults
	 */
	private const POSTS_PER_PAGE = 100;

	/**
	 * Rate limiting defaults
	 */
	private const RATE_LIMIT_REQUESTS = 30;
	private const RATE_LIMIT_WINDOW   = 60;
	private const CACHE_KEY_PREFIX    = 'cb_rate_limit_';

	/**
	 * HTTP status codes
	 */
	private const HTTP_UNAUTHORIZED      = 401;
	private const HTTP_BAD_REQUEST       = 400;
	private const HTTP_TOO_MANY_REQUESTS = 429;

	/**
	 * Option key used to store plugin settings.
	 *
	 * @var string
	 */
	private static string $option_name = 'campaignbridge_settings';

	/**
	 * Registered providers map indexed by slug.
	 *
	 * @var array<string,object>
	 */
	private static array $providers = array();

	/**
	 * Editor settings routes instance.
	 *
	 * @var Editor_Settings_Routes
	 */
	private static Editor_Settings_Routes $editor_settings_routes;

	/**
	 * Initialize shared state.
	 *
	 * @param string $option_name Options key used by the plugin.
	 * @param array  $providers   Registered providers map.
	 * @return void
	 */
	public static function init( string $option_name, array $providers ): void {
		self::$option_name            = $option_name;
		self::$providers              = $providers;
		self::$editor_settings_routes = new Editor_Settings_Routes( $option_name );
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
		register_rest_route(
			self::API_NAMESPACE,
			self::ENDPOINT_POSTS,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_posts' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'post_type' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
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
		register_rest_route(
			self::API_NAMESPACE,
			self::ENDPOINT_POST_TYPES,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_post_types' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	/**
	 * Required capability for managing CampaignBridge settings.
	 */
	private const MANAGE_CAP = 'manage_options';

	/**
	 * Whether current user can manage plugin settings.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( self::MANAGE_CAP );
	}

	/**
	 * Validate post type parameter.
	 *
	 * @param string $value Post type value to validate.
	 * @return bool
	 */
	public static function validate_post_type( string $value ): bool {
		// Only allow valid post types that exist in WordPress.
		$post_types = get_post_types( array( 'public' => true ) );
		return array_key_exists( $value, $post_types );
	}

	/**
	 * Simple rate limiting for REST API endpoints.
	 *
	 * @param string $endpoint_name Unique identifier for the endpoint.
	 * @param int    $max_requests Maximum requests allowed per time window.
	 * @param int    $time_window Time window in seconds.
	 * @return bool|\WP_Error True if allowed, WP_Error if rate limited.
	 */
	public static function check_rate_limit( string $endpoint_name, int $max_requests = self::RATE_LIMIT_REQUESTS, int $time_window = self::RATE_LIMIT_WINDOW ) {
		$user_id = get_current_user_id();

		// For better security, use both user ID and IP address.
		$ip_address = self::get_client_ip();
		$identifier = $user_id ? 'user_' . $user_id : 'ip_' . $ip_address;

		$cache_key = self::CACHE_KEY_PREFIX . $endpoint_name . '_' . $identifier;
		$requests  = get_transient( $cache_key );

		if ( false === $requests ) {
			$requests = 0;
		}

		if ( $requests >= $max_requests ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: number of seconds until reset */
					__( 'Rate limit exceeded. Try again in %d seconds.', 'campaignbridge' ),
					$time_window
				),
				array( 'status' => self::HTTP_TOO_MANY_REQUESTS )
			);
		}

		set_transient( $cache_key, $requests + 1, $time_window );
		return true;
	}

	/**
	 * GET /posts endpoint.
	 *
	 * @param \WP_REST_Request $req Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function r_posts( WP_REST_Request $req ) {
		// Rate limiting check.
		$rate_limit = self::check_rate_limit( 'posts' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		// Sanitize and validate post_type parameter with enhanced security.
		$raw_post_type = $req->get_param( 'post_type' );
		$post_type     = $raw_post_type ? sanitize_key( $raw_post_type ) : self::DEFAULT_POST_TYPE;

		// Additional validation: ensure post type contains only alphanumeric characters and underscores.
		if ( ! empty( $raw_post_type ) && ! preg_match( '/^[a-zA-Z0-9_]+$/', $post_type ) ) {
			return new \WP_Error( 'invalid_post_type_format', 'Post type contains invalid characters', array( 'status' => self::HTTP_BAD_REQUEST ) );
		}

		if ( ! post_type_exists( $post_type ) ) {
			return new \WP_Error( 'bad_post_type', 'Invalid post type', array( 'status' => self::HTTP_BAD_REQUEST ) );
		}

		$settings       = self::get_safe_settings();
		$excluded_types = isset( $settings['exclude_post_types'] ) && is_array( $settings['exclude_post_types'] ) ? array_map( 'sanitize_key', $settings['exclude_post_types'] ) : array();
		if ( in_array( $post_type, $excluded_types, true ) ) {
			return new \WP_Error( 'excluded_post_type', 'Post type excluded', array( 'status' => self::HTTP_BAD_REQUEST ) );
		}
		$post_ids = get_posts( self::get_posts_query_args( $post_type ) );
		$items    = array();
		foreach ( (array) $post_ids as $pid ) {
			$title_raw     = (string) get_post_field( 'post_title', $pid );
			$title_decoded = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
			$title_escaped = esc_html( $title_decoded ); // Escape HTML to prevent XSS.
			$items[]       = array(
				'id'    => (int) $pid,
				'label' => $title_escaped,
			);
		}
		return \rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Get safe settings for REST API responses.
	 *
	 * Returns plugin settings with sensitive data redacted to prevent
	 * exposure of API keys and other sensitive information via REST API.
	 *
	 * @return array Settings array with sensitive fields redacted.
	 */
	private static function get_safe_settings(): array {
		$settings = get_option( self::$option_name, array() );

		// Redact sensitive fields for REST API responses.
		$sensitive_fields = array( 'api_key', 'secret', 'password', 'token' );
		foreach ( $sensitive_fields as $field ) {
			if ( isset( $settings[ $field ] ) ) {
				// Replace with placeholder to indicate field exists but is hidden.
				$settings[ $field ] = '[REDACTED]';
			}
		}

		return $settings;
	}

	/**
	 * Get query arguments for posts endpoint.
	 *
	 * @param string $post_type Post type to query.
	 * @return array Query arguments array.
	 */
	private static function get_posts_query_args( string $post_type ): array {
		return array(
			'post_type'              => $post_type,
			'posts_per_page'         => self::POSTS_PER_PAGE,
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
	public static function r_post_types() {
		// Rate limiting check.
		$rate_limit = self::check_rate_limit( 'post_types' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$settings = self::get_safe_settings();

		// Sanitize and validate excluded post types with enhanced security.
		$excluded_types = array();
		if ( isset( $settings['exclude_post_types'] ) && is_array( $settings['exclude_post_types'] ) ) {
			foreach ( $settings['exclude_post_types'] as $post_type ) {
				$sanitized = sanitize_key( $post_type );
				// Additional validation: ensure post type contains only alphanumeric characters and underscores.
				if ( preg_match( '/^[a-zA-Z0-9_]+$/', $sanitized ) ) {
					$excluded_types[] = $sanitized;
				}
			}
		}
		$objs  = get_post_types( array( 'public' => true ), 'objects' );
		$items = array();
		foreach ( $objs as $obj ) {
			if ( in_array( $obj->name, $excluded_types, true ) ) {
				continue;
			}
			$items[] = array(
				'id'    => (string) $obj->name,
				'label' => esc_html( (string) $obj->labels->singular_name ), // Escape HTML to prevent XSS.
			);
		}
		usort(
			$items,
			function ( $a, $b ) {
				return strcasecmp( (string) $a['label'], (string) $b['label'] );
			}
		);
		return \rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Get client IP address for rate limiting.
	 *
	 * @return string Client IP address.
	 */
	private static function get_client_ip(): string {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}
		return '127.0.0.1'; // Fallback.
	}
}
