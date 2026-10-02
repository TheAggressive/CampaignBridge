<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,CampaignBridge.Standard.Sniffs.Database
/**
 * Provider draft REST route integration tests.
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
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Repository\Provider_Discovery_Repository;
use CampaignBridge\Repository\Remote_Campaign_Reference_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\REST\Campaign_Rest_Schema;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/** Proves the REST route drives the real handoff against a faked Mailchimp. */
final class Campaign_Provider_Draft_Route_Test extends Test_Case {
	private const COLLECTION = '/campaignbridge/v1/campaigns';

	private int $admin_id;

	/** @var array<int, int> */
	private array $template_ids = array();

	/** @var array<int, array{method: string, url: string, body: string}> Mailchimp requests. */
	private array $requests = array();

	/** @var array<int, array{0: int, 1: string}> Scripted replies; empty means success. */
	private array $replies = array();

	/** @var callable|null */
	private $filter = null;

	private static function api_key(): string {
		return str_repeat( 'c0ffee', 5 ) . 'c0' . '-us20';
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
			self::assertSame( 'Bearer ' . self::api_key(), $args['headers']['Authorization'] ?? null );
			$reply = array_shift( $this->replies ) ?? array( 200, 'POST' === $method ? '{"id":"mc0042","status":"save"}' : '{}' );

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
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%discovery\_%' OR option_name LIKE '%rate\_limit%'" );
		$this->truncate();
		// Workflow transactions commit, so cleanup of committed fixtures must commit too.
		$wpdb->query( 'COMMIT' );
		wp_cache_flush();
		parent::tearDown();
	}

	public function test_approved_campaign_becomes_one_mailchimp_draft_and_replays(): void {
		$campaign = $this->approved_campaign();

		$created = $this->draft( $campaign['id'], 4, 'rest-draft-1' );
		self::assertSame( 201, $created->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::provider_draft_result(), $created );
		$data = $created->get_data();
		self::assertSame( 'provider_draft', $data['campaign']['state'] );
		self::assertSame( 5, $data['campaign']['version'] );
		self::assertSame( array( 'mailchimp', 'mc0042', 'draft' ), array( $data['remote']['provider'], $data['remote']['remote_id'], $data['remote']['observed_state'] ) );
		self::assertSame( 'succeeded', $data['attempt']['status'] );
		self::assertFalse( $data['idempotent_replay'] );
		self::assertSame( 'no-store', $created->get_headers()['Cache-Control'] );
		self::assertStringNotContainsString( self::api_key(), (string) wp_json_encode( $data ) );

		self::assertSame(
			array( 'POST https://us20.api.mailchimp.com/3.0/campaigns', 'PUT https://us20.api.mailchimp.com/3.0/campaigns/mc0042/content' ),
			array_map( static fn ( array $request ): string => $request['method'] . ' ' . $request['url'], $this->requests )
		);
		$create = json_decode( $this->requests[0]['body'], true );
		self::assertSame( 'abc123', $create['recipients']['list_id'] );
		self::assertSame( 'Spring sale for *|FNAME|*', $create['settings']['subject_line'] );
		self::assertSame( array( 'Example Shop', 'news@example.com' ), array( $create['settings']['from_name'], $create['settings']['reply_to'] ) );
		self::assertSame( 'CampaignBridge ' . $data['attempt']['id'], $create['settings']['title'] );
		$upload = json_decode( $this->requests[1]['body'], true );
		self::assertStringContainsString( 'Hello *|FNAME|*', $upload['html'] );
		self::assertStringNotContainsString( '{{cb:', $upload['html'] . $upload['plain_text'] );

		$replay = $this->draft( $campaign['id'], 5, 'rest-draft-2' );
		self::assertSame( 200, $replay->get_status() );
		self::assertTrue( $replay->get_data()['idempotent_replay'] );
		self::assertSame( 'mc0042', $replay->get_data()['remote']['remote_id'] );
		self::assertNull( $replay->get_data()['attempt'] );
		self::assertCount( 2, $this->requests, 'A replay sends nothing to Mailchimp.' );
		self::assertSame( $campaign['id'], ( new Remote_Campaign_Reference_Repository() )->find_remote( 'mailchimp', 'mc0042' )?->campaign_id() );
	}

	public function test_unconfirmed_create_returns_a_conflict_and_is_not_retried(): void {
		$campaign      = $this->approved_campaign();
		$this->replies = array( array( 503, '{"detail":"private upstream detail"}' ) );

		$unknown = $this->draft( $campaign['id'], 4, 'rest-draft-1' );
		self::assertSame( 409, $unknown->get_status() );
		self::assertSame( 'campaignbridge_campaign_reconciliation_required', $unknown->get_data()['code'] );
		$this->assert_schema( Campaign_Rest_Schema::error(), $unknown );
		self::assertSame( 'unknown', $unknown->get_data()['data']['attempt']['status'] );
		self::assertSame( 'provider_error', $unknown->get_data()['data']['provider_error']['category'] );
		self::assertStringNotContainsString( 'private upstream detail', (string) wp_json_encode( $unknown->get_data() ) );
		self::assertSame( 'approved', ( new Campaign_Repository() )->get( $campaign['id'] )?->state() );

		$blocked = $this->draft( $campaign['id'], 4, 'rest-draft-2' );
		self::assertSame( 409, $blocked->get_status() );
		self::assertCount( 1, $this->requests, 'Exactly one create was sent; an unknown outcome is never retried.' );
		self::assertCount( 1, ( new Delivery_Attempt_Repository() )->for_campaign( $campaign['id'] ) );
	}

