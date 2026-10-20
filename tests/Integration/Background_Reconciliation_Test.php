<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,Generic.Files.OneObjectStructurePerFile,CampaignBridge.Standard.Sniffs.Database
/**
 * Background reconciliation of scheduled, sending, and unknown campaigns.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Core\Encryption;
use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Domain\Job\Job_State;
use CampaignBridge\Domain\Provider\Discovered_Merge_Field;
use CampaignBridge\Domain\Provider\Discovery_Batch;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Domain\Provider\Discovery_Result;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Providers\Mailchimp_Draft_Gateway;
use CampaignBridge\Providers\Mailchimp_Discovery;
use CampaignBridge\Providers\Mailchimp_Provider;
use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Database_Transaction;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Job_Repository;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Repository\Provider_Discovery_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\REST\Routes;
use CampaignBridge\Services\Lock\Lock_Factory;
use CampaignBridge\Services\Provider\Provider_Discovery_Factory;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Workflow\Campaign\Campaign_Clock;
use CampaignBridge\Workflow\Campaign\Campaign_Reconcile_Job;
use CampaignBridge\Workflow\Campaign\Campaign_Reconciler;
use CampaignBridge\Workflow\Campaign\Random_Id_Generator;
use CampaignBridge\Workflow\Campaign\Reconcile_Watch;
use CampaignBridge\Workflow\Campaign\Reconciliation_Sweep;
use CampaignBridge\Workflow\Job\Job_Queue;
use CampaignBridge\Workflow\Job\Job_Runner;
use WP_REST_Request;
use WP_REST_Response;

final class Watch_Test_Clock implements Campaign_Clock {
	public int $now;

	public function __construct() {
		$this->now = time();
	}

	public function now(): string {
		return gmdate( 'Y-m-d\TH:i:s\Z', $this->now );
	}
}

/** Proves campaigns settle on their own, wait out the settle window, and stop for an operator only when they must. */
final class Background_Reconciliation_Test extends Test_Case {
	private const COLLECTION = '/campaignbridge/v1/campaigns';

	private int $admin_id;

	/** @var array<int, int> */
	private array $template_ids = array();

	/** @var array<int, array{method: string, url: string}> Mailchimp requests. */
	private array $requests = array();

	/** @var array<int, array{0: int, 1: string}> Scripted replies for campaign actions; empty means 204. */
	private array $action_replies = array();

	/** Status and body Mailchimp returns when the campaign is read. */
	private array $remote = array( 200, '{"type":"regular","status":"save","emails_sent":0,"recipients":{"list_id":"abc123","segment_opts":{}}}' );

	/** @var callable|null */
	private $filter = null;

	private Watch_Test_Clock $clock;

	private Job_Runner $runner;

	private Reconciliation_Sweep $sweep;

	private static function api_key(): string {
		return str_repeat( 'c0ffee', 5 ) . 'c0-us20';
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
			);
			$reply            = match ( true ) {
				str_contains( $url, '/actions/' ) => array_shift( $this->action_replies ) ?? array( 204, '' ),
				str_contains( $url, '/campaigns/mc0042?fields=' ) => $this->remote,
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

		$this->clock  = new Watch_Test_Clock();
		$jobs         = new Job_Repository();
		$handler      = new Campaign_Reconcile_Job(
			new Campaign_Repository(),
			fn ( string $provider ) => $this->reconciler( $provider ),
			static fn ( string $provider ): array => Provider_Discovery_Factory::settings( $provider ),
			$this->clock
		);
		$this->sweep  = new Reconciliation_Sweep( new Campaign_Repository(), $jobs, new Job_Queue( $jobs, $this->clock ), $this->clock );
		$this->runner = new Job_Runner( $jobs, array( $handler, $this->sweep ), $this->clock, 'worker-test', static fn (): int => 0 );
	}

	public function tearDown(): void {
		global $wpdb;
		remove_filter( 'pre_http_request', $this->filter );
		foreach ( $this->template_ids as $template_id ) {
			wp_delete_post( $template_id, true );
		}
		( new Provider_Connection_Repository() )->delete( 'mailchimp' );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%discovery\_%' OR option_name LIKE '%rate\_limit%'" );
		$this->truncate();
		$wpdb->query( 'COMMIT' );
		wp_cache_flush();
		parent::tearDown();
	}

