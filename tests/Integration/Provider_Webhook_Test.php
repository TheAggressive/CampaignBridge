<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,CampaignBridge.Standard.Sniffs.Database
/**
 * Mailchimp webhooks: registration and prompt, read-before-change reconciliation.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Core\Encryption;
use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Domain\Provider\Discovered_Merge_Field;
use CampaignBridge\Domain\Provider\Discovery_Batch;
use CampaignBridge\Domain\Provider\Discovery_Kind;
use CampaignBridge\Domain\Provider\Discovery_Result;
use CampaignBridge\Domain\Webhook\Webhook_Subscription;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Providers\Mailchimp_Discovery;
use CampaignBridge\Repository\Audit_Event_Repository;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Job_Repository;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Repository\Provider_Discovery_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\Repository\Webhook_Subscription_Repository;
use CampaignBridge\REST\Routes;
use CampaignBridge\Services\Job\Job_Factory;
use CampaignBridge\Services\Webhook\Webhook_Factory;
use CampaignBridge\Tests\Helpers\Test_Case;
use CampaignBridge\Workflow\Campaign\Campaign_Reconcile_Job;
use CampaignBridge\Workflow\Campaign\Reconcile_Watch;
use CampaignBridge\Workflow\Campaign\System_Clock;
use CampaignBridge\Workflow\Job\Job_Queue;
use WP_REST_Request;
use WP_REST_Response;

/** Proves a signed webhook is only a hint: it brings reconciliation forward and never changes a campaign itself. */
final class Provider_Webhook_Test extends Test_Case {
	public const ROUTE      = '/campaignbridge/v1/webhooks/mailchimp';
	public const SECRET     = 'whsec-integration-0123456789abcdefghijklm';
	private const CAMPAIGNS = '/campaignbridge/v1/campaigns';

	private int $admin_id;

	/** @var array<int, int> */
	private array $template_ids = array();

	/** @var array<int, array{method: string, url: string, body: string}> Mailchimp requests. */
	private array $requests = array();

	/** Status and body Mailchimp returns when the campaign is read. */
	private array $remote = array( 200, '{"type":"regular","status":"save","emails_sent":0,"recipients":{"list_id":"abc123","segment_opts":{}}}' );

	/** @var array<int, array{0: int, 1: string}> Scripted replies to webhook API calls, in order. */
	public array $webhook_replies = array();

