<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,CampaignBridge.Standard.Sniffs.Database
/**
 * Campaign REST authentication, authorization, and leakage tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Security;

use CampaignBridge\Core\Capabilities;
use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\Repository\Campaign_Repository;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\Repository\Schema_Manager;
use CampaignBridge\REST\Campaign_Routes;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/** Proves each campaign guard is registered and then refuses without leaking data. */
final class Campaign_Routes_Security_Test extends Test_Case {
	private const COLLECTION = '/campaignbridge/v1/campaigns';

	/** Transport keys that must never appear in a campaign representation. */
	private const FORBIDDEN_KEYS = array( 'api_key', 'credentials', 'authorization', 'remote_id', 'review_input', 'artifact', 'blocks', 'raw', 'provider_response', 'user_email', 'subscribers', 'members' );

	private int $admin_id;
	private int $template_id;

	public function setUp(): void {
		parent::setUp();
		self::assertTrue( Schema_Manager::migrate() );

		global $wpdb;
		foreach ( array_reverse( Schema_Manager::table_names() ) as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Allowlisted isolated test tables.
		}

		do_action( 'rest_api_init' );
		Routes::register();

		$this->admin_id    = $this->create_test_user( array( 'role' => 'administrator' ) );
		$this->template_id = $this->factory->post->create(
			array(
				'post_type'    => Post_Type_Email_Template::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Confidential launch template',
				'post_content' => '<!-- wp:campaignbridge/container /-->',
				'post_author'  => $this->admin_id,
			)
		);
		wp_set_current_user( $this->admin_id );
	}

	public function tearDown(): void {
		global $wp_rest_auth_cookie;
		$wp_rest_auth_cookie = false;
		unset( $_SERVER['HTTP_X_WP_NONCE'], $_REQUEST['_wpnonce'] );
		( new Provider_Connection_Repository() )->delete( 'mailchimp' );
		parent::tearDown();
	}

	public function test_every_campaign_route_registers_a_capability_guard_that_refuses(): void {
		$guarded = 0;
		foreach ( rest_get_server()->get_routes( 'campaignbridge/v1' ) as $route => $endpoints ) {
			if ( ! str_starts_with( $route, self::COLLECTION ) ) {
				continue;
			}
			foreach ( $endpoints as $endpoint ) {
				$expected = match ( true ) {
					str_ends_with( $route, '/approve' ), str_ends_with( $route, '/provider-draft' ) => 'can_approve_campaigns',
					str_ends_with( $route, '/test-send' ) => 'can_test_campaigns',
					str_ends_with( $route, '/schedule' ), str_ends_with( $route, '/unschedule' ), str_ends_with( $route, '/reconcile' ) => 'can_deliver_campaigns',
					default => 'can_access_campaigns',
				};
				self::assertSame( array( Campaign_Routes::class, $expected ), $endpoint['permission_callback'], $route );
				++$guarded;
			}
		}
		self::assertSame( 18, $guarded );

		$campaign = $this->create_campaign()['id'];
		wp_set_current_user( 0 );
		self::assertSame( 401, $this->request( 'GET', self::COLLECTION )->get_status() );
		self::assertSame( 401, $this->request( 'GET', self::COLLECTION . "/{$campaign}" )->get_status() );

		$uncapable = $this->create_test_user( array( 'role' => 'editor' ) );
		wp_set_current_user( $uncapable );
		foreach ( array( array( 'GET', '' ), array( 'POST', '' ), array( 'GET', "/{$campaign}" ), array( 'POST', "/{$campaign}/archive" ) ) as $call ) {
			$refused = $this->request(
				$call[0],
				self::COLLECTION . $call[1],
				array(
					'template_id'      => $this->template_id,
					'expected_version' => 1,
				)
			);
			self::assertSame( 403, $refused->get_status() );
			self::assertSame( 'rest_forbidden', $refused->get_data()['code'] );
			$this->assert_no_campaign_data( $refused, $campaign );
		}
		self::assertSame( 'draft', ( new Campaign_Repository() )->get( $campaign )?->state() );
	}

