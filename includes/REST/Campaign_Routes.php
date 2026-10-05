<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Thin REST adapter for canonical campaign workflows.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST;

use CampaignBridge\Core\Campaign_Authorizer;
use CampaignBridge\Core\Capabilities;
use CampaignBridge\Domain\Campaign\Campaign;
use CampaignBridge\Domain\Campaign\Campaign_List_Filter;
use CampaignBridge\Services\Campaign\Campaign_Draft_Handoff_Factory;
use CampaignBridge\Services\Campaign\Campaign_Reconciler_Factory;
use CampaignBridge\Services\Campaign\Campaign_Scheduler_Factory;
use CampaignBridge\Services\Campaign\Campaign_Test_Delivery_Factory;
use CampaignBridge\Services\Campaign\Campaign_Workflow_Factory;
use CampaignBridge\Services\Provider\Provider_Discovery_Factory;
use CampaignBridge\Workflow\Campaign\Campaign_Actor;
use CampaignBridge\Workflow\Campaign\Campaign_Delivery_Result;
use CampaignBridge\Workflow\Campaign\Campaign_Scheduler;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Error;
use CampaignBridge\Workflow\Campaign\Campaign_Workflow_Result;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authenticates transport input, invokes Campaign_Workflow, and maps results.
 *
 * Lifecycle, ownership, template, and concurrency decisions belong to the
 * workflow; this class only shapes transport input and output.
 */
final class Campaign_Routes extends Abstract_Rest_Controller {
	private const COLLECTION = '/campaigns';
	private const ITEM       = '/campaigns/(?P<id>[a-z0-9][a-z0-9_-]{0,63})';

	/** Per-user limits for requests that compile, capture, or create records. */
	private const EXPENSIVE_LIMIT = 10;

	/** Per-user limit for cheaper versioned lifecycle mutations. */
	private const MUTATION_LIMIT = Rest_Constants::RATE_LIMIT_REQUESTS;

	/**
	 * Canonical application workflow.
	 *
	 * @var Campaign_Workflow
	 */
	private Campaign_Workflow $workflow;

	/**
	 * WordPress user-to-actor adapter.
	 *
	 * @var Campaign_Authorizer
	 */
	private Campaign_Authorizer $authorizer;

	public function __construct(
		?Campaign_Workflow $workflow = null,
		?Campaign_Authorizer $authorizer = null
	) {
		$this->workflow   = $workflow ?? Campaign_Workflow_Factory::create();
		$this->authorizer = $authorizer ?? new Campaign_Authorizer();
	}

