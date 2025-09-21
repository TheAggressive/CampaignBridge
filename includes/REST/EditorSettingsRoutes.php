<?php
/**
 * Editor Settings REST API Routes for CampaignBridge.
 *
 * Handles the /editor-settings endpoint with rate limiting and
 * sensitive data filtering for WordPress block editor settings.
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
 * Editor Settings REST API routes handler.
 */
class EditorSettingsRoutes {
	/**
	 * API namespace
	 */
	private const API_NAMESPACE = 'campaignbridge/v1';

	/**
	 * Endpoint path
	 */
	private const ENDPOINT_PATH = '/editor-settings';

	/**
	 * Default post type
	 */
	private const DEFAULT_POST_TYPE = 'post';

	/**
	 * Rate limiting defaults
	 */
	private const RATE_LIMIT_REQUESTS = 30;
	private const RATE_LIMIT_WINDOW   = 60;

	/**
	 * Cache key prefix
	 */
	private const CACHE_KEY_PREFIX = 'cb_rate_limit_editor_settings_';

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
	private string $option_name;

	/**
	 * Constructor.
	 *
	 * @param string $option_name Options key used by the plugin.
	 */
	public function __construct( string $option_name ) {
		$this->option_name = $option_name;
	}

	/**
	 * Register the editor settings endpoint.
	 *
	 * @return void
	 */
	public function register(): void {
		register_rest_route(
			self::API_NAMESPACE,
			self::ENDPOINT_PATH,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'post_type' => array(
						'type'     => 'string',
						'required' => false,
						'default'  => self::DEFAULT_POST_TYPE,
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
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Simple rate limiting for the editor settings endpoint.
	 *
	 * @param int $max_requests Maximum requests allowed per time window.
	 * @param int $time_window Time window in seconds.
	 * @return bool|\WP_Error True if allowed, WP_Error if rate limited.
	 */
	private function check_rate_limit( int $max_requests = self::RATE_LIMIT_REQUESTS, int $time_window = self::RATE_LIMIT_WINDOW ): bool|\WP_Error {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new \WP_Error( 'rate_limit_no_user', 'User not authenticated', array( 'status' => self::HTTP_UNAUTHORIZED ) );
		}

		$cache_key = self::CACHE_KEY_PREFIX . $user_id;
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
	 * Handle the GET /editor-settings endpoint request.
	 *
	 * @param WP_REST_Request $req Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_request( WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		// Rate limiting check.
		$rate_limit = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$post_type = $req->get_param( 'post_type' ) ? sanitize_key( $req->get_param( 'post_type' ) ) : self::DEFAULT_POST_TYPE;

		if ( ! post_type_exists( $post_type ) ) {
			return new \WP_Error( 'invalid_post_type', 'Invalid post type', array( 'status' => self::HTTP_BAD_REQUEST ) );
		}

		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object ) {
			return new \WP_Error( 'post_type_not_found', 'Post type object not found', array( 'status' => self::HTTP_BAD_REQUEST ) );
		}

		// Get block editor settings from WordPress core.
		$settings = get_block_editor_settings( array(), $post_type_object );

		// Filter out sensitive information inline.
		if ( is_array( $settings ) ) {
			$settings = $this->filter_sensitive_settings( $settings );
		}

		return \rest_ensure_response( $settings );
	}

	/**
	 * Get sensitive keys that should be removed from editor settings.
	 *
	 * @return array List of sensitive keys to filter out.
	 */
	private static function get_sensitive_keys(): array {
		return array(
			'__experimentalDashboardLink',       // Admin URL.
			'__unstableResolvedAssets',          // Contains URLs, versions, scripts with sensitive data.
			'__experimentalDiscussionSettings',  // Contains avatar URLs and discussion settings.
			'canUpdateBlockBindings',            // Not needed for editor functionality.
		);
	}

	/**
	 * Filter sensitive information from editor settings.
	 *
	 * @param array $settings Raw editor settings from WordPress.
	 * @return array Filtered settings with sensitive data removed.
	 */
	private function filter_sensitive_settings( array $settings ): array {
		// Remove sensitive keys.
		$sensitive_keys = self::get_sensitive_keys();

		foreach ( $sensitive_keys as $key ) {
			unset( $settings[ $key ] );
		}

		// Filter styles array to remove any potentially sensitive CSS.
		if (
			isset( $settings['styles'] ) &&
			is_array( $settings['styles'] )
		) {
			$settings['styles'] = $this->filter_styles( $settings['styles'] );
		}

		// Ensure defaultEditorStyles doesn't contain sensitive information.
		if (
			isset( $settings['defaultEditorStyles'] ) &&
			is_array( $settings['defaultEditorStyles'] )
		) {
			$settings['defaultEditorStyles'] = $this->filter_default_styles( $settings['defaultEditorStyles'] );
		}

		return $settings;
	}

	/**
	 * Check if a style is safe to keep.
	 *
	 * @param array $style Style array to check.
	 * @return bool True if style is safe to keep.
	 */
	private function is_safe_style( array $style ): bool {
		if ( ! isset( $style['css'] ) || ! is_string( $style['css'] ) ) {
			return false;
		}

		// Block any CSS that contains URLs or potentially sensitive data.
		if ( false !== strpos( $style['css'], 'url(' ) ) {
			return false;
		}

		// Only keep global styles that are essential for theme colors and typography.
		if (
			isset( $style['isGlobalStyles'] ) &&
			true === $style['isGlobalStyles'] &&
			isset( $style['__unstableType'] ) &&
			in_array( $style['__unstableType'], array( 'presets', 'theme' ), true )
		) {
			return true;
		}

		// Reject anything else to be safe.
		return false;
	}

	/**
	 * Filter styles array to remove potentially sensitive CSS.
	 *
	 * @param array $styles Raw styles array.
	 * @return array Filtered styles array.
	 */
	private function filter_styles( array $styles ): array {
		$filtered_styles = array_filter(
			$styles,
			array( $this, 'is_safe_style' )
		);

		// Re-index the array to ensure clean numeric keys.
		return array_values( $filtered_styles );
	}

	/**
	 * Filter default editor styles to remove sensitive information.
	 *
	 * @param array $default_styles Raw default editor styles array.
	 * @return array Filtered default editor styles array.
	 */
	private function filter_default_styles( array $default_styles ): array {
		$filtered_default_styles = array_filter(
			$default_styles,
			array( $this, 'is_safe_style' )
		);

		// Re-index the array to ensure clean numeric keys.
		return array_values( $filtered_default_styles );
	}
}