	/** @var callable|null */
	private $filter = null;

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
				'body'   => is_string( $args['body'] ?? null ) ? $args['body'] : '',
			);
			$reply            = match ( true ) {
				str_contains( $url, '/webhooks' ) => array_shift( $this->webhook_replies ) ?? array( 500, '' ),
				str_contains( $url, '/lists?' ) => array( 200, '{"lists":[{"id":"abc123","name":"Main","stats":{"member_count":3},"campaign_defaults":{"from_name":"Shop","from_email":"news@example.com"}},{"id":"def456","name":"VIP","stats":{"member_count":1},"campaign_defaults":{"from_name":"Shop","from_email":"news@example.com"}}],"total_items":2}' ),
				str_contains( $url, '/actions/' ) => array( 204, '' ),
				str_contains( $url, '/campaigns/mc0042?fields=' ) => $this->remote,
				'POST' === $method => array( 200, '{"id":"mc0042","status":"save"}' ),
				default => array( 200, '{}' ),
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
		foreach ( array_keys( ( new Webhook_Subscription_Repository() )->all( 'mailchimp' ) ) as $audience ) {
			( new Webhook_Subscription_Repository() )->remove( 'mailchimp', (string) $audience );
		}
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%discovery\_%' OR option_name LIKE '%rate\_limit%'" );
		$this->truncate();
		$wpdb->query( 'COMMIT' );
		wp_cache_flush();
		parent::tearDown();
	}

	public function test_mailchimp_url_check_is_answered_without_credentials(): void {
		wp_set_current_user( 0 );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', self::ROUTE ) );

		self::assertSame( 200, $response->get_status() );
		self::assertNull( $response->get_data() );
	}

	public function test_a_signed_sent_event_settles_a_scheduled_campaign_only_after_reading_mailchimp(): void {
		$campaign = $this->scheduled_campaign();
		self::store_secret( self::SECRET );
		$reads = $this->reads();

		wp_set_current_user( 0 );
		self::assertSame( 200, self::deliver( self::campaign_event( 'mc0042' ) )->get_status() );

		self::assertSame( 'scheduled', $this->campaign( $campaign )->state(), 'The webhook body changes nothing by itself.' );
		self::assertSame( $reads, $this->reads(), 'Mailchimp is read by the job, not the request.' );
		self::assertNotNull( ( new Job_Repository() )->find_active( Campaign_Reconcile_Job::HINT_TYPE . ':campaign:' . $campaign ) );

		// Someone sent the campaign early from the Mailchimp app, an hour before its send time.
		$this->remote = array( 200, '{"type":"regular","status":"sent","emails_sent":3,"send_time":"' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '","recipients":{"list_id":"abc123","segment_opts":{}}}' );
		$report       = Job_Factory::runner()->run( 10 );

		self::assertSame( 1, $report['succeeded'] );
		self::assertSame( 'sent', $this->campaign( $campaign )->state() );
		self::assertSame( $reads + 1, $this->reads() );
		$event = ( new Audit_Event_Repository() )->for_target( 'campaign', $campaign, 1 )[0];
		self::assertSame( 'campaign_reconcile', $event->action() );
		self::assertNull( $event->actor_user_id(), 'A hinted check acts for no user.' );
		self::assertSame( 0, $this->actions( 'send' ), 'Reconciliation never sends.' );
	}

	public function test_a_hint_mailchimp_does_not_confirm_changes_nothing_and_keeps_the_watch(): void {
		$campaign = $this->scheduled_campaign();
		self::store_secret( self::SECRET );
		$this->remote = array( 200, '{"type":"regular","status":"schedule","emails_sent":0,"send_time":"' . (string) $this->campaign( $campaign )->scheduled_for() . '","recipients":{"list_id":"abc123","segment_opts":{}}}' );
		$this->sweep();

		wp_set_current_user( 0 );
		self::assertSame( 200, self::deliver( self::campaign_event( 'mc0042' ) )->get_status() );
		Job_Factory::runner()->run( 10 );

		self::assertSame( 'scheduled', $this->campaign( $campaign )->state() );
		self::assertSame( Reconcile_Watch::WATCHING, Reconcile_Watch::of( new Job_Repository(), $this->campaign( $campaign ) )->state, 'The regular watch is untouched.' );
		self::assertNull( ( new Job_Repository() )->find_active( Campaign_Reconcile_Job::HINT_TYPE . ':campaign:' . $campaign ), 'A hint runs once.' );
	}

	public function test_duplicate_and_repeated_deliveries_reconcile_once(): void {
		$campaign = $this->scheduled_campaign();
		self::store_secret( self::SECRET );
		$body = self::campaign_event( 'mc0042' );
		$now  = time();

		wp_set_current_user( 0 );
		$first  = self::deliver( $body, self::SECRET, $now );
		$replay = self::deliver( $body, self::SECRET, $now );
		$retry  = self::deliver( $body, self::SECRET, $now + 1 );

		self::assertSame( array( 200, 200, 200 ), array( $first->get_status(), $replay->get_status(), $retry->get_status() ) );
		self::assertSame( $first->get_data(), $replay->get_data(), 'A duplicate is answered exactly like the original.' );
		self::assertSame( 1, $this->hint_jobs( $campaign ), 'Every delivery for one campaign collapses into one queued check.' );

		$reads = $this->reads();
		Job_Factory::runner()->run( 10 );
		self::assertSame( $reads + 1, $this->reads() );
	}

	public function test_deliveries_about_other_campaigns_or_subscribers_are_ignored(): void {
		$campaign = $this->scheduled_campaign();
		self::store_secret( self::SECRET );

		wp_set_current_user( 0 );
		self::assertSame( 200, self::deliver( self::campaign_event( 'notours99' ) )->get_status() );
		self::assertSame( 200, self::deliver( 'type=unsubscribe&fired_at=2026-10-17+18%3A00%3A00&data%5Bemail%5D=person%40example.com&data%5Blist_id%5D=abc123' )->get_status() );

		self::assertSame( 0, $this->hint_jobs( $campaign ) );
		global $wpdb;
		self::assertSame( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema_Manager::table( 'jobs' ) . " WHERE job_type = '" . Campaign_Reconcile_Job::HINT_TYPE . "'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted test table and constant.
	}

	public function test_enabling_creates_one_signed_webhook_per_audience_and_stores_only_encrypted_secrets(): void {
		$this->webhook_replies = array(
			array( 200, '{"webhooks":[]}' ),
			array( 200, self::created( 'wh-main', 'whsec-main-0123456789abcdefghijklmnopqr' ) ),
			array( 200, '{"webhooks":[]}' ),
			array( 200, self::created( 'wh-vip', 'whsec-vip-0123456789abcdefghijklmnopqrs' ) ),
		);
		$registrar             = Webhook_Factory::registrar( 'mailchimp' );
		self::assertNotNull( $registrar );

		$result = $registrar->enable();

		self::assertTrue( $result->is_success() );
		self::assertSame( 2, $result->changed() );
		$stored = $registrar->subscriptions();
		self::assertSame( array( 'abc123', 'def456' ), array_keys( $stored ) );
		self::assertSame( 'wh-main', $stored['abc123']->remote_id() );
		self::assertTrue( Encryption::is_encrypted_value( $stored['abc123']->signing_secret() ) );
		self::assertSame( 'whsec-main-0123456789abcdefghijklmnopqr', Encryption::decrypt( $stored['abc123']->signing_secret() ) );
		global $wpdb;
		self::assertSame( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE '%whsec-%'" ), 'No plaintext secret is stored anywhere.' );

		$creates = array_values( array_filter( $this->requests, static fn ( array $request ): bool => 'POST' === $request['method'] && str_ends_with( $request['url'], '/webhooks' ) ) );
		self::assertCount( 2, $creates );
		self::assertSame( Webhook_Factory::url( 'mailchimp' ), json_decode( $creates[0]['body'], true )['url'] );

		$before = count( $this->requests );
		self::assertSame( 0, $registrar->enable()->changed(), 'Audiences that already have a webhook are left alone.' );
		self::assertSame( $before + 1, count( $this->requests ), 'Only the audience list is read again.' );
	}

	public function test_a_webhook_left_by_an_unconfirmed_create_is_removed_before_creating_again(): void {
		$url                   = addcslashes( Webhook_Factory::url( 'mailchimp' ), '/' );
		$this->webhook_replies = array(
			array( 200, '{"webhooks":[{"id":"wh-orphan","url":"' . $url . '"},{"id":"wh-other","url":"https:\/\/elsewhere.example\/hook"}]}' ),
			array( 204, '' ),
			array( 200, self::created( 'wh-main', 'whsec-main-0123456789abcdefghijklmnopqr' ) ),
			array( 503, '' ),
		);

		$result = Webhook_Factory::registrar( 'mailchimp' )?->enable();

		self::assertNotNull( $result );
		self::assertFalse( $result->is_success(), 'One audience could not be listed.' );
		self::assertSame( 1, $result->changed() );
		self::assertSame( 1, $result->failed_count() );
		$deletes = array_values( array_filter( $this->requests, static fn ( array $request ): bool => 'DELETE' === $request['method'] ) );
		self::assertCount( 1, $deletes );
		self::assertStringEndsWith( '/lists/abc123/webhooks/wh-orphan', $deletes[0]['url'], 'Only the webhook pointing at this site is removed.' );
		self::assertSame( array( 'abc123' ), array_keys( ( new Webhook_Subscription_Repository() )->all( 'mailchimp' ) ), 'The finished audience is kept; the action can be repeated.' );
	}

	public function test_rotating_replaces_every_secret_and_disabling_removes_every_webhook(): void {
		self::store_secret( 'whsec-old-0123456789abcdefghijklmnopqrs' );
		$this->webhook_replies = array(
			array( 204, '' ),
			array( 200, '{"webhooks":[]}' ),
			array( 200, self::created( 'wh-new', 'whsec-new-0123456789abcdefghijklmnopqrs' ) ),
			array( 200, '{"webhooks":[]}' ),
			array( 200, self::created( 'wh-vip', 'whsec-vip-0123456789abcdefghijklmnopqrs' ) ),
		);
		$registrar             = Webhook_Factory::registrar( 'mailchimp' );
		self::assertNotNull( $registrar );

		self::assertTrue( $registrar->rotate()->is_success() );
		self::assertSame( 'whsec-new-0123456789abcdefghijklmnopqrs', Encryption::decrypt( $registrar->subscriptions()['abc123']->signing_secret() ) );
		wp_set_current_user( 0 );
		self::assertSame( 401, self::deliver( self::campaign_event( 'mc0042' ), 'whsec-old-0123456789abcdefghijklmnopqrs' )->get_status(), 'The old secret no longer authenticates.' );

		$this->webhook_replies = array( array( 204, '' ), array( 404, '' ) );
		self::assertTrue( $registrar->disable()->is_success(), 'A webhook already gone counts as removed.' );
		self::assertSame( array(), $registrar->subscriptions() );
		self::assertSame( 401, self::deliver( self::campaign_event( 'mc0042' ), 'whsec-new-0123456789abcdefghijklmnopqrs' )->get_status() );
	}

	public function test_disconnecting_removes_the_webhooks_while_the_key_still_works(): void {
		self::store_secret( self::SECRET );
		$this->webhook_replies = array( array( 204, '' ) );

		self::assertTrue( \CampaignBridge\Admin\Controllers\Provider_Disconnect_Handler::handle( 'mailchimp' ) );

		self::assertSame( array(), ( new Webhook_Subscription_Repository() )->all( 'mailchimp' ) );
		self::assertSame( 'DELETE', end( $this->requests )['method'] );
		self::assertNull( ( new Provider_Connection_Repository() )->get( 'mailchimp' ) );
	}

	/** Store one webhook for the main audience with a known secret. */
	public static function store_secret( string $secret, string $audience = 'abc123' ): void {
		self::assertTrue( ( new Webhook_Subscription_Repository() )->save( Webhook_Subscription::create( 'mailchimp', $audience, 'wh-' . $audience, Encryption::encrypt( $secret ), gmdate( 'Y-m-d\TH:i:s\Z' ) ) ) );
	}

	/** A form-encoded campaign event as Mailchimp sends it. */
	public static function campaign_event( string $remote_id ): string {
		return http_build_query(
			array(
				'type'     => 'campaign',
				'fired_at' => gmdate( 'Y-m-d H:i:s' ),
				'data'     => array(
					'id'      => $remote_id,
					'subject' => 'Spring sale',
					'status'  => 'sent',
					'reason'  => '',
					'list_id' => 'abc123',
				),
			)
		);
	}

	/** POST one signed delivery to the webhook route. */
	public static function deliver( string $body, string $secret = self::SECRET, ?int $timestamp = null, ?string $signature = null ): WP_REST_Response {
		$timestamp ??= time();
		$request     = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_header( 'Content-Type', 'application/x-www-form-urlencoded' );
		$request->set_header( 'X-Mailchimp-Signature', $signature ?? 't=' . $timestamp . ',v1=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret ) );
		$request->set_body( $body );

		return rest_get_server()->dispatch( $request );
	}

	private static function created( string $id, string $secret ): string {
		return (string) wp_json_encode(
			array(
				'id'              => $id,
				'url'             => Webhook_Factory::url( 'mailchimp' ),
				'list_id'         => 'abc123',
				'signing_enabled' => true,
				'signing_secret'  => $secret,
			)
		);
	}

	private function sweep(): void {
		$jobs = new Job_Repository();
		$clock = new System_Clock();
		( new \CampaignBridge\Workflow\Campaign\Reconciliation_Sweep( new Campaign_Repository(), $jobs, new Job_Queue( $jobs, $clock ), $clock ) )->sweep();
	}

	private function hint_jobs( string $campaign ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema_Manager::table( 'jobs' ) . ' WHERE job_type = %s AND target_id = %s', Campaign_Reconcile_Job::HINT_TYPE, $campaign ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Allowlisted test table.
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

	/** A campaign scheduled in Mailchimp an hour from now; returns its ID. */
	private function scheduled_campaign(): string {
		$template             = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Webhook template',
				'post_content' => '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:paragraph --><p>Hello {{cb:subscriber.first_name}}</p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->',
				'post_author'  => $this->admin_id,
			)
		);
		$this->template_ids[] = $template;
		update_post_meta( $template, 'campaignbridge_subject', 'Spring sale' );
		update_post_meta( $template, 'campaignbridge_sender_name', 'Example Shop' );
		update_post_meta( $template, 'campaignbridge_sender_email', 'news@example.com' );

		$id = $this->request( 'POST', '', array( 'template_id' => $template, 'provider' => 'mailchimp', 'audience_reference' => 'abc123' ) )->get_data()['campaign']['id']; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Compact request.
		self::assertSame( 200, $this->request( 'POST', "/{$id}/snapshot", array( 'expected_version' => 1 ) )->get_status() );
		self::assertSame( 200, $this->request( 'POST', "/{$id}/submit", array( 'expected_version' => 2 ) )->get_status() );
		self::assertSame( 200, $this->request( 'POST', "/{$id}/approve", array( 'expected_version' => 3 ) )->get_status() );
		self::assertSame( 201, $this->request( 'POST', "/{$id}/provider-draft", array( 'expected_version' => 4, 'idempotency_key' => 'wh-draft' ) )->get_status() ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Compact request.
		$send_at = gmdate( 'Y-m-d\TH:i:s\Z', (int) ( ceil( ( time() + 3600 ) / 900 ) * 900 ) );
		self::assertSame( 200, $this->request( 'POST', "/{$id}/schedule", array( 'expected_version' => 5, 'scheduled_for' => $send_at, 'confirm_audience_reference' => 'abc123', 'idempotency_key' => 'wh-schedule' ) )->get_status() ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Compact request.
		self::assertSame( 'scheduled', $this->campaign( $id )->state() );

		return $id;
	}

	private function truncate(): void {
		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted isolated test tables.
		}
	}

	/** @param array<string, mixed> $params Request parameters. */
	private function request( string $method, string $suffix, array $params = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, self::CAMPAIGNS . $suffix );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}
}
