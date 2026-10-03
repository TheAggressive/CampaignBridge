<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,CampaignBridge.Standard.Sniffs.Database
/**
 * Test-send REST route integration tests.
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
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Providers\Mailchimp_Discovery;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Delivery_Attempt_Repository;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Repository\Provider_Discovery_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\REST\Campaign_Rest_Schema;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/** Proves the REST route drives the real test delivery against a faked Mailchimp. */
final class Campaign_Test_Send_Route_Test extends Test_Case {
	private const COLLECTION = '/campaignbridge/v1/campaigns';

	private const TEST_ACTION = 'POST https://us20.api.mailchimp.com/3.0/campaigns/mc0042/actions/test';

	private int $admin_id;

	/** @var array<int, int> */
	private array $template_ids = array();

	/** @var array<int, array{method: string, url: string, body: string}> Mailchimp requests. */
	private array $requests = array();

	/** @var array<int, array{0: int, 1: string}> Scripted replies for test actions; empty means 204. */
	private array $test_replies = array();

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
				str_ends_with( $url, '/actions/test' ) => array_shift( $this->test_replies ) ?? array( 204, '' ),
				'POST' === $method                      => array( 200, '{"id":"mc0042","status":"save"}' ),
				default                                 => array( 200, '{}' ),
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
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%discovery\_%' OR option_name LIKE '%rate\_limit%'" );
		$this->truncate();
		// Workflow transactions commit, so cleanup of committed fixtures must commit too.
		$wpdb->query( 'COMMIT' );
		wp_cache_flush();
		parent::tearDown();
	}

	public function test_provider_draft_is_tested_through_mailchimp_and_replays(): void {
		$campaign = $this->provider_draft_campaign();

		$sent = $this->test_send( $campaign['id'], array( 'QA@example.com' ), 'rest-test-1' );
		self::assertSame( 202, $sent->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::test_send_result(), $sent );
		$data = $sent->get_data();
		self::assertSame( array( 'provider_draft', 5 ), array( $data['campaign']['state'], $data['campaign']['version'] ), 'A test never changes the campaign.' );
		self::assertSame( 'mc0042', $data['remote']['remote_id'] );
		self::assertSame( 'succeeded', $data['attempt']['status'] );
		self::assertSame( array( 'html', 1 ), array( $data['test']['format'], $data['test']['recipient_count'] ) );
		self::assertSame( $campaign['active_snapshot_id'], $data['test']['snapshot_id'] );
		self::assertFalse( $data['idempotent_replay'] );
		self::assertSame( 'no-store', $sent->get_headers()['Cache-Control'] );
		$encoded = (string) wp_json_encode( $data );
		self::assertStringNotContainsString( self::api_key(), $encoded );
		self::assertStringNotContainsStringIgnoringCase( 'qa@example.com', $encoded, 'Recipients are never echoed back.' );

		self::assertSame( self::TEST_ACTION, $this->last_request() );
		self::assertSame(
			array(
				'test_emails' => array( 'qa@example.com' ),
				'send_type'   => 'html',
			),
			json_decode( $this->requests[ count( $this->requests ) - 1 ]['body'], true )
		);

		$requests = count( $this->requests );
		$replay   = $this->test_send( $campaign['id'], array( 'qa@example.com' ), 'rest-test-1' );
		self::assertSame( 200, $replay->get_status() );
		$this->assert_schema( Campaign_Rest_Schema::test_send_result(), $replay );
		self::assertTrue( $replay->get_data()['idempotent_replay'] );
		self::assertNull( $replay->get_data()['test'] );
		self::assertSame( $data['attempt']['id'], $replay->get_data()['attempt']['id'] );
		self::assertCount( $requests, $this->requests, 'A replay sends nothing to Mailchimp.' );

		$text = $this->test_send( $campaign['id'], array( 'qa@example.com' ), 'rest-test-2', 'text' );
		self::assertSame( 202, $text->get_status() );
		self::assertSame( 'plaintext', json_decode( $this->requests[ count( $this->requests ) - 1 ]['body'], true )['send_type'] );
		self::assertSame( 'provider_draft', ( new Campaign_Repository() )->get( $campaign['id'] )?->state() );
	}

	public function test_unconfirmed_test_returns_a_conflict_and_is_not_retried(): void {
		$campaign           = $this->provider_draft_campaign();
		$requests           = count( $this->requests );
		$this->test_replies = array( array( 503, '{"detail":"private upstream detail"}' ) );

		$unknown = $this->test_send( $campaign['id'], array( 'qa@example.com' ), 'rest-test-1' );
		self::assertSame( 409, $unknown->get_status() );
		self::assertSame( 'campaignbridge_campaign_reconciliation_required', $unknown->get_data()['code'] );
		$this->assert_schema( Campaign_Rest_Schema::error(), $unknown );
		self::assertSame( 'unknown', $unknown->get_data()['data']['attempt']['status'] );
		self::assertSame( 'provider_error', $unknown->get_data()['data']['provider_error']['category'] );
		self::assertStringNotContainsString( 'private upstream detail', (string) wp_json_encode( $unknown->get_data() ) );
		self::assertCount( $requests + 1, $this->requests, 'Exactly one test action was sent; a 5xx is never retried.' );

		$same_key = $this->test_send( $campaign['id'], array( 'qa@example.com' ), 'rest-test-1' );
		self::assertSame( 409, $same_key->get_status() );
		self::assertCount( $requests + 1, $this->requests );
		self::assertSame( 'provider_draft', ( new Campaign_Repository() )->get( $campaign['id'] )?->state() );
	}

	public function test_provider_refusal_maps_to_a_stable_error(): void {
		$campaign           = $this->provider_draft_campaign();
		$this->test_replies = array( array( 400, '{"detail":"private validation detail"}' ) );

		$refused = $this->test_send( $campaign['id'], array( 'qa@example.com' ), 'rest-test-1' );
		self::assertSame( 502, $refused->get_status() );
		self::assertSame( 'campaignbridge_campaign_provider_failed', $refused->get_data()['code'] );
		$this->assert_schema( Campaign_Rest_Schema::error(), $refused );
		self::assertSame( array( 'failed', 'not_retryable' ), array( $refused->get_data()['data']['attempt']['status'], $refused->get_data()['data']['attempt']['retryability'] ) );
		self::assertSame( 'mailchimp_request_rejected', $refused->get_data()['data']['provider_error']['code'] );
		self::assertStringNotContainsString( 'private validation detail', (string) wp_json_encode( $refused->get_data() ) );
	}

	public function test_invalid_and_oversized_requests_fail_before_any_provider_call(): void {
		$campaign = $this->provider_draft_campaign();
		$requests = count( $this->requests );
		$six      = array_map( static fn ( int $i ): string => "qa{$i}@example.com", range( 1, 6 ) );

		foreach ( array( array( 'recipients' => $six ), array( 'recipients' => array( 'not-an-address' ) ), array( 'recipients' => array() ), array( 'format' => 'amp' ) ) as $override ) {
			$params   = array_merge(
				array(
					'recipients'      => array( 'qa@example.com' ),
					'idempotency_key' => 'rest-test-1',
				),
				$override
			);
			$response = $this->request( 'POST', "/{$campaign['id']}/test-send", $params );
			self::assertSame( 400, $response->get_status(), (string) wp_json_encode( $override ) );
			self::assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		}
		self::assertSame( 'rest_missing_callback_param', $this->request( 'POST', "/{$campaign['id']}/test-send", array( 'idempotency_key' => 'k' ) )->get_data()['code'] );
		self::assertSame( 'rest_missing_callback_param', $this->request( 'POST', "/{$campaign['id']}/test-send", array( 'recipients' => array( 'qa@example.com' ) ) )->get_data()['code'] );

		self::assertCount( $requests, $this->requests, 'Invalid requests never reach Mailchimp.' );
		self::assertSame( array(), array_filter( ( new Delivery_Attempt_Repository() )->for_campaign( $campaign['id'] ), static fn ( $attempt ): bool => 'test_send' === $attempt->operation() ) );
	}

	public function test_a_campaign_without_a_provider_draft_cannot_be_tested(): void {
		$approved = $this->approved_campaign();

		$response = $this->test_send( $approved['id'], array( 'qa@example.com' ), 'rest-test-1' );
		self::assertSame( 409, $response->get_status() );
		self::assertSame( 'campaignbridge_campaign_invalid_state', $response->get_data()['code'] );
		self::assertSame( array(), $this->requests );
	}

	/** @return array<string, mixed> */
	private function approved_campaign(): array {
		$template             = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Test route template',
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
		$approved = $this->request( 'POST', "/{$campaign['id']}/approve", array( 'expected_version' => 3 ) );
		self::assertSame( 'approved', $approved->get_data()['campaign']['state'] );

		return $approved->get_data()['campaign'];
	}

	/** @return array<string, mixed> */
	private function provider_draft_campaign(): array {
		$approved = $this->approved_campaign();
		$draft    = $this->request(
			'POST',
			"/{$approved['id']}/provider-draft",
			array(
				'expected_version' => 4,
				'idempotency_key'  => 'rest-draft-1',
			)
		);
		self::assertSame( 201, $draft->get_status() );

		return $draft->get_data()['campaign'];
	}

	/** @param array<int, string> $recipients Test addresses. */
	private function test_send( string $campaign_id, array $recipients, string $key, ?string $format = null ): WP_REST_Response {
		return $this->request(
			'POST',
			"/{$campaign_id}/test-send",
			array_filter(
				array(
					'recipients'      => $recipients,
					'idempotency_key' => $key,
					'format'          => $format,
				),
				static fn ( mixed $value ): bool => null !== $value
			)
		);
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
