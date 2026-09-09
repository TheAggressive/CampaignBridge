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
}
