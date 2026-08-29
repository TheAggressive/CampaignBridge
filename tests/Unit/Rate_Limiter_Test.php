<?php
/**
 * REST rate limiter tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\REST\Rate_Limiter;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Verify client address trust boundaries.
 */
class Rate_Limiter_Test extends WP_UnitTestCase {
	/**
	 * Untrusted forwarding headers cannot replace REMOTE_ADDR.
	 */
	public function test_forwarded_headers_are_not_trusted(): void {
		$_SERVER['REMOTE_ADDR']          = '192.0.2.10';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';

		$method = new ReflectionMethod( Rate_Limiter::class, 'get_client_ip' );
		$this->assertSame( '192.0.2.10', $method->invoke( null ) );

		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * A trusted integration can provide a validated proxy address.
	 */
	public function test_trusted_proxy_filter_can_supply_client_address(): void {
		$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
		$callback               = static fn (): string => '198.51.100.99';
		add_filter( 'campaignbridge_client_ip', $callback );

		$method = new ReflectionMethod( Rate_Limiter::class, 'get_client_ip' );
		$this->assertSame( '198.51.100.99', $method->invoke( null ) );

		remove_filter( 'campaignbridge_client_ip', $callback );
		unset( $_SERVER['REMOTE_ADDR'] );
	}
}
