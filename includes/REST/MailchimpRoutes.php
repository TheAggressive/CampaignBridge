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
	 * API namespace for routes.
	 */
	private const API_NAMESPACE = 'campaignbridge/v1';

	/**
	 * Default provider slug.
	 */
	private const DEFAULT_PROVIDER = 'mailchimp';

	/**
	 * Required capability for managing plugin settings.
	 */
	private const MANAGE_CAPABILITY = 'manage_options';

	/**
	 * HTTP status codes.
	 */
	private const HTTP_BAD_REQUEST       = 400;
	private const HTTP_UNAUTHORIZED      = 401;
	private const HTTP_TOO_MANY_REQUESTS = 429;

	/**
	 * Rate limiting defaults.
	 */
	private const RATE_LIMIT_MAX_REQUESTS = 20;
	private const RATE_LIMIT_WINDOW       = 60;

	/**
	 * Cache prefix for rate limiting.
	 */
	private const RATE_LIMIT_CACHE_PREFIX = 'cb_mc_rate_limit_';

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
	public static function register(): void {
		self::register_sections_route();
		self::register_audiences_route();
		self::register_templates_route();
		self::register_verify_route();
	}

	/**
	 * Register the sections endpoint route.
	 *
	 * @return void
	 */
	private static function register_sections_route(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/mailchimp/sections',
			self::get_route_definition( 'r_mc_sections', 'GET', self::get_refresh_args() )
		);
	}

	/**
	 * Register the audiences endpoint route.
	 *
	 * @return void
	 */
	private static function register_audiences_route(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/mailchimp/audiences',
			self::get_route_definition( 'r_mc_audiences', 'GET', self::get_refresh_args() )
		);
	}

	/**
	 * Register the templates endpoint route.
	 *
	 * @return void
	 */
	private static function register_templates_route(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/mailchimp/templates',
			self::get_route_definition( 'r_mc_templates', 'GET', self::get_refresh_args() )
		);
	}

	/**
	 * Register the verify endpoint route.
	 *
	 * @return void
	 */
	private static function register_verify_route(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/mailchimp/verify',
			self::get_route_definition( 'r_mc_verify', 'POST', self::get_verify_args() )
		);
	}

	/**
	 * Whether current user can manage plugin settings.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( self::MANAGE_CAPABILITY );
	}

	/**
	 * Get standard route definition array.
	 *
	 * @param string $callback Callback method name.
	 * @param string $method HTTP method.
	 * @param array  $args Route arguments.
	 * @return array Route definition.
	 */
	private static function get_route_definition( string $callback, string $method, array $args ): array {
		return array(
			'methods'             => $method,
			'callback'            => array( __CLASS__, $callback ),
			'permission_callback' => array( __CLASS__, 'can_manage' ),
			'args'                => $args,
		);
	}

	/**
	 * Get arguments for refresh parameter.
	 *
	 * @return array Refresh argument definition.
	 */
	private static function get_refresh_args(): array {
		return array(
			'refresh' => array(
				'type'     => 'boolean',
				'required' => false,
			),
		);
	}

	/**
	 * Get arguments for verify endpoint.
	 *
	 * @return array Verify argument definition.
	 */
	private static function get_verify_args(): array {
		return array(
			'api_key' => array(
				'type'     => 'string',
				'required' => false,
			),
		);
	}

	/**
	 * Simple rate limiting for Mailchimp REST API endpoints.
	 *
	 * @param string $endpoint_name Unique identifier for the endpoint.
	 * @param int    $max_requests Maximum requests allowed per time window.
	 * @param int    $time_window Time window in seconds.
	 * @return bool|\WP_Error True if allowed, WP_Error if rate limited.
	 */
	public static function check_rate_limit( string $endpoint_name, int $max_requests = self::RATE_LIMIT_MAX_REQUESTS, int $time_window = self::RATE_LIMIT_WINDOW ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return self::create_error_response( 'rate_limit_no_user', 'User not authenticated', self::HTTP_UNAUTHORIZED );
		}

		$cache_key = self::RATE_LIMIT_CACHE_PREFIX . $endpoint_name . '_' . $user_id;
		$requests  = get_transient( $cache_key );

		if ( false === $requests ) {
			$requests = 0;
		}

		if ( $requests >= $max_requests ) {
			return self::create_error_response(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: number of seconds until reset */
					__( 'Rate limit exceeded. Try again in %d seconds.', 'campaignbridge' ),
					$time_window
				),
				self::HTTP_TOO_MANY_REQUESTS
			);
		}

		set_transient( $cache_key, $requests + 1, $time_window );
		return true;
	}

	/**
	 * Create a standardized error response.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @param int    $status HTTP status code.
	 * @return \WP_Error Error object.
	 */
	private static function create_error_response( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Validate that Mailchimp provider is available.
	 *
	 * @return bool|\WP_Error True if available, error if not.
	 */
	private static function validate_mailchimp_provider() {
		$settings = get_option( self::$option_name );

		$provider = isset( $settings['provider'] ) && isset( self::$providers[ $settings['provider'] ] )
			? $settings['provider']
			: self::DEFAULT_PROVIDER;

		if ( self::DEFAULT_PROVIDER !== $provider || ! isset( self::$providers[ self::DEFAULT_PROVIDER ] ) ) {
			return self::create_error_response(
				'unsupported',
				'Only supported for Mailchimp',
				self::HTTP_BAD_REQUEST
			);
		}

		return true;
	}

	/**
	 * Validate that API key is available.
	 *
	 * @param array $settings Plugin settings.
	 * @return bool|\WP_Error True if available, error if not.
	 */
	private static function validate_api_key( array $settings ) {
		if ( empty( $settings['api_key'] ) ) {
			return self::create_error_response(
				'missing_key',
				'Missing API key',
				self::HTTP_BAD_REQUEST
			);
		}

		return true;
	}

	/**
	 * Validate that Mailchimp provider is available.
	 *
	 * @return bool|\WP_Error True if available, error if not.
	 */
	private static function validate_mailchimp_availability() {
		if ( ! isset( self::$providers[ self::DEFAULT_PROVIDER ] ) ) {
			return self::create_error_response(
				'not_available',
				'Mailchimp provider not available',
				self::HTTP_BAD_REQUEST
			);
		}

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
		if ( \is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		// Validate Mailchimp provider.
		$validation = self::validate_mailchimp_provider();
		if ( \is_wp_error( $validation ) ) {
			return $validation;
		}

		$settings = get_option( self::$option_name );
		$refresh  = (bool) $req->get_param( 'refresh' );
		$sections = self::$providers[ self::DEFAULT_PROVIDER ]->get_section_keys( $settings, $refresh );

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
		if ( \is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$settings = get_option( self::$option_name );

		// Validate API key.
		$validation = self::validate_api_key( $settings );
		if ( \is_wp_error( $validation ) ) {
			return $validation;
		}

		// Validate Mailchimp availability.
		$validation = self::validate_mailchimp_availability();
		if ( \is_wp_error( $validation ) ) {
			return $validation;
		}

		$refresh = (bool) $req->get_param( 'refresh' );
		$items   = self::$providers[ self::DEFAULT_PROVIDER ]->get_audiences( $settings, $refresh );

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
		if ( \is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$settings = get_option( self::$option_name );

		// Validate API key.
		$validation = self::validate_api_key( $settings );
		if ( \is_wp_error( $validation ) ) {
			return $validation;
		}

		// Validate Mailchimp availability.
		$validation = self::validate_mailchimp_availability();
		if ( \is_wp_error( $validation ) ) {
			return $validation;
		}

		$refresh = (bool) $req->get_param( 'refresh' );
		$items   = self::$providers[ self::DEFAULT_PROVIDER ]->get_templates( $settings, $refresh );

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

		// Validate Mailchimp provider.
		$validation = self::validate_mailchimp_provider();
		if ( \is_wp_error( $validation ) ) {
			return $validation;
		}

		$api_key = $req->get_param( 'api_key' );
		if ( $api_key ) {
			$settings['api_key'] = (string) $api_key;
		}

		// Validate API key.
		$validation = self::validate_api_key( $settings );
		if ( \is_wp_error( $validation ) ) {
			return $validation;
		}

		// Validate Mailchimp availability.
		$validation = self::validate_mailchimp_availability();
		if ( \is_wp_error( $validation ) ) {
			return $validation;
		}

		$items = self::$providers[ self::DEFAULT_PROVIDER ]->get_audiences( $settings );

		return \is_wp_error( $items ) ? $items : \rest_ensure_response( array( 'ok' => true ) );
	}
}
