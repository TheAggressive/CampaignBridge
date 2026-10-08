<?php
/**
 * Read-only campaign history REST routes.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Core\Campaign_Authorizer;
use CampaignBridge\Domain\Campaign\Audit_Event;
use CampaignBridge\Domain\Campaign\Delivery_Attempt;
use CampaignBridge\Services\Campaign\Campaign_History_Factory;
use CampaignBridge\Workflow\Campaign\Campaign_History;
use CampaignBridge\Workflow\Campaign\Campaign_History_Result;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pages a campaign's audit events and delivery attempts for operator screens.
 *
 * Both routes authorize exactly as the campaign detail route does, through the
 * campaign workflow. They expose what the audit log and attempt records
 * already hold in redacted form: no credentials, provider payloads, recipient
 * addresses, or raw idempotency keys.
 */
final class Campaign_History_Routes {
	private const ITEM = '/campaigns/(?P<id>[a-z0-9][a-z0-9_-]{0,63})';

	/**
	 * Read-only history service.
	 *
	 * @var Campaign_History
	 */
	private Campaign_History $history;

	/**
	 * Resolves the requesting actor.
	 *
	 * @var Campaign_Authorizer
	 */
	private Campaign_Authorizer $authorizer;

	/**
	 * Build the routes.
	 *
	 * @param Campaign_History|null    $history    History service.
	 * @param Campaign_Authorizer|null $authorizer Actor resolver.
	 */
	public function __construct( ?Campaign_History $history = null, ?Campaign_Authorizer $authorizer = null ) {
		$this->history    = $history ?? Campaign_History_Factory::create();
		$this->authorizer = $authorizer ?? new Campaign_Authorizer();
	}

	/** Register the history, attempt, and remote reference routes. */
	public function register(): void {
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::ITEM . '/remote',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_remote' ),
					'permission_callback' => array( Campaign_Routes::class, 'can_access_campaigns' ),
					'args'                => array( 'id' => Campaign_Rest_Schema::campaign_id() ),
				),
				'schema' => array( Campaign_Rest_Schema::class, 'remote_view_result' ),
			)
		);

		foreach ( array(
			'/history'  => array( 'get_history', 'history' ),
			'/attempts' => array( 'get_attempts', 'attempts' ),
		) as $suffix => list( $callback, $schema ) ) {
			\register_rest_route(
				Rest_Constants::API_NAMESPACE,
				self::ITEM . $suffix,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, $callback ),
						'permission_callback' => array( Campaign_Routes::class, 'can_access_campaigns' ),
						'args'                => array( 'id' => Campaign_Rest_Schema::campaign_id() ) + Campaign_History_Schema::page_args(),
					),
					'schema' => array( Campaign_History_Schema::class, $schema ),
				)
			);
		}
	}

	/**
	 * The campaign and its provider reference, which is null before handoff.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function get_remote( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$actor = $this->authorizer->actor( get_current_user_id() );
		$view  = $this->history->remote( $actor, (string) $request->get_param( 'id' ) );
		$read  = $view->campaign();
		if ( ! $view->is_success() || null === $read ) {
			return Campaign_Rest_Errors::from_error( $view->error() );
		}

		$reference = $view->reference();
		$response  = new WP_REST_Response(
			array(
				'campaign' => Campaign_Rest_Resource::campaign( $read, $actor ),
				'remote'   => null === $reference ? null : Campaign_Rest_Resource::remote( $reference ),
			)
		);
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * One page of the campaign's audit events, newest first.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function get_history( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		list( $page, $per_page ) = $this->paging( $request );

		return $this->page(
			$this->history->events( $this->authorizer->actor( get_current_user_id() ), (string) $request->get_param( 'id' ), $per_page, $page ),
			$page,
			$per_page,
			static fn ( Audit_Event $event ): array => self::event( $event )
		);
	}

	/**
	 * One page of the campaign's delivery attempts, newest first.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function get_attempts( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		list( $page, $per_page ) = $this->paging( $request );

		return $this->page(
			$this->history->attempts( $this->authorizer->actor( get_current_user_id() ), (string) $request->get_param( 'id' ), $per_page, $page ),
			$page,
			$per_page,
			static fn ( Delivery_Attempt $attempt ): array => self::attempt( $attempt )
		);
	}

	/**
	 * Read validated paging arguments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{0: int, 1: int}
	 */
	private function paging( WP_REST_Request $request ): array {
		return array( (int) $request->get_param( 'page' ), (int) $request->get_param( 'per_page' ) );
	}

	/**
	 * Shape one page of records as a collection response.
	 *
	 * @param Campaign_History_Result $result   Workflow result.
	 * @param int                     $page     One-based page.
	 * @param int                     $per_page Page size.
	 * @param callable                $shape    Record to response item.
	 */
	private function page( Campaign_History_Result $result, int $page, int $per_page, callable $shape ): WP_REST_Response|WP_Error {
		if ( ! $result->is_success() ) {
			return Campaign_Rest_Errors::from_error( $result->error() );
		}

		$total       = $result->total();
		$total_pages = (int) ceil( $total / $per_page );
		if ( 1 < $page && $page > $total_pages ) {
			return new WP_Error(
				'campaignbridge_campaign_invalid_page',
				__( 'The requested page does not exist.', 'campaignbridge' ),
				array( 'status' => Rest_Constants::HTTP_BAD_REQUEST )
			);
		}

		$response = new WP_REST_Response(
			array(
				'items'      => array_map( $shape, $result->records() ),
				'pagination' => array(
					'page'        => $page,
					'per_page'    => $per_page,
					'total'       => $total,
					'total_pages' => $total_pages,
				),
			)
		);
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * Response item for one audit event.
	 *
	 * @param Audit_Event $event Stored event; its context was redacted when written.
	 * @return array<string, mixed>
	 */
	private static function event( Audit_Event $event ): array {
		$data  = $event->to_array();
		$actor = is_int( $data['actor_user_id'] ) ? get_userdata( $data['actor_user_id'] ) : false;

		return array(
			'id'         => $data['id'],
			'action'     => $data['action'],
			'result'     => $data['result'],
			'actor'      => array(
				'id'   => $data['actor_user_id'],
				'name' => $actor instanceof \WP_User ? $actor->display_name : null,
			),
			'context'    => (object) $data['context'],
			'created_at' => $data['created_at'],
		);
	}

	/**
	 * Response item for one delivery attempt.
	 *
	 * @param Delivery_Attempt $attempt Stored attempt.
	 * @return array<string, mixed>
	 */
	private static function attempt( Delivery_Attempt $attempt ): array {
		$data = $attempt->to_array();

		return array(
			'id'                  => $data['id'],
			'operation'           => $data['operation'],
			'status'              => $data['status'],
			'retryability'        => $data['retryability'],
			// The key is an operator-supplied value; only its presence is useful here.
			'has_idempotency_key' => null !== $data['idempotency_key'],
			'remote_correlation'  => $data['remote_correlation'],
			'created_at'          => $data['created_at'],
			'updated_at'          => $data['updated_at'],
		);
	}
}