	public function test_a_scheduled_campaign_is_reconciled_to_sent_in_the_background(): void {
		$campaign = $this->provider_draft_campaign();
		$send_at  = gmdate( 'Y-m-d\TH:i:s\Z', (int) ( ceil( ( time() + 3600 ) / 900 ) * 900 ) );
		self::assertSame( 200, $this->request( 'POST', "/{$campaign['id']}/schedule", array( 'expected_version' => 5, 'scheduled_for' => $send_at, 'confirm_audience_reference' => 'abc123', 'idempotency_key' => 'bg-schedule' ) )->get_status() );
		$reads = $this->reads();

		self::assertSame( 1, $this->sweep->sweep() );
		$watch = Reconcile_Watch::of( new Job_Repository(), $this->campaign( $campaign['id'] ) );
		self::assertSame( Reconcile_Watch::WATCHING, $watch->state );
		self::assertSame( gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $send_at ) + 120 ), $watch->next_check_at, 'The first check is two minutes after the send time.' );

		$this->runner->run( 10 );
		self::assertSame( $reads, $this->reads(), 'Nothing is read before the send time.' );

		// Two hours later, Mailchimp has sent it.
		$this->clock->now += 2 * HOUR_IN_SECONDS;
		$this->remote      = array( 200, '{"type":"regular","status":"sent","emails_sent":3,"send_time":"' . $send_at . '","recipients":{"list_id":"abc123","segment_opts":{}}}' );
		$this->runner->run( 10 );

		self::assertSame( 'sent', $this->campaign( $campaign['id'] )->state() );
		self::assertSame( Reconcile_Watch::IDLE, Reconcile_Watch::of( new Job_Repository(), $this->campaign( $campaign['id'] ) )->state, 'A sent campaign is no longer watched.' );
		$event = ( new Audit_Event_Repository() )->for_target( 'campaign', $campaign['id'], 1 )[0];
		self::assertSame( 'campaign_reconcile', $event->action() );
		self::assertNull( $event->actor_user_id(), 'An automatic check acts for no user.' );
		self::assertTrue( $event->context()->to_array()['automatic'] ?? false );
		self::assertSame( 0, $this->actions( 'send' ), 'Reconciliation never sends.' );
	}

	public function test_an_unconfirmed_send_settles_only_after_the_settle_window(): void {
		$campaign             = $this->provider_draft_campaign();
		$this->action_replies = array( array( 503, '' ) );
		self::assertSame( 409, $this->request( 'POST', "/{$campaign['id']}/send", array( 'expected_version' => 5, 'confirm_audience_reference' => 'abc123', 'idempotency_key' => 'bg-send' ) )->get_status() );
		self::assertSame( 'unknown', $this->campaign( $campaign['id'] )->state() );

		$this->sweep->sweep();
		$this->runner->run( 10 );

		// Mailchimp shows an unsent draft, but that is no proof yet that the send was never applied.
		self::assertSame( 'unknown', $this->campaign( $campaign['id'] )->state() );
		$watch = Reconcile_Watch::of( new Job_Repository(), $this->campaign( $campaign['id'] ) );
		self::assertSame( Reconcile_Watch::WATCHING, $watch->state );
		self::assertGreaterThan( $this->clock->now(), $watch->next_check_at );

		// Once the attempt is older than the settle window, the draft proves the send never happened.
		$this->clock->now += 900;
		$this->runner->run( 10 );

		self::assertSame( 'provider_draft', $this->campaign( $campaign['id'] )->state() );
		self::assertSame( 1, $this->actions( 'send' ), 'The unconfirmed send was never repeated.' );
	}

	public function test_an_unresolvable_reason_stops_automatic_checks_and_flags_the_campaign(): void {
		$campaign = $this->provider_draft_campaign();
		self::assertSame( 200, $this->request( 'POST', "/{$campaign['id']}/send", array( 'expected_version' => 5, 'confirm_audience_reference' => 'abc123', 'idempotency_key' => 'bg-send' ) )->get_status() );
		self::assertSame( 'sending', $this->campaign( $campaign['id'] )->state() );

		// Someone deletes the campaign in Mailchimp.
		$this->remote = array( 404, '{"title":"Resource Not Found"}' );
		$this->sweep->sweep();
		$this->runner->run( 10 );

		$watch = Reconcile_Watch::of( new Job_Repository(), $this->campaign( $campaign['id'] ) );
		self::assertSame( Reconcile_Watch::STOPPED, $watch->state );
		self::assertSame( 'missing', $watch->reason );
		self::assertSame( 'sending', $this->campaign( $campaign['id'] )->state(), 'Nothing is changed locally.' );

		$reads = $this->reads();
		self::assertSame( 0, $this->sweep->sweep(), 'A stopped watch is not restarted while the campaign is unchanged.' );
		$this->clock->now += DAY_IN_SECONDS;
		$this->runner->run( 10 );
		self::assertSame( $reads, $this->reads(), 'No further automatic checks.' );

		$remote = $this->request( 'GET', "/{$campaign['id']}/remote" );
		self::assertSame( 'stopped', $remote->get_data()['watch']['state'] );
		self::assertSame( 'missing', $remote->get_data()['watch']['reason'] );
		$valid = rest_validate_value_from_schema( $remote->get_data(), \CampaignBridge\REST\Campaign_Rest_Schema::remote_view_result(), 'response' );
		self::assertTrue( true === $valid, is_wp_error( $valid ) ? $valid->get_error_message() : '' );
		self::assertSame( 1, ( new \CampaignBridge\Admin\Controllers\Status_Controller() )->get_data()['health']['jobs']['stopped_watches'], 'Status counts the campaign needing operator action.' );
	}

	public function test_delayed_cron_runs_an_overdue_check_once(): void {
		$campaign = $this->provider_draft_campaign();
		self::assertSame( 200, $this->request( 'POST', "/{$campaign['id']}/send", array( 'expected_version' => 5, 'confirm_audience_reference' => 'abc123', 'idempotency_key' => 'bg-send' ) )->get_status() );
		$this->remote = array( 200, '{"type":"regular","status":"sending","emails_sent":1,"send_time":"' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '","recipients":{"list_id":"abc123","segment_opts":{}}}' );
		$this->sweep->sweep();
		$reads = $this->reads();

		// Cron stops for six hours, then runs twice in quick succession.
		$this->clock->now += 6 * HOUR_IN_SECONDS;
		$this->runner->run( 10 );
		$this->runner->run( 10 );

		self::assertSame( $reads + 1, $this->reads(), 'The overdue check runs once, not once per missed interval.' );
		self::assertSame( Reconcile_Watch::WATCHING, Reconcile_Watch::of( new Job_Repository(), $this->campaign( $campaign['id'] ) )->state );
	}

	public function test_a_held_campaign_is_checked_again_later_without_a_provider_call(): void {
		$campaign = $this->provider_draft_campaign();
		self::assertSame( 200, $this->request( 'POST', "/{$campaign['id']}/send", array( 'expected_version' => 5, 'confirm_audience_reference' => 'abc123', 'idempotency_key' => 'bg-send' ) )->get_status() );
		$this->sweep->sweep();
		( new \CampaignBridge\Repository\Lock_Repository() )->acquire( 'campaign:' . $campaign['id'], 'worker-other', 'send', gmdate( 'Y-m-d\TH:i:s\Z', time() + 300 ), gmdate( 'Y-m-d\TH:i:s\Z' ) );
		$reads = $this->reads();

		$report = $this->runner->run( 10 );

		self::assertSame( 1, $report['rescheduled'] );
		self::assertSame( $reads, $this->reads() );
		$job = ( new Job_Repository() )->find_active( Campaign_Reconcile_Job::dedupe_key( $campaign['id'] ) );
		self::assertSame( Job_State::QUEUED, $job?->state() );
		self::assertSame( 0, $job?->attempts(), 'Waiting for a lock uses no attempt.' );
	}

	private function reconciler( string $provider ): ?Campaign_Reconciler {
		if ( 'mailchimp' !== $provider ) {
			return null;
		}

		// Use one clock for campaign updates and job completion, even across a real second boundary.
		return new Campaign_Reconciler(
			new Campaign_Repository(),
			new Remote_Campaign_Reference_Repository(),
			new Delivery_Attempt_Repository(),
			new Audit_Event_Repository(),
			new Database_Transaction(),
			new Random_Id_Generator(),
			$this->clock,
			new Mailchimp_Draft_Gateway(),
			( new Mailchimp_Provider() )->capabilities(),
			Lock_Factory::manager()
		);
	}

	private function reads(): int {
		return count( array_filter( $this->requests, static fn ( array $request ): bool => 'GET' === $request['method'] && str_contains( $request['url'], '/campaigns/mc0042?fields=' ) ) );
	}

	private function actions( string $action ): int {
		return count( array_filter( $this->requests, static fn ( array $request ): bool => str_ends_with( $request['url'], '/actions/' . $action ) ) );
	}

	private function campaign( string $id ): \CampaignBridge\Domain\Campaign\Campaign {
		$campaign = ( new Campaign_Repository() )->get( $id );
		self::assertNotNull( $campaign );

		return $campaign;
	}

	/** @return array<string, mixed> */
	private function provider_draft_campaign(): array {
		$template             = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Background template',
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
		self::assertSame(
			201,
			$this->request(
				'POST',
				"/{$campaign['id']}/provider-draft",
				array(
					'expected_version' => 4,
					'idempotency_key'  => 'bg-draft',
				)
			)->get_status()
		);

		return $campaign;
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

		$response = rest_get_server()->dispatch( $request );
		// REST writes use real time; background work starts at that instant or later.
		$this->clock->now = max( $this->clock->now, time() );

		return $response;
	}
}
