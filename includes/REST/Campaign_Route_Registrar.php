<?php
/**
 * Campaign REST route declarations and registration.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Binds the campaign controller to its route, permission, and schema contracts. */
final class Campaign_Route_Registrar {
	private const COLLECTION = '/campaigns';
	private const ITEM       = '/campaigns/(?P<id>[a-z0-9][a-z0-9_-]{0,63})';

	/**
	 * Build the registrar without registering routes.
	 *
	 * @param Campaign_Routes $controller Handles campaign requests.
	 */
	public function __construct( private readonly Campaign_Routes $controller ) {}

	/**
	 * Register every campaign route with its permission check and argument schema.
	 */
	public function register(): void {
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::COLLECTION,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'list_campaigns' ),
					'permission_callback' => array( Campaign_Routes::class, 'can_access_campaigns' ),
					'args'                => Campaign_Rest_Schema::collection_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'create_campaign' ),
					'permission_callback' => array( Campaign_Routes::class, 'can_access_campaigns' ),
					'args'                => array(
						'template_id'        => Campaign_Rest_Schema::template_id(),
						'owner_user_id'      => Campaign_Rest_Schema::owner_user_id(),
						'provider'           => Campaign_Rest_Schema::provider(),
						'audience_reference' => Campaign_Rest_Schema::audience_reference(),
					),
				),
				'schema' => array( Campaign_Rest_Schema::class, 'collection' ),
			)
		);

		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::ITEM,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_campaign' ),
					'permission_callback' => array( Campaign_Routes::class, 'can_access_campaigns' ),
					'args'                => array( 'id' => Campaign_Rest_Schema::campaign_id() ),
				),
				'schema' => array( Campaign_Rest_Schema::class, 'campaign_result' ),
			)
		);

		$this->register_action( '/template', 'update_template', array( 'template_id' => Campaign_Rest_Schema::template_id() ), true );
		$this->register_action(
			'/targeting',
			'update_targeting',
			array(
				'provider'           => Campaign_Rest_Schema::provider(),
				'audience_reference' => Campaign_Rest_Schema::audience_reference(),
			),
			true
		);
		$this->register_action( '/snapshot', 'snapshot_campaign', array(), true, 'snapshot_result' );
		$this->register_action( '/validation', 'validate_campaign', array(), false, 'validation_result' );
		$this->register_action( '/preview', 'preview_campaign', array(), false, 'preview_result' );
		$this->register_action( '/submit', 'submit_campaign', array(), true );
		$this->register_action( '/approve', 'approve_campaign', array(), true, 'campaign_result', 'can_approve_campaigns' );
		$this->register_action( '/revoke-approval', 'revoke_approval', array(), true );
		$this->register_action( '/archive', 'archive_campaign', array(), true );
		$this->register_action( '/duplicate', 'duplicate_campaign', array( 'idempotency_key' => Campaign_Rest_Schema::idempotency_key() ), false, 'duplicate_result' );
		$this->register_action( '/provider-draft', 'create_provider_draft', array( 'idempotency_key' => Campaign_Rest_Schema::idempotency_key() ), true, 'provider_draft_result', 'can_approve_campaigns' );
		$this->register_action(
			'/test-send',
			'send_test',
			array(
				'recipients'      => Campaign_Rest_Schema::test_recipients(),
				'format'          => Campaign_Rest_Schema::test_format(),
				'idempotency_key' => Campaign_Rest_Schema::idempotency_key(),
			),
			false,
			'test_send_result',
			'can_test_campaigns'
		);
		$this->register_action(
			'/schedule',
			'schedule_campaign',
			array(
				'scheduled_for'              => Campaign_Rest_Schema::scheduled_for(),
				'confirm_audience_reference' => Campaign_Rest_Schema::confirm_audience_reference(),
				'idempotency_key'            => Campaign_Rest_Schema::idempotency_key(),
			),
			true,
			'delivery_result',
			'can_deliver_campaigns'
		);
		$this->register_action( '/unschedule', 'unschedule_campaign', array( 'idempotency_key' => Campaign_Rest_Schema::idempotency_key() ), true, 'delivery_result', 'can_deliver_campaigns' );
		$this->register_action(
			'/send',
			'send_campaign',
			array(
				'confirm_audience_reference' => Campaign_Rest_Schema::confirm_audience_reference(),
				'idempotency_key'            => Campaign_Rest_Schema::idempotency_key(),
			),
			true,
			'delivery_result',
			'can_deliver_campaigns'
		);
		$this->register_action( '/reconcile', 'reconcile_campaign', array(), false, 'reconcile_result', 'can_deliver_campaigns' );
	}

	/**
	 * Register one POST action on a campaign item.
	 *
	 * @param string                              $suffix     Route suffix.
	 * @param string                              $callback   Name of the method that handles the route.
	 * @param array<string, array<string, mixed>> $args       Additional route fields.
	 * @param bool                                $versioned  Whether the request is versioned.
	 * @param string                              $schema     JSON schema.
	 * @param string                              $permission Permission callback.
	 */
	private function register_action(
		string $suffix,
		string $callback,
		array $args,
		bool $versioned,
		string $schema = 'campaign_result',
		string $permission = 'can_access_campaigns'
	): void {
		$fields = array( 'id' => Campaign_Rest_Schema::campaign_id() );
		if ( $versioned ) {
			$fields['expected_version'] = Campaign_Rest_Schema::expected_version();
		}

		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::ITEM . $suffix,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, $callback ),
					'permission_callback' => array( Campaign_Routes::class, $permission ),
					'args'                => array_merge( $fields, $args ),
				),
				'schema' => array( Campaign_Rest_Schema::class, $schema ),
			)
		);
	}
}
