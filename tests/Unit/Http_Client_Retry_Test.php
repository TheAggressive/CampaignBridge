<?php
/**
 * HTTP retry policy tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Core\Http_Client;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Verify that remote mutations are not replayed accidentally.
 */
class Http_Client_Retry_Test extends WP_UnitTestCase {
	/**
	 * Remote requests use the WordPress VIP maximum timeout.
	 */
	public function test_default_timeout_meets_vip_budget(): void {
		$reflection = new \ReflectionClass( Http_Client::class );
		$constant   = $reflection->getReflectionConstant( 'DEFAULT_TIMEOUT' );

		$this->assertNotFalse( $constant );
		$this->assertSame( 3, $constant->getValue() );
	}

	/**
	 * Safe methods retry, while mutations fail closed.
	 */
	public function test_retry_policy_is_method_aware(): void {
		$method = new ReflectionMethod( Http_Client::class, 'may_retry' );

		$this->assertTrue( $method->invoke( null, 'GET', null ) );
		$this->assertTrue( $method->invoke( null, 'PUT', null ) );
		$this->assertFalse( $method->invoke( null, 'POST', null ) );
		$this->assertFalse( $method->invoke( null, 'PATCH', null ) );
	}

	/**
	 * Callers may opt in only after establishing operation-level idempotency.
	 */
	public function test_retry_policy_honors_explicit_override(): void {
		$method = new ReflectionMethod( Http_Client::class, 'may_retry' );

		$this->assertTrue( $method->invoke( null, 'POST', true ) );
		$this->assertFalse( $method->invoke( null, 'GET', false ) );
	}
}
