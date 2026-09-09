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
use CampaignBridge\Domain\Email\Design_Presets;
use CampaignBridge\Repository\Brand_Kit_Repository;
use CampaignBridge\Services\Email\Google_Fonts;
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
	private const FONTS_PATH    = '/brand-kit/fonts';

	/**
	 * Register focused colour and font operations.
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
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'color' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::FONTS_PATH,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'search_fonts' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => array(
						'search' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_fonts' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => array(
						'fonts'            => array(
							'type'       => 'object',
							'required'   => true,
							'properties' => array(
								'heading' => array( 'type' => 'string' ),
								'body'    => array( 'type' => 'string' ),
								'button'  => array( 'type' => 'string' ),
							),
						),
						'customFontFamily' => array(
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
	 * Search the configured Google Fonts catalogue.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 */
	public function search_fonts( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$rate_limit = Rate_Limiter::check_rate_limit( 'brand_kit_fonts' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$search = $request->get_param( 'search' );
		$result = ( new Google_Fonts() )->search( is_string( $search ) ? $search : '' );
		if ( is_wp_error( $result ) ) {
			return self::create_error( (string) $result->get_error_code(), $result->get_error_message(), Rest_Constants::HTTP_BAD_REQUEST );
		}

		return self::ensure_response( array( 'fonts' => $result ) );
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

		$repository = new Brand_Kit_Repository();
		$kit        = $repository->get();

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

		$merged          = $kit->to_array()['colors'];
		$merged[ $slug ] = $hex;

		try {
			$saved = Brand_Kit::from_colors( $merged, Brand_Kit::SOURCE_CUSTOM, $kit->theme_fingerprint(), $kit->fonts(), $kit->custom_font() );
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
				__( 'The brand kit could not be saved.', 'campaignbridge' ),
				Rest_Constants::HTTP_INTERNAL_SERVER_ERROR
			);
		}

		return self::ensure_response( Brand_Kit_Copy::payload( $saved ) );
	}

	/**
	 * PUT /brand-kit/fonts.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_fonts( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$rate_limit = Rate_Limiter::check_rate_limit( 'brand_kit_fonts' );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$fonts         = $request->get_param( 'fonts' );
		$custom_family = $request->get_param( 'customFontFamily' );
		if ( ! is_array( $fonts ) || array() === array_filter( $fonts, 'is_string' ) ) {
			return self::create_error( 'invalid_brand_fonts', __( 'Choose at least one valid typography slot.', 'campaignbridge' ), Rest_Constants::HTTP_BAD_REQUEST );
		}

		$repository  = new Brand_Kit_Repository();
		$kit         = $repository->get();
		$custom_font = $kit->custom_font();
		if ( is_string( $custom_family ) && '' !== trim( $custom_family ) ) {
			$custom_font = ( new Google_Fonts() )->resolve( $custom_family );
			if ( is_wp_error( $custom_font ) ) {
				return self::create_error( (string) $custom_font->get_error_code(), $custom_font->get_error_message(), Rest_Constants::HTTP_BAD_REQUEST );
			}
		}

		$merged_fonts = $kit->fonts();
		foreach ( $fonts as $font_slot => $font_slug ) {
			if ( ! in_array( $font_slot, Brand_Kit::FONT_SLOTS, true ) || ! is_string( $font_slug ) || '' === $font_slug ) {
				continue;
			}
			if ( Brand_Kit::CUSTOM_FONT_SLUG === $font_slug && null === $custom_font ) {
				return self::create_error( 'custom_font_not_configured', __( 'Choose a Google Font before assigning the custom font.', 'campaignbridge' ), Rest_Constants::HTTP_BAD_REQUEST );
			}
			if ( Brand_Kit::CUSTOM_FONT_SLUG !== $font_slug && null === Design_Presets::font( $font_slug ) ) {
				return self::create_error( 'invalid_brand_font', __( 'That font is not available in the CampaignBridge type catalogue.', 'campaignbridge' ), Rest_Constants::HTTP_BAD_REQUEST );
			}

			$merged_fonts[ $font_slot ] = $font_slug;
		}

		$saved = Brand_Kit::from_colors( $kit->to_array()['colors'], Brand_Kit::SOURCE_CUSTOM, $kit->theme_fingerprint(), $merged_fonts, $custom_font );
		if ( ! $repository->save( $saved ) ) {
			return self::create_error( 'brand_kit_not_saved', __( 'The brand kit could not be saved.', 'campaignbridge' ), Rest_Constants::HTTP_INTERNAL_SERVER_ERROR );
		}

		return self::ensure_response( Brand_Kit_Copy::payload( $saved ) );
	}
}