	public function register(): void {
		\register_rest_route(
			Rest_Constants::API_NAMESPACE,
			self::COLLECTION,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_campaigns' ),
					'permission_callback' => array( __CLASS__, 'can_access_campaigns' ),
					'args'                => Campaign_Rest_Schema::collection_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_campaign' ),
					'permission_callback' => array( __CLASS__, 'can_access_campaigns' ),
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
					'callback'            => array( $this, 'get_campaign' ),
					'permission_callback' => array( __CLASS__, 'can_access_campaigns' ),
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

	/** A useful coarse gate; exact campaign/template authorization remains in the workflow. */
	public static function can_access_campaigns(): bool {
		return 0 < get_current_user_id()
			&& ( current_user_can( Capabilities::CREATE_CAMPAIGNS ) || current_user_can( Capabilities::MANAGE ) );
	}

	/** Approval additionally requires the dedicated approval capability. */
	public static function can_approve_campaigns(): bool {
		return self::can_access_campaigns() && current_user_can( Capabilities::SEND_CAMPAIGNS );
	}

	/** Scheduling and sending require the same delivery capability as approval. */
	public static function can_deliver_campaigns(): bool {
		return self::can_access_campaigns() && current_user_can( Capabilities::SEND_CAMPAIGNS );
	}

	/** Test sends require their own capability, separate from approval and send. */
	public static function can_test_campaigns(): bool {
		return self::can_access_campaigns() && current_user_can( Capabilities::TEST_CAMPAIGNS );
	}

	public function list_campaigns( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$actor    = $this->actor();
		$owner_id = $request->has_param( 'owner_user_id' ) ? (int) $request->get_param( 'owner_user_id' ) : $actor->user_id();
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );
		$filter   = new Campaign_List_Filter( array_values( (array) ( $request->get_param( 'state' ) ?? array() ) ), $this->nullable_string( $request, 'provider' ) );
		$result   = $this->workflow->list( $actor, $owner_id, $per_page, ( $page - 1 ) * $per_page, $filter );
		if ( ! $result->is_success() ) {
			return Campaign_Rest_Errors::from_error( $result->error() );
		}

		$total       = $result->total();
		$total_pages = (int) ceil( $total / $per_page );
		if ( 1 < $page && $page > $total_pages ) {
			return new WP_Error(
				'campaignbridge_campaign_invalid_page',
				__( 'The requested campaign page does not exist.', 'campaignbridge' ),
				array( 'status' => Rest_Constants::HTTP_BAD_REQUEST )
			);
		}

		$response = new WP_REST_Response(
			array(
				'items'      => array_map( static fn ( Campaign $campaign ): array => Campaign_Rest_Resource::campaign( $campaign, $actor ), $result->campaigns() ),
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
		return $this->no_store( $response );
	}

	public function get_campaign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->campaign_response( $this->workflow->get( $this->actor(), $this->campaign_id( $request ) ) );
	}

	public function create_campaign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$limited = $this->rate_limit( 'campaign_create', self::EXPENSIVE_LIMIT );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$actor    = $this->actor();
		$owner_id = $request->has_param( 'owner_user_id' ) ? (int) $request->get_param( 'owner_user_id' ) : $actor->user_id();
		return $this->campaign_response(
			$this->workflow->create(
				$actor,
				$owner_id,
				(int) $request->get_param( 'template_id' ),
				$this->nullable_string( $request, 'provider' ),
				$this->nullable_string( $request, 'audience_reference' )
			),
			Rest_Constants::HTTP_CREATED
		);
	}

	public function update_template( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->versioned(
			$request,
			'campaign_template',
			fn ( Campaign_Actor $actor, string $id, int $version ): Campaign_Workflow_Result => $this->workflow->edit_template(
				$actor,
				$id,
				$version,
				(int) $request->get_param( 'template_id' )
			)
		);
	}

	/**
	 * Targeting is replaced as one pair. Both keys are required, but null is a
	 * meaningful value (clear), which WordPress `required` would treat as missing.
	 */
	public function update_targeting( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$missing = array_values(
			array_filter(
				array( 'provider', 'audience_reference' ),
				static fn ( string $key ): bool => ! $request->has_param( $key )
			)
		);
		if ( array() !== $missing ) {
			return new WP_Error(
				'rest_missing_callback_param',
				/* translators: %s: comma-separated parameter names. */
				sprintf( __( 'Missing parameter(s): %s', 'campaignbridge' ), implode( ', ', $missing ) ),
				array(
					'status' => Rest_Constants::HTTP_BAD_REQUEST,
					'params' => $missing,
				)
			);
		}

		return $this->versioned(
			$request,
			'campaign_targeting',
			fn ( Campaign_Actor $actor, string $id, int $version ): Campaign_Workflow_Result => $this->workflow->select_audience(
				$actor,
				$id,
				$version,
				$this->nullable_string( $request, 'provider' ),
				$this->nullable_string( $request, 'audience_reference' )
			)
		);
	}

	public function snapshot_campaign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$limited = $this->rate_limit( 'campaign_snapshot', self::EXPENSIVE_LIMIT );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$result   = $this->workflow->snapshot( $this->actor(), $this->campaign_id( $request ), $this->expected_version( $request ) );
		$campaign = $result->campaign();
		$snapshot = $result->snapshot();
		$compiled = $result->compile_result();
		if ( ! $result->is_success() || null === $campaign || null === $snapshot || null === $compiled ) {
			return Campaign_Rest_Errors::from_result( $result );
		}

		return $this->no_store(
			new WP_REST_Response(
				array(
					'campaign'   => Campaign_Rest_Resource::campaign( $campaign, $this->actor() ),
					'snapshot'   => Campaign_Rest_Resource::snapshot( $snapshot ),
					'validation' => Campaign_Rest_Resource::validation( $compiled ),
				)
			)
		);
	}

