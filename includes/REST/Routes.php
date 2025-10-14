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
use CampaignBridge\REST\Helpers\Response_Formatter;
use CampaignBridge\REST\Helpers\Input_Validator;

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
	private const ENDPOINT_POSTS      = '/posts';
	private const ENDPOINT_POST_TYPES = '/post-types';

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
	 * @param string               $option_name Options key used by the plugin.
	 * @param array<string, mixed> $providers   Registered providers map.
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
		register_rest_route(
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
	 * GET /posts endpoint.
	 *
	 * @param WP_REST_Request $req Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function r_posts( WP_REST_Request $req ) {
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
		$allowed_check = Input_Validator::validate_post_type_allowed( $post_type, 'campaignbridge_post_types' );
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
		$post_types_settings = get_option( 'campaignbridge_post_types', array() );

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
}
