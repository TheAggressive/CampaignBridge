<?php
/**
 * Mailchimp provider tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Core\Http_Client_Interface;
use CampaignBridge\Domain\Campaign\Connection_Result;
use CampaignBridge\Providers\Mailchimp_Provider;
use ReflectionMethod;
use WP_Error;
use WP_UnitTestCase;

/**
 * A mock HTTP client for testing.
 */
class Mock_Http_Client implements Http_Client_Interface {
	/**
	 * @var array<string, mixed>|WP_Error
	 */
	private $response;

	/**
	 * @var array<int, string>
	 */
	private array $requested_urls = array();

	/**
	 * Set the response to return.
	 *
	 * @param array<string, mixed>|WP_Error $response Response to return.
	 */
	public function set_response( array|WP_Error $response ): void {
		$this->response = $response;
	}

	/**
	 * Get the URLs that were requested.
	 *
	 * @return array<int, string>
	 */
	public function get_requested_urls(): array {
		return $this->requested_urls;
	}

	public function post( string $url, array $args = array() ) {
		$this->requested_urls[] = $url;
		return $this->response;
	}

	public function get( string $url, array $args = array() ) {
		$this->requested_urls[] = $url;
		return $this->response;
	}

	public function put( string $url, array $args = array() ) {
		$this->requested_urls[] = $url;
		return $this->response;
	}

	public function delete( string $url, array $args = array() ) {
		$this->requested_urls[] = $url;
		return $this->response;
	}
}

/**
 * Verify the Mailchimp provider implements the Provider_Interface port correctly.
 */
class Mailchimp_Provider_Test extends WP_UnitTestCase {
	private const VALID_KEY = '0123456789abcdef' . '0123456789abcdef' . '-us20';

	private function make_success_response( string $body = '' ): array {
		return array(
			'body'        => $body,
			'headers'     => array(),
			'status_code' => 200,
		);
	}

	private function make_error_response( int $code, string $body = '' ): array {
		return array(
			'body'        => $body,
			'headers'     => array(),
			'status_code' => $code,
		);
	}

	/**
	 * The key data center selects the API host.
	 */
	public function test_api_url_uses_key_data_center(): void {
		$method = new ReflectionMethod( Mailchimp_Provider::class, 'build_api_url' );

		$url = $method->invoke( null, self::VALID_KEY, '/campaigns' );

		$this->assertSame( 'https://us20.api.mailchimp.com/3.0/campaigns', $url );
	}

	/**
	 * A valid API key passes is_configured().
	 */
	public function test_is_configured_with_valid_key(): void {
		$provider = new Mailchimp_Provider();
		$this->assertTrue( $provider->is_configured( array( 'api_key' => self::VALID_KEY ) ) );
	}

	/**
	 * An invalid API key fails is_configured().
	 */
	public function test_is_configured_with_invalid_key(): void {
		$provider = new Mailchimp_Provider();
		$this->assertFalse( $provider->is_configured( array( 'api_key' => 'not-a-valid-key' ) ) );
		$this->assertFalse( $provider->is_configured( array() ) );
	}

	/**
	 * verify_connection() returns Connection_Result::success() on a 200 ping.
	 */
	public function test_verify_connection_returns_success_on_ping_200(): void {
		$mock     = new Mock_Http_Client();
		$mock->set_response( $this->make_success_response( '{"health_status":"Everything is Chimpy!"}' ) );

		$provider = new Mailchimp_Provider( $mock );
		$result   = $provider->verify_connection( array( 'api_key' => self::VALID_KEY ) );

		self::assertInstanceOf( Connection_Result::class, $result );
		self::assertTrue( $result->connected() );
		self::assertNull( $result->error() );
		self::assertSame( array( 'https://us20.api.mailchimp.com/3.0/ping' ), $mock->get_requested_urls() );
	}

	/**
	 * verify_connection() returns a failure with an authentication error on 401.
	 */
	public function test_verify_connection_returns_failure_on_ping_401(): void {
		$mock     = new Mock_Http_Client();
		$mock->set_response( $this->make_error_response( 401, '{"detail":"Invalid API key"}' ) );

		$provider = new Mailchimp_Provider( $mock );
		$result   = $provider->verify_connection( array( 'api_key' => self::VALID_KEY ) );

		self::assertInstanceOf( Connection_Result::class, $result );
		self::assertFalse( $result->connected() );
		self::assertNotNull( $result->error() );
		self::assertSame( 'authentication', $result->error()->category() );
		self::assertSame( 'mailchimp_connection_rejected', $result->error()->code() );
	}

	/**
	 * verify_connection() returns a failure for an invalid key format (no network call).
	 */
	public function test_verify_connection_rejects_invalid_key_format(): void {
		$provider = new Mailchimp_Provider();
		$result   = $provider->verify_connection( array( 'api_key' => 'invalid' ) );

		self::assertInstanceOf( Connection_Result::class, $result );
		self::assertFalse( $result->connected() );
		self::assertSame( 'authentication', $result->error()->category() );
		self::assertSame( 'mailchimp_invalid_credentials', $result->error()->code() );
	}

	/**
	 * verify_connection() returns a network error when the HTTP client fails.
	 */
	public function test_verify_connection_returns_network_error_on_http_failure(): void {
		$mock     = new Mock_Http_Client();
		$mock->set_response( new WP_Error( 'http_exception', 'HTTP request failed' ) );

		$provider = new Mailchimp_Provider( $mock );
		$result   = $provider->verify_connection( array( 'api_key' => self::VALID_KEY ) );

		self::assertInstanceOf( Connection_Result::class, $result );
		self::assertFalse( $result->connected() );
		self::assertSame( 'network', $result->error()->category() );
		self::assertSame( 'mailchimp_connection_unavailable', $result->error()->code() );
	}

	/**
	 * get_audiences() normalizes the Mailchimp list response for the admin UI.
	 */
	public function test_audiences_are_normalized_for_the_admin_ui(): void {
		$mock = new Mock_Http_Client();
		$mock->set_response(
			$this->make_success_response( '{"lists":[{"id":"abc123","name":"Customers"},{"id":"def456","name":"Newsletter"}],"total_items":2}' )
		);

		$provider = new Mailchimp_Provider( $mock );
		$result   = $provider->get_audiences( array( 'api_key' => self::VALID_KEY ) );

		self::assertSame( array( 'abc123' => 'Customers', 'def456' => 'Newsletter' ), $result );
		self::assertSame(
			array( 'https://us20.api.mailchimp.com/3.0/lists?count=1000&fields=lists.id,lists.name,total_items' ),
			$mock->get_requested_urls()
		);
	}

	/**
	 * Provider identity is stable.
	 */
	public function test_provider_identity(): void {
		$provider = new Mailchimp_Provider();
		$this->assertSame( 'mailchimp', $provider->slug() );
		$this->assertNotEmpty( $provider->label() );
	}
}
