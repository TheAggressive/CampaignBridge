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
use CampaignBridge\REST\Helpers\Response_Formatter;
use CampaignBridge\REST\Helpers\Input_Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName
/**
 * Editor Settings REST API routes handler.
 */
class Editor_Settings_Routes extends Abstract_Rest_Controller {
	/**
	 * Endpoint path
	 */
	private const ENDPOINT_PATH = '/editor-settings';

	/**
	 * Register the editor settings endpoint.
	 *
	 * @return void
	 */
	public function register(): void {
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::ENDPOINT_PATH,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'post_type' => array(
						'type'     => 'string',
						'required' => false,
						'default'  => Rest_Constants::DEFAULT_POST_TYPE,
					),
				),
			)
		);
	}



	/**
	 * Handle the GET /editor-settings endpoint request.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $req Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_request( WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		// Rate limiting check.
		$rate_limit = Rate_Limiter::check_rate_limit_authenticated( 'editor_settings' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		// Validate post type.
		$raw_post_type = $req->get_param( 'post_type' );
		$post_type     = $raw_post_type ? $raw_post_type : Rest_Constants::DEFAULT_POST_TYPE;

		$validated_post_type = Input_Validator::validate_post_type_secure( $post_type );
		if ( is_wp_error( $validated_post_type ) ) {
			return $validated_post_type;
		}
		$post_type = $validated_post_type;

		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object ) {
			return $this->create_error( 'post_type_not_found', 'Post type object not found' );
		}

		// Get block editor settings from WordPress core.
		$block_editor_context = new \WP_Block_Editor_Context( array( 'post' => null ) );
		$settings             = get_block_editor_settings( array(), $block_editor_context );

		// Filter out sensitive information.
		$settings = Response_Formatter::filter_editor_settings( $settings );

		return $this->ensure_response( $settings );
	}
}
