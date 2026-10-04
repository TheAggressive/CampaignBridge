<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,CampaignBridge.Standard.Sniffs.Database
/**
 * Schedule and unschedule REST route integration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Core\Encryption;
use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Domain\Provider\Discovered_Merge_Field;
use CampaignBridge\Domain\Provider\Discovery_Batch;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Domain\Provider\Discovery_Result;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Providers\Mailchimp_Discovery;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Repository\Provider_Discovery_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\REST\Campaign_Rest_Schema;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/** Proves the schedule routes drive the real scheduler against a faked Mailchimp. */
final class Campaign_Schedule_Route_Test extends Test_Case {
	private const COLLECTION = '/campaignbridge/v1/campaigns';

	private const ACTIONS = 'https://us20.api.mailchimp.com/3.0/campaigns/mc0042/actions/';

	private int $admin_id;

	/** @var array<int, int> */
	private array $template_ids = array();

	/** @var array<int, array{method: string, url: string, body: string}> Mailchimp requests. */
	private array $requests = array();

	/** @var array<int, array{0: int, 1: string}> Scripted replies for campaign actions; empty means 204. */
	private array $action_replies = array();

	/** Body Mailchimp returns when the campaign is read. */
	private string $remote_status = '{"status":"save","recipients":{"list_id":"abc123","segment_opts":{}}}';

	/** @var callable|null */
	private $filter = null;

	private static function api_key(): string {
		return str_repeat( 'c0ffee', 5 ) . 'c0-us20';
	}

	/** The next quarter-hour at least one hour from now, as Mailchimp requires. */
	private static function send_at(): string {
		return gmdate( 'Y-m-d\TH:i:s\Z', (int) ( ceil( ( time() + 3600 ) / 900 ) * 900 ) );
	}

