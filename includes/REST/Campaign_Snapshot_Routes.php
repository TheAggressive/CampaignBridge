<?php
/**
 * Read-only route for a campaign's reviewed snapshot.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Core\Campaign_Authorizer;
use CampaignBridge\Services\Campaign\Campaign_Workflow_Factory;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the exact artifact a campaign was reviewed with.
 *
 * POST /preview compiles the template's live content; this route returns the
 * stored artifact of the active snapshot, only after the workflow proves the
 * snapshot still reproduces it, so a review screen never shows content that
 * differs from what will be handed off.
 */
final class Campaign_Snapshot_Routes {
	/**
	 * Campaign workflow.
	 *
	 * @var Campaign_Workflow
	 */
	private Campaign_Workflow $workflow;

	/**
	 * Resolves the requesting actor.
	 *
	 * @var Campaign_Authorizer
	 */
	private Campaign_Authorizer $authorizer;

	/**
	 * Build the route.
	 *
	 * @param Campaign_Workflow|null   $workflow   Campaign workflow.
	 * @param Campaign_Authorizer|null $authorizer Actor resolver.
	 */
	public function __construct( ?Campaign_Workflow $workflow = null, ?Campaign_Authorizer $authorizer = null ) {
		$this->workflow   = $workflow ?? Campaign_Workflow_Factory::create();
		$this->authorizer = $authorizer ?? new Campaign_Authorizer();
	}

	/** Register GET /campaigns/{id}/reviewed-snapshot. */
	public function register(): void {
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			'/campaigns/(?P<id>[a-z0-9][a-z0-9_-]{0,63})/reviewed-snapshot',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_snapshot' ),
					'permission_callback' => array( Campaign_Routes::class, 'can_access_campaigns' ),
					'args'                => array( 'id' => Campaign_Rest_Schema::campaign_id() ),
				),
				'schema' => array( Campaign_Rest_Schema::class, 'reviewed_snapshot_result' ),
			)
		);
	}

	/**
	 * The active snapshot and its reviewed artifact.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function get_snapshot( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$actor  = $this->authorizer->actor( get_current_user_id() );
		$result = $this->workflow->reviewed_snapshot( $actor, (string) $request->get_param( 'id' ) );
		$loaded = $result->campaign();
		$frozen = $result->snapshot();
		if ( ! $result->is_success() || null === $loaded || null === $frozen ) {
			return Campaign_Rest_Errors::from_result( $result );
		}

		$response = new WP_REST_Response(
			array(
				'campaign' => Campaign_Rest_Resource::campaign( $loaded, $actor ),
				'snapshot' => Campaign_Rest_Resource::snapshot( $frozen ),
				'artifact' => Campaign_Rest_Resource::artifact( $frozen ),
			)
		);
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
