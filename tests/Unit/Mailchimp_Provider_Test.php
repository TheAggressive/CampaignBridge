<?php
/**
 * Mailchimp provider tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Providers\Mailchimp_Provider;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Verify provider-specific URL construction.
 */
class Mailchimp_Provider_Test extends WP_UnitTestCase {
	private ?\Closure $http_filter = null;

	public function tearDown(): void {
		if ( null !== $this->http_filter ) {
			remove_filter( 'pre_http_request', $this->http_filter );
		}
		parent::tearDown();
	}

	public function test_credentials_are_redacted_without_changing_other_settings(): void {
		$provider = new Mailchimp_Provider();
		$settings = array( 'api_key' => 'private-fixture-us20', 'token' => 'short', 'audience_id' => 'audience' );
		$redacted = $provider->redact_settings( $settings );
		$this->assertStringNotContainsString( $settings['api_key'], $redacted['api_key'] );
		$this->assertStringNotContainsString( $settings['token'], $redacted['token'] );
		$this->assertSame( 'audience', $redacted['audience_id'] );
		$this->assertSame( 'private-fixture-us20', $settings['api_key'] );
	}

	/**
	 * The key data center selects the API host.
	 */
	public function test_api_url_uses_key_data_center(): void {
		$method = new ReflectionMethod( Mailchimp_Provider::class, 'build_api_url' );

		$url = $method->invoke( null, 'mailchimp-test-fixture-us20', '/campaigns' );

		$this->assertSame( 'https://us20.api.mailchimp.com/3.0/campaigns', $url );
	}

	/**
	 * Invalid data centers fail before an outbound request is attempted.
	 */
	public function test_api_url_rejects_invalid_key(): void {
		$method = new ReflectionMethod( Mailchimp_Provider::class, 'build_api_url' );

		$this->expectException( \InvalidArgumentException::class );
		$method->invoke( null, 'invalid-key', '/campaigns' );
	}

	public function test_connection_is_verified_against_ping_endpoint(): void {
		$this->http_filter = static function ( mixed $preempt, array $args, string $url ): array {
			self::assertSame( 'https://us20.api.mailchimp.com/3.0/ping', $url );
			self::assertSame( 'GET', $args['method'] ?? null );
			return array(
				'headers' => array(),
				'body' => '{"health_status":"Everything is Chimpy!"}',
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies' => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		$result = ( new Mailchimp_Provider() )->verify_connection( array( 'api_key' => str_repeat( 'a', 32 ) . '-us20' ) );
		self::assertFalse( is_wp_error( $result ) );
		self::assertTrue( $result['verified'] ?? false );
	}

	public function test_capabilities_only_advertise_implemented_workflows(): void {
		$capabilities = ( new Mailchimp_Provider() )->get_capabilities();
		self::assertTrue( $capabilities['verify_connection'] );
		self::assertTrue( $capabilities['discover_template_sections'] );
		self::assertTrue( $capabilities['discover_audiences'] );
		self::assertFalse( $capabilities['schedule'] );
		self::assertFalse( $capabilities['reports'] );
	}

	public function test_audiences_are_normalized_for_the_admin_ui(): void {
		$this->http_filter = static function ( mixed $preempt, array $args, string $url ): array {
			self::assertSame( 'https://us20.api.mailchimp.com/3.0/lists?count=1000&fields=lists.id,lists.name,total_items', $url );
			return array(
				'headers'  => array(),
				'body'     => '{"lists":[{"id":"abc123","name":"Customers"},{"id":"def456","name":"Newsletter"}],"total_items":2}',
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		$result = ( new Mailchimp_Provider() )->get_audiences( array( 'api_key' => str_repeat( 'a', 32 ) . '-us20' ) );

		self::assertSame( array( 'abc123' => 'Customers', 'def456' => 'Newsletter' ), $result );
	}
}
