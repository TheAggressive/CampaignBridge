<?php
/**
 * Onboarding checklist REST routes.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Admin\Onboarding_Checklist;
use WP_Error;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read and dismiss the current user's onboarding checklist.
 */
final class Onboarding_Routes extends Abstract_Rest_Controller {
	private const ENDPOINT_PATH = '/onboarding';

	/** Register the checklist read and dismissal. */
	public function register(): void {
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::ENDPOINT_PATH,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_checklist' ),
					'permission_callback' => array( Campaign_Routes::class, 'can_access_campaigns' ),
				),
			)
		);

		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::ENDPOINT_PATH . '/dismiss',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'dismiss' ),
					'permission_callback' => array( Campaign_Routes::class, 'can_access_campaigns' ),
				),
			)
		);
	}

	/** The checklist as stored state shows it now. */
	public function get_checklist(): WP_REST_Response {
		return new WP_REST_Response( Onboarding_Checklist::for_user( get_current_user_id() ), 200 );
	}

	/**
	 * Dismiss a completed checklist; an incomplete one cannot be hidden.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function dismiss() {
		$user_id = get_current_user_id();
		if ( ! Onboarding_Checklist::dismiss( $user_id ) ) {
			return new WP_Error(
				'campaignbridge_onboarding_incomplete',
				__( 'Finish the required setup steps before dismissing the checklist.', 'campaignbridge' ),
				array( 'status' => 409 )
			);
		}

		return new WP_REST_Response( Onboarding_Checklist::for_user( $user_id ), 200 );
	}
}
