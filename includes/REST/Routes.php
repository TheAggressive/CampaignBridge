<?php
/**
 * General REST API Routes for CampaignBridge Admin Operations.
 *
 * This class provides general REST API endpoints for CampaignBridge admin
 * functionality, including posts and post types management. Mailchimp-specific
 * routes have been extracted to the separate MailchimpRoutes class for better
 * organization and maintainability.
 *
 * It follows WordPress REST API standards and provides secure, authenticated
 * endpoints for modern JavaScript-based interactions and AJAX operations.
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
	 * Initialize shared state.
	 *
	 * @param string $option_name Options key used by the plugin.
	 * @param array  $providers   Registered providers map.
	 * @return void
	 */
	public static function init( string $option_name, array $providers ): void {
		self::$option_name = $option_name;
		self::$providers   = $providers;
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = 'campaignbridge/v1';

		register_rest_route(
			$ns,
			'/posts',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_posts' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'post_type' => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/post-types',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_post_types' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		// Mapping slots endpoint removed (block-based workflow).

		// Register Mailchimp-specific routes.
		MailchimpRoutes::register();
	}

	/**
	 * Whether current user can manage plugin settings.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Simple rate limiting for REST API endpoints.
	 *
	 * @param string $endpoint_name Unique identifier for the endpoint.
	 * @param int    $max_requests Maximum requests allowed per time window.
	 * @param int    $time_window Time window in seconds.
	 * @return bool|\WP_Error True if allowed, WP_Error if rate limited.
	 */
	public static function check_rate_limit( string $endpoint_name, int $max_requests = 30, int $time_window = 60 ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new \WP_Error( 'rate_limit_no_user', 'User not authenticated', array( 'status' => 401 ) );
		}

		$cache_key = 'cb_rate_limit_' . $endpoint_name . '_' . $user_id;
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
				array( 'status' => 429 )
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

		$post_type = $req->get_param( 'post_type' ) ? sanitize_key( $req->get_param( 'post_type' ) ) : 'post';
		if ( ! post_type_exists( $post_type ) ) {
			return new \WP_Error( 'bad_post_type', 'Invalid post type', array( 'status' => 400 ) );
		}

		$settings       = get_option( self::$option_name );
		$excluded_types = isset( $settings['exclude_post_types'] ) && is_array( $settings['exclude_post_types'] ) ? array_map( 'sanitize_key', $settings['exclude_post_types'] ) : array();
		if ( in_array( $post_type, $excluded_types, true ) ) {
			return new \WP_Error( 'excluded_post_type', 'Post type excluded', array( 'status' => 400 ) );
		}
		$post_ids = get_posts(
			array(
				'post_type'              => $post_type,
				'posts_per_page'         => 100,
				'post_status'            => 'publish',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
				'suppress_filters'       => true,
			)
		);
		$items    = array();
		foreach ( (array) $post_ids as $pid ) {
			$title_raw     = (string) get_post_field( 'post_title', $pid );
			$title_decoded = html_entity_decode( $title_raw, ENT_QUOTES, 'UTF-8' );
			$items[]       = array(
				'id'    => (int) $pid,
				'label' => $title_decoded,
			);
		}
		return \rest_ensure_response( array( 'items' => $items ) );
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

		$settings       = get_option( self::$option_name );
		$excluded_types = isset( $settings['exclude_post_types'] ) && is_array( $settings['exclude_post_types'] ) ? array_map( 'sanitize_key', $settings['exclude_post_types'] ) : array();
		$objs           = get_post_types( array( 'public' => true ), 'objects' );
		$items          = array();
		foreach ( $objs as $obj ) {
			if ( in_array( $obj->name, $excluded_types, true ) ) {
				continue;
			}
			$items[] = array(
				'id'    => (string) $obj->name,
				'label' => (string) $obj->labels->singular_name,
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
}