	public function validate_campaign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->compile_response( $request, true );
	}

	public function preview_campaign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->compile_response( $request, false );
	}

	public function submit_campaign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->versioned(
			$request,
			'campaign_submit',
			fn ( Campaign_Actor $actor, string $id, int $version ): Campaign_Workflow_Result => $this->workflow->submit_for_review( $actor, $id, $version )
		);
	}

	public function approve_campaign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->versioned(
			$request,
			'campaign_approve',
			fn ( Campaign_Actor $actor, string $id, int $version ): Campaign_Workflow_Result => $this->workflow->approve( $actor, $id, $version )
		);
	}

	public function revoke_approval( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->versioned(
			$request,
			'campaign_revoke_approval',
			fn ( Campaign_Actor $actor, string $id, int $version ): Campaign_Workflow_Result => $this->workflow->revoke_approval( $actor, $id, $version )
		);
	}

	public function archive_campaign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->versioned(
			$request,
			'campaign_archive',
			fn ( Campaign_Actor $actor, string $id, int $version ): Campaign_Workflow_Result => $this->workflow->archive( $actor, $id, $version )
		);
	}

	public function duplicate_campaign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$limited = $this->rate_limit( 'campaign_duplicate', self::EXPENSIVE_LIMIT );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$result   = $this->workflow->duplicate( $this->actor(), $this->campaign_id( $request ), (string) $request->get_param( 'idempotency_key' ) );
		$campaign = $result->campaign();
		if ( ! $result->is_success() || null === $campaign ) {
			return Campaign_Rest_Errors::from_result( $result );
		}

		return $this->no_store(
			new WP_REST_Response(
				array(
					'campaign'          => Campaign_Rest_Resource::campaign( $campaign, $this->actor() ),
					'idempotent_replay' => $result->is_idempotent_replay(),
				),
				$result->is_idempotent_replay() ? 200 : Rest_Constants::HTTP_CREATED
			)
		);
	}

	/**
	 * Create, resume, or replay the campaign's remote provider draft.
	 *
	 * The provider comes from the campaign's own targeting. Credentials are
	 * decrypted for this one call and never returned. This never schedules or
	 * sends.
	 */
	public function create_provider_draft( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$limited = $this->rate_limit( 'campaign_provider_draft', self::EXPENSIVE_LIMIT );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$actor  = $this->actor();
		$loaded = $this->workflow->get( $actor, $this->campaign_id( $request ) );
		if ( ! $loaded->is_success() || null === $loaded->campaign() ) {
			return Campaign_Rest_Errors::from_result( $loaded );
		}
		$provider = $loaded->campaign()->provider();
		$handoff  = null === $provider ? null : Campaign_Draft_Handoff_Factory::create( $provider );
		if ( null === $provider || null === $handoff ) {
			return new WP_Error(
				'campaignbridge_campaign_invalid_input',
				__( 'The campaign must target a provider that supports remote drafts.', 'campaignbridge' ),
				array( 'status' => Rest_Constants::HTTP_BAD_REQUEST )
			);
		}

		$settings = Provider_Discovery_Factory::settings( $provider );
		$result   = $handoff->create_draft(
			$actor,
			$this->campaign_id( $request ),
			$this->expected_version( $request ),
			(string) $request->get_param( 'idempotency_key' ),
			$settings
		);
		unset( $settings );

		$campaign  = $result->campaign();
		$reference = $result->reference();
		if ( ! $result->is_success() || null === $campaign || null === $reference ) {
			return Campaign_Rest_Errors::from_remote( $result );
		}

		return $this->no_store(
			new WP_REST_Response(
				array(
					'campaign'          => Campaign_Rest_Resource::campaign( $campaign, $this->actor() ),
					'remote'            => Campaign_Rest_Resource::remote( $reference ),
					'attempt'           => null === $result->attempt() ? null : Campaign_Rest_Resource::attempt( $result->attempt() ),
					'idempotent_replay' => $result->is_idempotent_replay(),
				),
				$result->is_idempotent_replay() ? 200 : Rest_Constants::HTTP_CREATED
			)
		);
	}

	/**
	 * Send one test of the campaign's remote draft to named addresses.
	 *
	 * The provider comes from the campaign's own targeting. Credentials and
	 * recipients are used for this one call and never returned or stored.
	 * The campaign's state and version never change.
	 */
	public function send_test( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$limited = $this->rate_limit( 'campaign_test_send', self::EXPENSIVE_LIMIT );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$actor  = $this->actor();
		$loaded = $this->workflow->get( $actor, $this->campaign_id( $request ) );
		if ( ! $loaded->is_success() || null === $loaded->campaign() ) {
			return Campaign_Rest_Errors::from_result( $loaded );
		}
		$provider = $loaded->campaign()->provider();
		$delivery = null === $provider ? null : Campaign_Test_Delivery_Factory::create( $provider );
		if ( null === $provider || null === $delivery ) {
			return new WP_Error(
				'campaignbridge_campaign_invalid_input',
				__( 'The campaign must target a provider that supports test sends.', 'campaignbridge' ),
				array( 'status' => Rest_Constants::HTTP_BAD_REQUEST )
			);
		}

		$recipients = $request->get_param( 'recipients' );
		$settings   = Provider_Discovery_Factory::settings( $provider );
		$result     = $delivery->send_test(
			$actor,
			$this->campaign_id( $request ),
			is_array( $recipients ) ? $recipients : array(),
			(string) $request->get_param( 'format' ),
			(string) $request->get_param( 'idempotency_key' ),
			$settings
		);
		unset( $settings, $recipients );

		$campaign = $result->campaign();
		$attempt  = $result->attempt();
		if ( ! $result->is_success() || null === $campaign || null === $attempt ) {
			return Campaign_Rest_Errors::from_remote( $result );
		}

		return $this->no_store(
			new WP_REST_Response(
				array(
					'campaign'          => Campaign_Rest_Resource::campaign( $campaign, $this->actor() ),
					'remote'            => null === $result->reference() ? null : Campaign_Rest_Resource::remote( $result->reference() ),
					'attempt'           => Campaign_Rest_Resource::attempt( $attempt ),
					'test'              => Campaign_Rest_Resource::test( $result ),
					'idempotent_replay' => $result->is_idempotent_replay(),
				),
				$result->is_idempotent_replay() ? 200 : Rest_Constants::HTTP_ACCEPTED
			)
		);
	}

	/**
	 * Schedule the campaign's remote draft to send to its audience.
	 *
	 * The operator must echo the campaign's audience reference, so a stale
	 * screen or a mistargeted call cannot schedule the wrong audience.
	 */
	public function schedule_campaign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->delivery(
			$request,
			'campaign_schedule',
			fn ( Campaign_Scheduler $scheduler, Campaign_Actor $actor, array $settings ): Campaign_Delivery_Result => $scheduler->schedule(
				$actor,
				$this->campaign_id( $request ),
				$this->expected_version( $request ),
				(string) $request->get_param( 'scheduled_for' ),
				(string) $request->get_param( 'confirm_audience_reference' ),
				(string) $request->get_param( 'idempotency_key' ),
				$settings
			)
		);
	}

	/**
	 * Send the campaign's remote draft to its audience now.
	 *
	 * Irreversible. The operator must echo the campaign's audience reference,
	 * exactly as for scheduling.
	 */
	public function send_campaign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->delivery(
			$request,
			'campaign_send',
			fn ( Campaign_Scheduler $scheduler, Campaign_Actor $actor, array $settings ): Campaign_Delivery_Result => $scheduler->send(
				$actor,
				$this->campaign_id( $request ),
				$this->expected_version( $request ),
				(string) $request->get_param( 'confirm_audience_reference' ),
				(string) $request->get_param( 'idempotency_key' ),
				$settings
			)
		);
	}

	/** Return a scheduled campaign to its provider draft before it sends. */
	public function unschedule_campaign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->delivery(
			$request,
			'campaign_unschedule',
			fn ( Campaign_Scheduler $scheduler, Campaign_Actor $actor, array $settings ): Campaign_Delivery_Result => $scheduler->unschedule(
				$actor,
				$this->campaign_id( $request ),
				$this->expected_version( $request ),
				(string) $request->get_param( 'idempotency_key' ),
				$settings
			)
		);
	}

	/**
	 * Settle unconfirmed outcomes and follow what the provider reports.
	 *
	 * Read-only toward the provider and idempotent, so it takes neither an
	 * expected version nor an idempotency key.
	 */
	public function reconcile_campaign( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$limited = $this->rate_limit( 'campaign_reconcile', self::EXPENSIVE_LIMIT );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$actor  = $this->actor();
		$loaded = $this->workflow->get( $actor, $this->campaign_id( $request ) );
		if ( ! $loaded->is_success() || null === $loaded->campaign() ) {
			return Campaign_Rest_Errors::from_result( $loaded );
		}
		$provider   = $loaded->campaign()->provider();
		$reconciler = null === $provider ? null : Campaign_Reconciler_Factory::create( $provider );
		if ( null === $provider || null === $reconciler ) {
			return new WP_Error(
				'campaignbridge_campaign_invalid_input',
				__( 'The campaign must target a provider that supports reconciliation.', 'campaignbridge' ),
				array( 'status' => Rest_Constants::HTTP_BAD_REQUEST )
			);
		}

		$settings = Provider_Discovery_Factory::settings( $provider );
		$result   = $reconciler->reconcile( $actor, $this->campaign_id( $request ), $settings );
		unset( $settings );

		$campaign = $result->campaign();
		if ( ! $result->is_success() || null === $campaign ) {
			return Campaign_Rest_Errors::from_remote( $result );
		}

		return $this->no_store(
			new WP_REST_Response(
				array(
					'campaign'          => Campaign_Rest_Resource::campaign( $campaign, $this->actor() ),
					'remote'            => null === $result->reference() ? null : Campaign_Rest_Resource::remote( $result->reference() ),
					'resolved_attempts' => array_map( array( Campaign_Rest_Resource::class, 'attempt' ), $result->resolved_attempts() ),
				)
			)
		);
	}

	/**
	 * Resolve the campaign's provider, run one delivery operation, and map it.
	 *
	 * Credentials are decrypted for this one call and never returned.
	 *
	 * @param callable(Campaign_Scheduler, Campaign_Actor, array<string, mixed>): Campaign_Delivery_Result $operation Workflow call.
	 */
	private function delivery( WP_REST_Request $request, string $rate_key, callable $operation ): WP_REST_Response|WP_Error {
		$limited = $this->rate_limit( $rate_key, self::EXPENSIVE_LIMIT );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$actor  = $this->actor();
		$loaded = $this->workflow->get( $actor, $this->campaign_id( $request ) );
		if ( ! $loaded->is_success() || null === $loaded->campaign() ) {
			return Campaign_Rest_Errors::from_result( $loaded );
		}
		$provider  = $loaded->campaign()->provider();
		$scheduler = null === $provider ? null : Campaign_Scheduler_Factory::create( $provider );
		if ( null === $provider || null === $scheduler ) {
			return new WP_Error(
				'campaignbridge_campaign_invalid_input',
				__( 'The campaign must target a provider that supports delivery.', 'campaignbridge' ),
				array( 'status' => Rest_Constants::HTTP_BAD_REQUEST )
			);
		}

		$settings = Provider_Discovery_Factory::settings( $provider );
		$result   = $operation( $scheduler, $actor, $settings );
		unset( $settings );

		$campaign  = $result->campaign();
		$reference = $result->reference();
		if ( ! $result->is_success() || null === $campaign || null === $reference ) {
			return Campaign_Rest_Errors::from_remote( $result );
		}

		return $this->no_store(
			new WP_REST_Response(
				array(
					'campaign'          => Campaign_Rest_Resource::campaign( $campaign, $this->actor() ),
					'remote'            => Campaign_Rest_Resource::remote( $reference ),
					'attempt'           => null === $result->attempt() ? null : Campaign_Rest_Resource::attempt( $result->attempt() ),
					'idempotent_replay' => $result->is_idempotent_replay(),
				)
			)
		);
	}

	/**
	 * Register one POST action on a campaign item.
	 *
	 * @param array<string, array<string, mixed>> $args Additional route fields.
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
					'callback'            => array( $this, $callback ),
					'permission_callback' => array( __CLASS__, $permission ),
					'args'                => array_merge( $fields, $args ),
				),
				'schema' => array( Campaign_Rest_Schema::class, $schema ),
			)
		);
	}

	/**
	 * Validation diagnostics are the requested resource on compile routes, so
	 * they return 200 like POST /preview; every other failure uses the shared map.
	 */
	private function compile_response( WP_REST_Request $request, bool $validate ): WP_REST_Response|WP_Error {
		$limited = $this->rate_limit( $validate ? 'campaign_validation' : 'campaign_preview', self::EXPENSIVE_LIMIT );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$result = $validate
			? $this->workflow->validate( $this->actor(), $this->campaign_id( $request ) )
			: $this->workflow->preview( $this->actor(), $this->campaign_id( $request ) );

		$campaign = $result->campaign();
		$compiled = $result->compile_result();
		$reported = $result->is_success() || Campaign_Workflow_Error::VALIDATION_FAILED === $result->error()?->code();
		if ( ! $reported || null === $campaign || null === $compiled ) {
			return Campaign_Rest_Errors::from_result( $result );
		}

		return $this->no_store(
			new WP_REST_Response(
				array(
					'campaign'                           => Campaign_Rest_Resource::campaign( $campaign, $this->actor() ),
					$validate ? 'validation' : 'preview' => $validate
						? Campaign_Rest_Resource::validation( $compiled )
						: Campaign_Rest_Resource::preview( $compiled ),
				)
			)
		);
	}

	/** @param callable(Campaign_Actor, string, int): Campaign_Workflow_Result $operation Workflow call. */
	private function versioned( WP_REST_Request $request, string $rate_key, callable $operation ): WP_REST_Response|WP_Error {
		$limited = $this->rate_limit( $rate_key, self::MUTATION_LIMIT );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		return $this->campaign_response(
			$operation( $this->actor(), $this->campaign_id( $request ), $this->expected_version( $request ) )
		);
	}

	private function campaign_response( Campaign_Workflow_Result $result, int $status = 200 ): WP_REST_Response|WP_Error {
		$campaign = $result->campaign();
		if ( ! $result->is_success() || null === $campaign ) {
			return Campaign_Rest_Errors::from_result( $result );
		}

		return $this->no_store(
			new WP_REST_Response( array( 'campaign' => Campaign_Rest_Resource::campaign( $campaign, $this->actor() ) ), $status )
		);
	}

	private function actor(): Campaign_Actor {
		return $this->authorizer->actor( get_current_user_id() );
	}

	private function campaign_id( WP_REST_Request $request ): string {
		return (string) $request->get_param( 'id' );
	}

	private function expected_version( WP_REST_Request $request ): int {
		return (int) $request->get_param( 'expected_version' );
	}

	private function rate_limit( string $key, int $maximum ): bool|WP_Error {
		return Rate_Limiter::check_rate_limit_authenticated(
			$key,
			Rest_Constants::CACHE_KEY_PREFIX_GENERAL,
			$maximum,
			Rest_Constants::RATE_LIMIT_WINDOW
		);
	}

	/** Versioned state must never be served from an intermediary cache. */
	private function no_store( WP_REST_Response $response ): WP_REST_Response {
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	private function nullable_string( WP_REST_Request $request, string $key ): ?string {
		$value = $request->get_param( $key );
		return is_string( $value ) && '' !== $value ? $value : null;
	}
}
