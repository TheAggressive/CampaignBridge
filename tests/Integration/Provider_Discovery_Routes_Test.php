<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment
/**
 * Provider discovery REST integration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Admin\Controllers\Settings_Controller;
use CampaignBridge\Core\Capabilities;
use CampaignBridge\Core\Encryption;
use CampaignBridge\Domain\Campaign\Provider_Connection;
use CampaignBridge\Repository\Provider_Connection_Repository;
use CampaignBridge\REST\Provider_Discovery_Routes;
use CampaignBridge\REST\Routes;
use CampaignBridge\Tests\Helpers\Test_Case;
use WP_REST_Request;
use WP_REST_Response;

/** Proves the REST adapter reads the cache, refreshes explicitly, and never leaks credentials. */
final class Provider_Discovery_Routes_Test extends Test_Case {
	private const BASE = '/campaignbridge/v1/providers/mailchimp';

	private int $author_id;

	/** @var array<int, string> Requested Mailchimp URLs. */
	private array $requests = array();

	/** @var array{0: int, 1: string} Scripted Mailchimp status and body. */
	private array $reply;

	/** @var callable|null */
	private $filter = null;

	private static function api_key(): string {
		return str_repeat( 'c0ffee', 5 ) . 'c0' . '-us20';
	}

	public function setUp(): void {
		parent::setUp();
		do_action( 'rest_api_init' );
		Routes::register();

		$this->reply  = array(
			200,
			(string) wp_json_encode(
				array(
					'lists'       => array(
						array(
							'id'                => 'abc123',
							'name'              => 'Customers',
							'stats'             => array( 'member_count' => 12 ),
							'campaign_defaults' => array(
								'from_name'  => 'Example Shop',
								'from_email' => 'news@example.com',
							),
							'contact'           => array( 'address1' => '1 Private Lane' ),
						),
					),
					'total_items' => 1,
				)
			),
		);
		$this->filter = function ( mixed $preempt, array $args, string $url ): array {
			$this->requests[] = $url;
			self::assertSame( 'Bearer ' . self::api_key(), $args['headers']['Authorization'] ?? null );

			return array(
				'headers'  => array(),
				'body'     => $this->reply[1],
				'response' => array(
					'code'    => $this->reply[0],
					'message' => '',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $this->filter, 10, 3 );

		( new Provider_Connection_Repository() )->save( Provider_Connection::create( 'mailchimp', Encryption::encrypt( self::api_key() ), '' ) );
		$this->author_id = $this->create_test_user( array( 'role' => 'subscriber' ) );
		get_userdata( $this->author_id )->add_cap( Capabilities::CREATE_CAMPAIGNS );
		wp_set_current_user( $this->author_id );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', $this->filter );
		( new Provider_Connection_Repository() )->delete( 'mailchimp' );
		parent::tearDown();
	}

	public function test_routes_require_a_campaign_capability(): void {
		foreach ( array( '/capabilities', '/discovery/audiences' ) as $suffix ) {
			$route = rest_get_server()->get_routes()[ '/campaignbridge/v1/providers/(?P<provider>[a-z0-9][a-z0-9_-]{0,63})' . str_replace( 'audiences', '(?P<kind>audiences|merge_fields|segments)', $suffix ) ] ?? null;
			self::assertNotNull( $route, $suffix );
			self::assertSame( array( Provider_Discovery_Routes::class, 'can_discover' ), $route[0]['permission_callback'] );
		}

		wp_set_current_user( 0 );
		self::assertSame( 401, $this->request( 'GET', '/capabilities' )->get_status() );
		wp_set_current_user( $this->create_test_user( array( 'role' => 'editor' ) ) );
		self::assertSame( 403, $this->request( 'POST', '/discovery/audiences/refresh' )->get_status() );
		self::assertSame( array(), $this->requests );
	}

	public function test_capabilities_are_reported_explicitly(): void {
		$response = $this->request( 'GET', '/capabilities' );

		self::assertSame( 200, $response->get_status() );
		self::assertTrue( $response->get_data()['operations']['discover_audiences'] );
		self::assertTrue( $response->get_data()['operations']['send'] );
		self::assertFalse( $response->get_data()['operations']['cancel'] );
		self::assertSame( 'campaignbridge_provider_not_found', $this->request( 'GET', '/capabilities', array(), '/campaignbridge/v1/providers/html' )->get_data()['code'] );
	}

	public function test_reads_use_the_cache_and_only_refresh_calls_mailchimp(): void {
		$miss = $this->request( 'GET', '/discovery/audiences' );
		self::assertSame( 200, $miss->get_status() );
		self::assertSame( 'none', $miss->get_data()['source'] );
		self::assertSame( array(), $miss->get_data()['items'] );
		self::assertSame( array(), $this->requests, 'A read never contacts Mailchimp.' );

		$fresh = $this->request( 'POST', '/discovery/audiences/refresh' );
		self::assertSame( 200, $fresh->get_status() );
		self::assertCount( 1, $this->requests );
		self::assertSame( 'remote', $fresh->get_data()['source'] );
		self::assertSame( 'no-store', $fresh->get_headers()['Cache-Control'] );
		self::assertSame(
			array(
				array(
					'id'             => 'abc123',
					'name'           => 'Customers',
					'member_count'   => 12,
					'default_sender' => array(
						'from_name'  => 'Example Shop',
						'from_email' => 'news@example.com',
					),
				),
			),
			$fresh->get_data()['items']
		);
		$json = (string) wp_json_encode( $fresh->get_data() );
		self::assertStringNotContainsString( self::api_key(), $json );
		self::assertStringNotContainsString( 'Private Lane', $json );

		$hit = $this->request( 'GET', '/discovery/audiences' );
		self::assertSame( 'cache', $hit->get_data()['source'] );
		self::assertFalse( $hit->get_data()['stale'] );
		self::assertCount( 1, $this->requests );
	}

	public function test_failed_refresh_maps_to_a_gateway_status_or_a_stale_list(): void {
		$this->reply = array( 401, '{"title":"API Key Invalid","detail":"secret detail"}' );
		$failed      = $this->request( 'POST', '/discovery/audiences/refresh' );
		self::assertSame( 502, $failed->get_status() );
		self::assertSame( 'mailchimp_authentication_failed', $failed->get_data()['code'] );
		self::assertStringNotContainsString( 'secret detail', (string) wp_json_encode( $failed->get_data() ) );

		$this->reply = array( 429, '{}' );
		$limited     = $this->request( 'POST', '/discovery/audiences/refresh' );
		self::assertSame( 429, $limited->get_status() );
		self::assertTrue( $limited->get_data()['data']['retryable'] );
	}

	public function test_failed_refresh_with_a_cached_list_returns_it_stale(): void {
		self::assertSame( 200, $this->request( 'POST', '/discovery/audiences/refresh' )->get_status() );

		$this->reply = array( 503, '{}' );
		$stale       = $this->request( 'POST', '/discovery/audiences/refresh' );
		self::assertSame( 200, $stale->get_status() );
		self::assertTrue( $stale->get_data()['stale'] );
		self::assertSame( 'cache', $stale->get_data()['source'] );
		self::assertSame( 'provider_error', $stale->get_data()['error']['category'] );
		self::assertSame( 'abc123', $stale->get_data()['items'][0]['id'] );
	}

	public function test_scope_and_configuration_are_validated(): void {
		self::assertSame( 'campaignbridge_discovery_invalid_scope', $this->request( 'GET', '/discovery/audiences', array( 'audience' => 'abc123' ) )->get_data()['code'] );
		self::assertSame( 'campaignbridge_discovery_invalid_scope', $this->request( 'GET', '/discovery/merge_fields' )->get_data()['code'] );
		self::assertSame( 'rest_invalid_param', $this->request( 'GET', '/discovery/merge_fields', array( 'audience' => '../x' ) )->get_data()['code'] );

		( new Provider_Connection_Repository() )->delete( 'mailchimp' );
		$unconfigured = $this->request( 'POST', '/discovery/audiences/refresh' );
		self::assertSame( 409, $unconfigured->get_status() );
		self::assertSame( 'mailchimp_not_configured', $unconfigured->get_data()['code'] );
		self::assertSame( array(), $this->requests );
	}

	public function test_refresh_is_rate_limited_per_user(): void {
		for ( $i = 0; $i < 10; ++$i ) {
			self::assertSame( 200, $this->request( 'POST', '/discovery/audiences/refresh' )->get_status() );
		}

		$limited = $this->request( 'POST', '/discovery/audiences/refresh' );
		self::assertSame( 429, $limited->get_status() );
		self::assertSame( 'rate_limit_exceeded', $limited->get_data()['code'] );
		self::assertCount( 10, $this->requests );
		self::assertSame( 200, $this->request( 'GET', '/discovery/audiences' )->get_status(), 'Cache reads are not rate limited.' );
	}

	public function test_settings_screen_shares_the_discovery_cache(): void {
		$method = new \ReflectionMethod( Settings_Controller::class, 'get_mailchimp_audiences' );
		$screen = new Settings_Controller();

		self::assertSame(
			array(
				'options' => array( 'abc123' => 'Customers' ),
				'error'   => '',
			),
			$method->invoke( $screen, true )
		);
		self::assertCount( 1, $this->requests, 'An empty cache is refreshed once.' );

		$method->invoke( $screen, true );
		self::assertCount( 1, $this->requests, 'Later views use the shared cache.' );
		self::assertSame( 'cache', $this->request( 'GET', '/discovery/audiences' )->get_data()['source'] );
	}

	/** @param array<string, mixed> $params Request parameters. */
	private function request( string $method, string $suffix, array $params = array(), string $base = self::BASE ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $base . $suffix );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}
}
