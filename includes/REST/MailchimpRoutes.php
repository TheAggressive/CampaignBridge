<?php
/**
 * Mailchimp-specific REST API Routes for CampaignBridge.
 *
 * This class provides REST API endpoints specific to Mailchimp integration,
 * including sections, audiences, templates, and verification functionality.
 * Extracted from the main Routes class for better organization and maintainability.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName
/**
 * Mailchimp-specific REST API routes for CampaignBridge.
 */
class MailchimpRoutes {
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
	 * Register Mailchimp-specific REST routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = 'campaignbridge/v1';

		register_rest_route(
			$ns,
			'/mailchimp/sections',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_mc_sections' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'refresh' => array(
						'type'     => 'boolean',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mailchimp/audiences',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_mc_audiences' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'refresh' => array(
						'type'     => 'boolean',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mailchimp/templates',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'r_mc_templates' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'refresh' => array(
						'type'     => 'boolean',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/mailchimp/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'r_mc_verify' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'api_key' => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);
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
	 * Simple rate limiting for Mailchimp REST API endpoints.
	 *
	 * @param string $endpoint_name Unique identifier for the endpoint.
	 * @param int    $max_requests Maximum requests allowed per time window.
	 * @param int    $time_window Time window in seconds.
	 * @return bool|\WP_Error True if allowed, WP_Error if rate limited.
	 */
	public static function check_rate_limit( string $endpoint_name, int $max_requests = 20, int $time_window = 60 ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new \WP_Error( 'rate_limit_no_user', 'User not authenticated', array( 'status' => 401 ) );
		}

		$cache_key = 'cb_mc_rate_limit_' . $endpoint_name . '_' . $user_id;
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
	 * GET /mailchimp/sections endpoint.
	 *
	 * @param WP_REST_Request $req Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function r_mc_sections( WP_REST_Request $req ) {
		// Rate limiting check.
		$rate_limit = self::check_rate_limit( 'mc_sections' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$settings = get_option( self::$option_name );
		$provider = isset( $settings['provider'] ) && isset( self::$providers[ $settings['provider'] ] ) ? $settings['provider'] : 'mailchimp';
		if ( 'mailchimp' !== $provider || ! isset( self::$providers['mailchimp'] ) ) {
			return new \WP_Error( 'unsupported', 'Only supported for Mailchimp', array( 'status' => 400 ) );
		}
		$refresh  = (bool) $req->get_param( 'refresh' );
		$sections = self::$providers['mailchimp']->get_section_keys( $settings, $refresh );
		if ( \is_wp_error( $sections ) ) {
			return $sections;
		}
		return \rest_ensure_response( array( 'sections' => $sections ) );
	}

	/**
	 * GET /mailchimp/audiences endpoint.
	 *
	 * @param WP_REST_Request $req Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function r_mc_audiences( WP_REST_Request $req ) {
		// Rate limiting check.
		$rate_limit = self::check_rate_limit( 'mc_audiences' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$settings = get_option( self::$option_name );
		if ( empty( $settings['api_key'] ) ) {
			return new \WP_Error( 'missing_key', 'Missing API key', array( 'status' => 400 ) );
		}
		if ( ! isset( self::$providers['mailchimp'] ) ) {
			return new \WP_Error( 'not_available', 'Mailchimp provider not available', array( 'status' => 400 ) );
		}
		$refresh = (bool) $req->get_param( 'refresh' );
		$items   = self::$providers['mailchimp']->get_audiences( $settings, $refresh );
		return \is_wp_error( $items ) ? $items : \rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * GET /mailchimp/templates endpoint.
	 *
	 * @param \WP_REST_Request $req Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function r_mc_templates( WP_REST_Request $req ) {
		// Rate limiting check.
		$rate_limit = self::check_rate_limit( 'mc_templates' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$settings = get_option( self::$option_name );
		if ( empty( $settings['api_key'] ) ) {
			return new \WP_Error( 'missing_key', 'Missing API key', array( 'status' => 400 ) );
		}
		if ( ! isset( self::$providers['mailchimp'] ) ) {
			return new \WP_Error( 'not_available', 'Mailchimp provider not available', array( 'status' => 400 ) );
		}
		$refresh = (bool) $req->get_param( 'refresh' );
		$items   = self::$providers['mailchimp']->get_templates( $settings, $refresh );
		return \is_wp_error( $items ) ? $items : \rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * POST /mailchimp/verify endpoint.
	 *
	 * @param \WP_REST_Request $req Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function r_mc_verify( WP_REST_Request $req ) {
		$settings = get_option( self::$option_name );
		$prov     = isset( $settings['provider'] ) ? $settings['provider'] : 'mailchimp';
		if ( 'mailchimp' !== $prov ) {
			return new \WP_Error( 'bad_provider', 'Provider is not Mailchimp', array( 'status' => 400 ) );
		}
		$api_key = $req->get_param( 'api_key' );
		if ( $api_key ) {
			$settings['api_key'] = (string) $api_key;
		}
		if ( empty( $settings['api_key'] ) ) {
			return new \WP_Error( 'missing_key', 'Missing API key', array( 'status' => 400 ) );
		}
		if ( ! isset( self::$providers['mailchimp'] ) ) {
			return new \WP_Error( 'not_available', 'Mailchimp provider not available', array( 'status' => 400 ) );
		}
		$items = self::$providers['mailchimp']->get_audiences( $settings );
		return \is_wp_error( $items ) ? $items : \rest_ensure_response( array( 'ok' => true ) );
	}
}
