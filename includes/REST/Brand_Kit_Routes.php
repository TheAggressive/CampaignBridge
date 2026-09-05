<?php
/**
 * Brand kit REST routes.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Admin\Brand_Kit_Copy;
use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Repository\Brand_Kit_Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read and update the stored email brand kit.
 */
final class Brand_Kit_Routes extends Abstract_Rest_Controller {
	private const ENDPOINT_PATH = '/brand-kit';

	/**
	 * Register GET and PUT /brand-kit.
	 */
	public function register(): void {
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::ENDPOINT_PATH,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_kit' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_kit' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => array(
						'id'    => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						),
						'color' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * GET /brand-kit.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_kit(): WP_REST_Response|WP_Error {
		$rate_limit = Rate_Limiter::check_rate_limit( 'brand_kit' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		return self::ensure_response( Brand_Kit_Copy::payload( ( new Brand_Kit_Repository() )->get() ) );
	}

	/**
	 * PUT /brand-kit.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_kit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$rate_limit = Rate_Limiter::check_rate_limit( 'brand_kit' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$slug  = $request->get_param( 'id' );
		$color = $request->get_param( 'color' );

		if ( ! is_string( $slug ) || ! in_array( $slug, Brand_Kit::SLOTS, true ) ) {
			return self::create_error(
				'invalid_brand_slot',
				__( 'That brand slot is not recognised.', 'campaignbridge' ),
				Rest_Constants::HTTP_BAD_REQUEST
			);
		}

		$hex = Brand_Kit::normalize_hex( is_string( $color ) ? $color : null );
		if ( null === $hex ) {
			return self::create_error(
				'invalid_brand_color',
				__( 'Brand colours must be portable six-digit hex values.', 'campaignbridge' ),
				Rest_Constants::HTTP_BAD_REQUEST
			);
		}

		$repository      = new Brand_Kit_Repository();
		$merged          = $repository->get()->to_array()['colors'];
		$merged[ $slug ] = $hex;

		try {
			$saved = Brand_Kit::from_colors( $merged, Brand_Kit::SOURCE_CUSTOM );
		} catch ( \InvalidArgumentException $e ) {
			return self::create_error(
				'invalid_brand_color',
				__( 'Brand colours must be portable six-digit hex values.', 'campaignbridge' ),
				Rest_Constants::HTTP_BAD_REQUEST
			);
		}

		if ( ! $repository->save( $saved ) ) {
			return self::create_error(
				'brand_kit_not_saved',
				__( 'The brand colour could not be saved.', 'campaignbridge' ),
				Rest_Constants::HTTP_INTERNAL_SERVER_ERROR
			);
		}

		return self::ensure_response( Brand_Kit_Copy::payload( $saved ) );
	}
}