	public function setUp(): void {
		parent::setUp();
		self::assertTrue( Schema_Manager::migrate() );
		$this->truncate();

		do_action( 'rest_api_init' );
		Routes::register();

		$this->filter = function ( mixed $preempt, array $args, string $url ): array {
			$method           = (string) ( $args['method'] ?? 'GET' );
			$this->requests[] = array(
				'method' => $method,
				'url'    => $url,
				'body'   => is_string( $args['body'] ?? null ) ? $args['body'] : '',
			);
			$reply            = match ( true ) {
				str_contains( $url, '/actions/' ) => array_shift( $this->action_replies ) ?? array( 204, '' ),
				str_contains( $url, '/campaigns/mc0042?fields=' ) => array( 200, $this->remote_status ),
				'POST' === $method                => array( 200, '{"id":"mc0042","status":"save"}' ),
				default                           => array( 200, '{}' ),
			};

			return array(
				'headers'  => array(),
				'body'     => $reply[1],
				'response' => array(
					'code'    => $reply[0],
					'message' => '',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $this->filter, 10, 3 );

		$this->admin_id = $this->create_test_user( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
		( new Provider_Connection_Repository() )->save( Provider_Connection::create( 'mailchimp', Encryption::encrypt( self::api_key() ), '' ) );
		( new Provider_Discovery_Repository() )->save(
			(string) ( new Mailchimp_Discovery() )->account_key( array( 'api_key' => self::api_key() ) ),
			Discovery_Result::create(
				'mailchimp',
				'abc123',
				Discovery_Batch::create( Discovery_Kind::MERGE_FIELDS, array( Discovered_Merge_Field::create( 'FNAME', 'First Name', 'text', false ) ), true ),
				gmdate( 'Y-m-d\TH:i:s\Z' )
			)
		);
	}

	public function tearDown(): void {
		global $wpdb;
		remove_filter( 'pre_http_request', $this->filter );
		foreach ( $this->template_ids as $template_id ) {
			wp_delete_post( $template_id, true );
		}
		( new Provider_Connection_Repository() )->delete( 'mailchimp' );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%discovery\_%' OR option_name LIKE '%rate\_limit%' OR option_name LIKE 'campaignbridge\_policy\_%'" );
		$this->truncate();
		// Workflow transactions commit, so cleanup of committed fixtures must commit too.
		$wpdb->query( 'COMMIT' );
		wp_cache_flush();
		parent::tearDown();
	}

	public function test_a_provider_draft_is_scheduled_and_unscheduled_through_mailchimp(): void {
		$campaign = $this->provider_draft_campaign();
		$send_at  = self::send_at();

		$scheduled = $this->schedule( $campaign['id'], 5, $send_at, 'abc123', 'rest-schedule-1' );
		self::assertSame( 200, $scheduled->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::delivery_result(), $scheduled );
		$data = $scheduled->get_data();
		self::assertSame( array( 'scheduled', 7, $send_at ), array( $data['campaign']['state'], $data['campaign']['version'], $data['campaign']['scheduled_for'] ) );
		self::assertSame( array( 'mc0042', 'scheduled' ), array( $data['remote']['remote_id'], $data['remote']['observed_state'] ) );
		self::assertSame( 'succeeded', $data['attempt']['status'] );
		self::assertFalse( $data['idempotent_replay'] );
		self::assertSame( 'no-store', $scheduled->get_headers()['Cache-Control'] );
		self::assertStringNotContainsString( self::api_key(), (string) wp_json_encode( $data ) );
		self::assertSame(
			array(
				'GET https://us20.api.mailchimp.com/3.0/campaigns/mc0042?fields=type,status,emails_sent,send_time,recipients.list_id,recipients.segment_opts',
				'PATCH https://us20.api.mailchimp.com/3.0/campaigns/mc0042',
				'PUT https://us20.api.mailchimp.com/3.0/campaigns/mc0042/content',
				'GET https://us20.api.mailchimp.com/3.0/campaigns/mc0042?fields=type,status,emails_sent,send_time,recipients.list_id,recipients.segment_opts',
				'POST ' . self::ACTIONS . 'schedule',
			),
			array_map( static fn ( array $request ): string => $request['method'] . ' ' . $request['url'], array_slice( $this->requests, -5 ) ),
			'The approved draft is read, re-asserted, and verified before it is scheduled.'
		);
		$patch = json_decode( $this->requests[ count( $this->requests ) - 4 ]['body'], true );
		self::assertSame( array( 'abc123', 'Spring sale' ), array( $patch['recipients']['list_id'], $patch['settings']['subject_line'] ) );
		self::assertSame( array( 'schedule_time' => str_replace( 'Z', '+00:00', $send_at ) ), json_decode( $this->requests[ count( $this->requests ) - 1 ]['body'], true ) );

		$requests = count( $this->requests );
		$replay   = $this->schedule( $campaign['id'], 5, $send_at, 'abc123', 'rest-schedule-1' );
		self::assertSame( 200, $replay->get_status() );
		self::assertTrue( $replay->get_data()['idempotent_replay'] );
		self::assertCount( $requests, $this->requests, 'A replay sends nothing to Mailchimp.' );

		$unscheduled = $this->request(
			'POST',
			"/{$campaign['id']}/unschedule",
			array(
				'expected_version' => 7,
				'idempotency_key'  => 'rest-unschedule-1',
			) 
		);
		self::assertSame( 200, $unscheduled->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::delivery_result(), $unscheduled );
		self::assertSame( array( 'provider_draft', null ), array( $unscheduled->get_data()['campaign']['state'], $unscheduled->get_data()['campaign']['scheduled_for'] ) );
		self::assertSame( 'POST ' . self::ACTIONS . 'unschedule', $this->last_request() );
		self::assertNotContains( 'POST ' . self::ACTIONS . 'send', array_map( static fn ( array $request ): string => $request['method'] . ' ' . $request['url'], $this->requests ), 'Scheduling never calls the immediate send action.' );
	}

	public function test_a_provider_draft_is_sent_through_mailchimp_and_reconciled_to_sent(): void {
		$campaign = $this->provider_draft_campaign();
		$actions  = $this->action_count();

		$wrong = $this->send( $campaign['id'], 5, 'other-audience', 'rest-send-0' );
		self::assertSame( 400, $wrong->get_status() );
		self::assertSame( $actions, $this->action_count(), 'An unconfirmed audience reaches no Mailchimp action.' );

		$sent = $this->send( $campaign['id'], 5, 'abc123', 'rest-send-1' );
		self::assertSame( 200, $sent->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::delivery_result(), $sent );
		self::assertSame( array( 'sending', 'sending' ), array( $sent->get_data()['campaign']['state'], $sent->get_data()['remote']['observed_state'] ) );
		self::assertSame( 'no-store', $sent->get_headers()['Cache-Control'] );
		self::assertStringNotContainsString( self::api_key(), (string) wp_json_encode( $sent->get_data() ) );
		self::assertSame(
			array(
				'GET https://us20.api.mailchimp.com/3.0/campaigns/mc0042?fields=type,status,emails_sent,send_time,recipients.list_id,recipients.segment_opts',
				'PATCH https://us20.api.mailchimp.com/3.0/campaigns/mc0042',
				'PUT https://us20.api.mailchimp.com/3.0/campaigns/mc0042/content',
				'GET https://us20.api.mailchimp.com/3.0/campaigns/mc0042?fields=type,status,emails_sent,send_time,recipients.list_id,recipients.segment_opts',
				'POST ' . self::ACTIONS . 'send',
			),
			array_map( static fn ( array $request ): string => $request['method'] . ' ' . $request['url'], array_slice( $this->requests, -5 ) ),
			'The approved draft is read, re-asserted, and verified before the one send action.'
		);

		$replay = $this->send( $campaign['id'], 5, 'abc123', 'rest-send-1' );
		self::assertTrue( $replay->get_data()['idempotent_replay'] );
		self::assertSame( $actions + 1, $this->action_count(), 'Exactly one send action reached Mailchimp.' );

		$this->remote_status = '{"status":"sent","send_time":"' . self::send_at() . '","recipients":{"list_id":"abc123","segment_opts":{}}}';
		$reconciled          = $this->request( 'POST', '/' . $campaign['id'] . '/reconcile' );
		self::assertSame( array( 200, 'sent' ), array( $reconciled->get_status(), $reconciled->get_data()['campaign']['state'] ) );
		self::assertSame( $actions + 1, $this->action_count() );
	}

	public function test_an_unconfirmed_send_leaves_the_campaign_unknown_and_is_never_retried(): void {
		$campaign             = $this->provider_draft_campaign();
		$actions              = $this->action_count();
		$this->action_replies = array( array( 503, '' ) );

		$unknown = $this->send( $campaign['id'], 5, 'abc123', 'rest-send-1' );
		self::assertSame( 409, $unknown->get_status() );
		self::assertSame( 'campaignbridge_campaign_reconciliation_required', $unknown->get_data()['code'] );
		self::assertSame( 'unknown', ( new Campaign_Repository() )->get( $campaign['id'] )?->state() );

		self::assertSame( 409, $this->send( $campaign['id'], 7, 'abc123', 'rest-send-2' )->get_status() );
		self::assertSame( $actions + 1, $this->action_count(), 'A 5xx on send is never retried, whatever key is sent.' );
	}

	public function test_an_unconfirmed_schedule_returns_a_conflict_and_leaves_the_campaign_unknown(): void {
		$campaign             = $this->provider_draft_campaign();
		$actions              = $this->action_count();
		$this->action_replies = array( array( 503, '{"detail":"private upstream detail"}' ) );

		$unknown = $this->schedule( $campaign['id'], 5, self::send_at(), 'abc123', 'rest-schedule-1' );
		self::assertSame( 409, $unknown->get_status() );
		self::assertSame( 'campaignbridge_campaign_reconciliation_required', $unknown->get_data()['code'] );
		$this->assert_schema( Campaign_Rest_Schema::error(), $unknown );
		self::assertSame( 'unknown', $unknown->get_data()['data']['attempt']['status'] );
		self::assertStringNotContainsString( 'private upstream detail', (string) wp_json_encode( $unknown->get_data() ) );
		self::assertSame( 'unknown', ( new Campaign_Repository() )->get( $campaign['id'] )?->state() );
		self::assertSame( 7, $unknown->get_data()['data']['current_version'], 'The client learns the version the claim and transition consumed.' );

		$again = $this->schedule( $campaign['id'], 7, self::send_at(), 'abc123', 'rest-schedule-2' );
		self::assertSame( 'campaignbridge_campaign_reconciliation_required', $again->get_data()['code'] );
		self::assertSame( $actions + 1, $this->action_count(), 'Exactly one schedule action was sent; a 5xx is never retried.' );
	}

	public function test_reconciliation_settles_an_unconfirmed_schedule_from_mailchimp_with_reads_only(): void {
		$campaign             = $this->provider_draft_campaign();
		$this->action_replies = array( array( 503, '' ) );
		self::assertSame( 409, $this->schedule( $campaign['id'], 5, self::send_at(), 'abc123', 'rest-schedule-1' )->get_status() );
		$actions             = $this->action_count();
		$writes              = count( array_filter( $this->requests, static fn ( array $request ): bool => 'GET' !== $request['method'] ) );
		$this->remote_status = '{"status":"schedule","send_time":"' . self::send_at() . '","recipients":{"list_id":"abc123","segment_opts":{}}}';

		$reconciled = $this->request( 'POST', '/' . $campaign['id'] . '/reconcile' );

		self::assertSame( 200, $reconciled->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::reconcile_result(), $reconciled );
		self::assertSame( array( 'scheduled', self::send_at() ), array( $reconciled->get_data()['campaign']['state'], $reconciled->get_data()['campaign']['scheduled_for'] ) );
		self::assertSame( 'succeeded', $reconciled->get_data()['resolved_attempts'][0]['status'] );
		self::assertNotNull( $reconciled->get_data()['remote']['reconciled_at'] );
		self::assertSame( 'no-store', $reconciled->get_headers()['Cache-Control'] );
		self::assertSame( $actions, $this->action_count(), 'Reconciliation sends no campaign action.' );
		self::assertSame( $writes, count( array_filter( $this->requests, static fn ( array $request ): bool => 'GET' !== $request['method'] ) ), 'Reconciliation only reads from Mailchimp.' );
		self::assertStringNotContainsString( self::api_key(), (string) wp_json_encode( $reconciled->get_data() ) );

		wp_set_current_user( $this->create_test_user( array( 'role' => 'editor' ) ) );
		self::assertContains( $this->request( 'POST', '/' . $campaign['id'] . '/reconcile' )->get_status(), array( 401, 403 ) );
	}

	public function test_a_provider_refusal_reports_the_claimed_version_for_a_retry(): void {
		$campaign             = $this->provider_draft_campaign();
		$this->action_replies = array( array( 400, '{"detail":"private validation detail"}' ) );

		$refused = $this->schedule( $campaign['id'], 5, self::send_at(), 'abc123', 'rest-schedule-1' );
		self::assertSame( 502, $refused->get_status() );
		self::assertSame( 'campaignbridge_campaign_provider_failed', $refused->get_data()['code'] );
		$this->assert_schema( Campaign_Rest_Schema::error(), $refused );
		self::assertSame( array( 6, 'mailchimp_request_rejected' ), array( $refused->get_data()['data']['current_version'], $refused->get_data()['data']['provider_error']['code'] ) );
		self::assertStringNotContainsString( 'private validation detail', (string) wp_json_encode( $refused->get_data() ) );
		self::assertSame( 'provider_draft', ( new Campaign_Repository() )->get( $campaign['id'] )?->state() );

		self::assertSame( 200, $this->schedule( $campaign['id'], 6, self::send_at(), 'abc123', 'rest-schedule-2' )->get_status() );
	}

	public function test_invalid_requests_fail_before_any_provider_call(): void {
		$campaign = $this->provider_draft_campaign();
		$requests = count( $this->requests );

		self::assertSame(
			'rest_missing_callback_param',
			$this->request(
				'POST',
				"/{$campaign['id']}/schedule",
				array(
					'expected_version' => 5,
					'idempotency_key'  => 'k',
					'scheduled_for'    => self::send_at(),
				) 
			)->get_data()['code'] 
		);
		self::assertSame( 'rest_invalid_param', $this->schedule( $campaign['id'], 5, '2030-01-01T09:00:00', 'abc123', 'k' )->get_data()['code'], 'A time without an offset is ambiguous.' );
		self::assertSame( 'rest_invalid_param', $this->schedule( $campaign['id'], 5, 'next tuesday', 'abc123', 'k' )->get_data()['code'] );

		$mismatch = $this->schedule( $campaign['id'], 5, self::send_at(), 'other-audience', 'k' );
		self::assertSame( 400, $mismatch->get_status() );
		self::assertSame( 'campaignbridge_campaign_invalid_input', $mismatch->get_data()['code'] );

		$off_interval = $this->schedule( $campaign['id'], 5, gmdate( 'Y-m-d\TH:i:s\Z', strtotime( self::send_at() ) + 300 ), 'abc123', 'k' );
		self::assertSame( 'campaignbridge_campaign_invalid_input', $off_interval->get_data()['code'] );
		self::assertStringContainsString( '15-minute', $off_interval->get_data()['message'] );

		self::assertCount( $requests, $this->requests, 'Invalid requests never reach Mailchimp.' );
		self::assertSame( 5, ( new Campaign_Repository() )->get( $campaign['id'] )?->version() );
	}

	public function test_the_stored_separation_policy_stops_the_approver_over_rest(): void {
		$campaign = $this->provider_draft_campaign();
		self::assertSame( $this->admin_id, $campaign['approved_by_user_id'] );
		update_option( 'campaignbridge_policy_separate_delivery', 1 );
		$actions = $this->action_count();

		$denied = $this->schedule( $campaign['id'], 5, self::send_at(), 'abc123', 'rest-schedule-1' );
		self::assertSame( 403, $denied->get_status() );
		self::assertSame( 'campaignbridge_campaign_forbidden', $denied->get_data()['code'] );
		self::assertSame( array( 'status' => 403 ), $denied->get_data()['data'], 'A denial reveals no campaign state.' );
		self::assertSame( $actions, $this->action_count() );

		wp_set_current_user( $this->create_test_user( array( 'role' => 'administrator' ) ) );
		self::assertSame( 200, $this->schedule( $campaign['id'], 5, self::send_at(), 'abc123', 'rest-schedule-2' )->get_status(), 'A second person may schedule.' );
	}

	public function test_scheduling_requires_delivery_authority(): void {
		$campaign = $this->provider_draft_campaign();
		$requests = count( $this->requests );

		$tester = $this->create_test_user( array( 'role' => 'subscriber' ) );
		get_userdata( $tester )->add_cap( Capabilities::CREATE_CAMPAIGNS );
		get_userdata( $tester )->add_cap( Capabilities::TEST_CAMPAIGNS );
		wp_set_current_user( $tester );

		$denied = $this->schedule( $campaign['id'], 5, self::send_at(), 'abc123', 'k' );
		self::assertSame( 403, $denied->get_status() );
		self::assertSame( 'rest_forbidden', $denied->get_data()['code'] );
		self::assertSame(
			403,
			$this->request(
				'POST',
				"/{$campaign['id']}/unschedule",
				array(
					'expected_version' => 5,
					'idempotency_key'  => 'k',
				) 
			)->get_status() 
		);
		self::assertCount( $requests, $this->requests );
		self::assertSame( 'provider_draft', ( new Campaign_Repository() )->get( $campaign['id'] )?->state() );
	}

	/** @return array<string, mixed> */
	private function provider_draft_campaign(): array {
		$template             = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Schedule route template',
				'post_content' => '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:paragraph --><p>Hello {{cb:subscriber.first_name}}</p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->',
				'post_author'  => $this->admin_id,
			)
		);
		$this->template_ids[] = $template;
		update_post_meta( $template, 'campaignbridge_subject', 'Spring sale' );
		update_post_meta( $template, 'campaignbridge_sender_name', 'Example Shop' );
		update_post_meta( $template, 'campaignbridge_sender_email', 'news@example.com' );

		$campaign = $this->request(
			'POST',
			'',
			array(
				'template_id'        => $template,
				'provider'           => 'mailchimp',
				'audience_reference' => 'abc123',
			)
		)->get_data()['campaign'];
		self::assertSame( 200, $this->request( 'POST', "/{$campaign['id']}/snapshot", array( 'expected_version' => 1 ) )->get_status() );
		self::assertSame( 200, $this->request( 'POST', "/{$campaign['id']}/submit", array( 'expected_version' => 2 ) )->get_status() );
		self::assertSame( 200, $this->request( 'POST', "/{$campaign['id']}/approve", array( 'expected_version' => 3 ) )->get_status() );
		$draft = $this->request(
			'POST',
			"/{$campaign['id']}/provider-draft",
			array(
				'expected_version' => 4,
				'idempotency_key'  => 'rest-draft-1',
			) 
		);
		self::assertSame( 201, $draft->get_status() );

		return $draft->get_data()['campaign'];
	}

	private function schedule( string $campaign_id, int $version, string $scheduled_for, string $confirm, string $key ): WP_REST_Response {
		return $this->request(
			'POST',
			"/{$campaign_id}/schedule",
			array(
				'expected_version'           => $version,
				'scheduled_for'              => $scheduled_for,
				'confirm_audience_reference' => $confirm,
				'idempotency_key'            => $key,
			)
		);
	}

	/** Delivery actions sent to Mailchimp, excluding draft reads and re-assertion. */
	private function send( string $campaign_id, int $version, string $confirm, string $key ): WP_REST_Response {
		return $this->request(
			'POST',
			"/{$campaign_id}/send",
			array(
				'expected_version'           => $version,
				'confirm_audience_reference' => $confirm,
				'idempotency_key'            => $key,
			)
		);
	}

	private function action_count(): int {
		return count( array_filter( $this->requests, static fn ( array $request ): bool => str_contains( $request['url'], '/actions/' ) ) );
	}

	private function last_request(): string {
		$request = $this->requests[ count( $this->requests ) - 1 ];

		return $request['method'] . ' ' . $request['url'];
	}

	/** @param array<string, mixed> $schema Published schema. */
	private function assert_schema( array $schema, WP_REST_Response $response ): void {
		$valid = rest_validate_value_from_schema( $response->get_data(), $schema, 'response' );
		self::assertTrue( true === $valid, is_wp_error( $valid ) ? $valid->get_error_message() : '' );
	}

	private function truncate(): void {
		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted isolated test tables.
		}
	}

	/** @param array<string, mixed> $params Request parameters. */
	private function request( string $method, string $suffix, array $params = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, self::COLLECTION . $suffix );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}
}