	public function test_cookie_authentication_requires_a_valid_rest_nonce(): void {
		global $wp_rest_auth_cookie;
		$wp_rest_auth_cookie = true;

		self::assertTrue( rest_cookie_check_errors( null ) );
		self::assertSame( 0, get_current_user_id() );
		self::assertSame( 401, $this->request( 'POST', self::COLLECTION, array( 'template_id' => $this->template_id ) )->get_status() );

		wp_set_current_user( $this->admin_id );
		$_SERVER['HTTP_X_WP_NONCE'] = 'invalid-nonce';
		$invalid                    = rest_cookie_check_errors( null );
		self::assertWPError( $invalid );
		self::assertSame( 'rest_cookie_invalid_nonce', $invalid->get_error_code() );

		$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_rest' );
		self::assertTrue( rest_cookie_check_errors( null ) );
		self::assertSame( $this->admin_id, get_current_user_id() );
		self::assertSame( 201, $this->request( 'POST', self::COLLECTION, array( 'template_id' => $this->template_id ) )->get_status() );
	}

	public function test_object_denials_do_not_enumerate_campaigns_or_template_content(): void {
		$owner    = $this->campaign_author();
		$intruder = $this->campaign_author();
		$campaign = $this->create_campaign( $owner )['id'];

		wp_set_current_user( $intruder );
		self::assertSame( array(), $this->request( 'GET', self::COLLECTION )->get_data()['items'] );
		self::assertSame( 0, $this->request( 'GET', self::COLLECTION )->get_data()['pagination']['total'] );
		self::assertSame( 403, $this->request( 'GET', self::COLLECTION, array( 'owner_user_id' => $owner ) )->get_status() );

		$calls = array(
			array( 'GET', '', array() ),
			array( 'POST', '/snapshot', array( 'expected_version' => 1 ) ),
			array( 'POST', '/validation', array() ),
			array( 'POST', '/preview', array() ),
			array( 'POST', '/archive', array( 'expected_version' => 2 ) ),
			array( 'POST', '/duplicate', array( 'idempotency_key' => 'intruder' ) ),
		);
		foreach ( $calls as $call ) {
			$denied = $this->request( $call[0], self::COLLECTION . "/{$campaign}{$call[1]}", $call[2] );
			self::assertSame( 403, $denied->get_status(), $call[1] );
			self::assertSame( 'campaignbridge_campaign_forbidden', $denied->get_data()['code'] );
			self::assertSame( array( 'status' => 403 ), $denied->get_data()['data'], 'A denial must not reveal the current version.' );
			$this->assert_no_campaign_data( $denied, $campaign );
		}
		self::assertSame( 1, ( new Campaign_Repository() )->get( $campaign )?->version() );
		self::assertCount( 1, ( new Campaign_Repository() )->for_owner( $owner ) );
	}

	public function test_test_send_requires_its_own_capability_and_campaign_authority_before_any_provider_call(): void {
		$calls  = 0;
		$filter = static function () use ( &$calls ): \WP_Error {
			++$calls;
			return new \WP_Error( 'unexpected_request', 'No provider call is expected.' );
		};
		add_filter( 'pre_http_request', $filter );
		$owner    = $this->campaign_author();
		$campaign = $this->create_campaign( $owner, array( 'provider' => 'mailchimp' ) )['id'];
		$params   = array(
			'recipients'      => array( 'qa@example.com' ),
			'idempotency_key' => 'security-test',
		);

		wp_set_current_user( 0 );
		self::assertSame( 401, $this->request( 'POST', self::COLLECTION . "/{$campaign}/test-send", $params )->get_status() );

		// Approval and production send authority do not grant test sends.
		get_userdata( $owner )->add_cap( Capabilities::SEND_CAMPAIGNS );
		wp_set_current_user( $owner );
		$refused = $this->request( 'POST', self::COLLECTION . "/{$campaign}/test-send", $params );
		self::assertSame( 403, $refused->get_status() );
		self::assertSame( 'rest_forbidden', $refused->get_data()['code'] );
		$this->assert_no_campaign_data( $refused, $campaign );

		// The test capability alone does not reach another owner's campaign.
		$intruder = $this->campaign_author();
		get_userdata( $intruder )->add_cap( Capabilities::TEST_CAMPAIGNS );
		wp_set_current_user( $intruder );
		$denied = $this->request( 'POST', self::COLLECTION . "/{$campaign}/test-send", $params );
		self::assertSame( 403, $denied->get_status() );
		self::assertSame( 'campaignbridge_campaign_forbidden', $denied->get_data()['code'] );
		$this->assert_no_campaign_data( $denied, $campaign );

		remove_filter( 'pre_http_request', $filter );
		self::assertSame( 0, $calls );
		self::assertSame( 'draft', ( new Campaign_Repository() )->get( $campaign )?->state() );
	}