	public function test_provider_refusal_and_unsendable_content_map_to_stable_errors(): void {
		$campaign      = $this->approved_campaign();
		$this->replies = array( array( 400, '{"detail":"private validation detail"}' ) );

		$refused = $this->draft( $campaign['id'], 4, 'rest-draft-1' );
		self::assertSame( 502, $refused->get_status() );
		self::assertSame( 'campaignbridge_campaign_provider_failed', $refused->get_data()['code'] );
		self::assertSame( array( 'failed', 'not_retryable' ), array( $refused->get_data()['data']['attempt']['status'], $refused->get_data()['data']['attempt']['retryability'] ) );
		self::assertStringNotContainsString( 'private validation detail', (string) wp_json_encode( $refused->get_data() ) );

		$literal  = $this->approved_campaign( 'Hello *|FNAME|*' );
		$requests = count( $this->requests );
		$invalid  = $this->draft( $literal['id'], 4, 'rest-draft-1' );
		self::assertSame( 400, $invalid->get_status() );
		self::assertSame( 'campaignbridge_campaign_validation_failed', $invalid->get_data()['code'] );
		self::assertCount( $requests, $this->requests, 'Unsendable content never reaches Mailchimp.' );

		$untargeted = $this->approved_campaign( 'Hello', array() );
		self::assertSame( 'campaignbridge_campaign_invalid_input', $this->draft( $untargeted['id'], 4, 'rest-draft-1' )->get_data()['code'] );
	}

	public function test_route_requires_approval_authority_version_and_key(): void {
		$campaign = $this->approved_campaign();
		self::assertSame( 'rest_missing_callback_param', $this->request( 'POST', "/{$campaign['id']}/provider-draft", array( 'expected_version' => 4 ) )->get_data()['code'] );
		self::assertSame( 'rest_missing_callback_param', $this->request( 'POST', "/{$campaign['id']}/provider-draft", array( 'idempotency_key' => 'k' ) )->get_data()['code'] );
		self::assertSame( 'campaignbridge_campaign_conflict', $this->draft( $campaign['id'], 3, 'rest-draft-1' )->get_data()['code'] );

		$author = $this->create_test_user( array( 'role' => 'subscriber' ) );
		get_userdata( $author )->add_cap( Capabilities::CREATE_CAMPAIGNS );
		wp_set_current_user( $author );
		$denied = $this->draft( $campaign['id'], 4, 'rest-draft-1' );
		self::assertSame( 403, $denied->get_status() );
		self::assertSame( 'rest_forbidden', $denied->get_data()['code'] );
		self::assertSame( array(), $this->requests );
		self::assertSame( 'approved', ( new Campaign_Repository() )->get( $campaign['id'] )?->state() );
	}

	/**
	 * @param array<string, string>|null $targeting Provider and audience, or an empty array for none.
	 * @return array<string, mixed>
	 */
	private function approved_campaign( string $paragraph = 'Hello {{cb:subscriber.first_name}}', ?array $targeting = null ): array {
		$template = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Draft route template',
				'post_content' => '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:paragraph --><p>' . $paragraph . '</p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->',
				'post_author'  => $this->admin_id,
			)
		);
		$this->template_ids[] = $template;
		update_post_meta( $template, 'campaignbridge_subject', 'Spring sale for {{cb:subscriber.first_name}}' );
		update_post_meta( $template, 'campaignbridge_sender_name', 'Example Shop' );
		update_post_meta( $template, 'campaignbridge_sender_email', 'news@example.com' );

		$campaign = $this->request(
			'POST',
			'',
			array_merge(
				array( 'template_id' => $template ),
				$targeting ?? array(
					'provider'           => 'mailchimp',
					'audience_reference' => 'abc123',
				)
			)
		)->get_data()['campaign'];
		self::assertSame( 200, $this->request( 'POST', "/{$campaign['id']}/snapshot", array( 'expected_version' => 1 ) )->get_status() );
		self::assertSame( 200, $this->request( 'POST', "/{$campaign['id']}/submit", array( 'expected_version' => 2 ) )->get_status() );
		$approved = $this->request( 'POST', "/{$campaign['id']}/approve", array( 'expected_version' => 3 ) );
		self::assertSame( 'approved', $approved->get_data()['campaign']['state'] );

		return $approved->get_data()['campaign'];
	}

	private function draft( string $campaign_id, int $version, string $key ): WP_REST_Response {
		return $this->request(
			'POST',
			"/{$campaign_id}/provider-draft",
			array(
				'expected_version' => $version,
				'idempotency_key'  => $key,
			)
		);
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