	public function test_success_representations_exclude_credentials_and_persistence_internals(): void {
		self::assertTrue( ( new Provider_Connection_Repository() )->save( Provider_Connection::create( 'mailchimp', 'encrypted-secret-us1', 'audience-secret' ) ) );
		$campaign = $this->create_campaign( null, array( 'provider' => 'mailchimp' ) )['id'];

		$responses = array(
			$this->request( 'GET', self::COLLECTION ),
			$this->request( 'GET', self::COLLECTION . "/{$campaign}" ),
			$this->request( 'POST', self::COLLECTION . "/{$campaign}/snapshot", array( 'expected_version' => 1 ) ),
			$this->request( 'POST', self::COLLECTION . "/{$campaign}/validation" ),
			$this->request( 'POST', self::COLLECTION . "/{$campaign}/preview" ),
			$this->request( 'POST', self::COLLECTION . "/{$campaign}/duplicate", array( 'idempotency_key' => 'leak-check' ) ),
		);
		foreach ( $responses as $response ) {
			self::assertLessThan( 300, $response->get_status() );
			self::assertSame( 'no-store', $response->get_headers()['Cache-Control'] ?? null );
			self::assertStringNotContainsString( 'secret', (string) wp_json_encode( $response->get_data() ) );
			$this->assert_no_forbidden_keys( $response->get_data() );
		}
	}

	private function assert_no_campaign_data( WP_REST_Response $response, string $campaign_id ): void {
		$json = (string) wp_json_encode( $response->get_data() );
		self::assertArrayNotHasKey( 'campaign', $response->get_data() );
		self::assertStringNotContainsString( 'Confidential launch template', $json );
		self::assertStringNotContainsString( '"template_id"', $json );
		self::assertStringNotContainsString( 'owner_user_id', $json );
		self::assertStringNotContainsString( $campaign_id, $json );
	}

	/** @param mixed $data Response data. */
	private function assert_no_forbidden_keys( mixed $data ): void {
		if ( ! is_array( $data ) ) {
			return;
		}
		foreach ( $data as $key => $value ) {
			self::assertNotContains( $key, self::FORBIDDEN_KEYS, 'Response exposed ' . $key );
			$this->assert_no_forbidden_keys( $value );
		}
	}

	private function campaign_author(): int {
		$user_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		$user    = get_userdata( $user_id );
		$user->add_cap( Capabilities::CREATE_CAMPAIGNS );
		$user->add_cap( Capabilities::EDIT_TEMPLATES );
		return $user_id;
	}

	/**
	 * @param array<string, mixed> $extra Additional create fields.
	 * @return array<string, mixed>
	 */
	private function create_campaign( ?int $owner_id = null, array $extra = array() ): array {
		$current = get_current_user_id();
		wp_set_current_user( $this->admin_id );
		$body = array_merge( array( 'template_id' => $this->template_id ), $extra );
		if ( null !== $owner_id ) {
			$body['owner_user_id'] = $owner_id;
		}
		$response = $this->request( 'POST', self::COLLECTION, $body );
		wp_set_current_user( $current );
		self::assertSame( 201, $response->get_status() );
		return $response->get_data()['campaign'];
	}

	/** @param array<string, mixed> $params Request parameters. */
	private function request( string $method, string $route, array $params = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_get_server()->dispatch( $request );
	}
}
