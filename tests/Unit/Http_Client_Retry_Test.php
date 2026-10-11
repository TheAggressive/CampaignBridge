<?php
/**
 * HTTP retry policy tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit;

use CampaignBridge\Core\Http_Client;
use CampaignBridge\Core\Http_Origin;
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

	public function test_requests_force_redirects_off(): void {
		$captured_args = array();
		$preempt       = static function ( $response, array $args ) use ( &$captured_args ) {
			$captured_args = $args;

			return array(
				'headers'  => array(),
				'body'     => '{}',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
			);
		};
		add_filter( 'pre_http_request', $preempt, 10, 3 );

		try {
			Http_Client::post(
				'https://api.example.com/resource',
				array(
					'redirection'           => 5,
					'campaignbridge_origin' => Http_Origin::host( 'api.example.com' ),
				)
			);
		} finally {
			remove_filter( 'pre_http_request', $preempt, 10 );
		}

		$this->assertSame( 0, $captured_args['redirection'] );
	}

	/**
	 * Retry-After is read in both its forms: seconds and an HTTP date.
	 */
	public function test_retry_after_reads_seconds_and_http_dates(): void {
		$method   = new ReflectionMethod( Http_Client::class, 'retry_after' );
		$response = static fn ( string $value ): array => array(
			'headers'  => array( 'retry-after' => $value ),
			'response' => array( 'code' => 429 ),
		);

		$this->assertSame( 30, $method->invoke( null, $response( '30' ), 429 ) );
		$in_ten = $method->invoke( null, $response( gmdate( 'D, d M Y H:i:s', time() + 10 ) . ' GMT' ), 503 );
		$this->assertGreaterThanOrEqual( 9, $in_ten );
		$this->assertLessThanOrEqual( 10, $in_ten );
		$this->assertSame( 0, $method->invoke( null, $response( gmdate( 'D, d M Y H:i:s', time() - 60 ) . ' GMT' ), 429 ), 'A date in the past means now.' );
		$this->assertNull( $method->invoke( null, $response( 'soon' ), 429 ) );
		$this->assertNull( $method->invoke( null, $response( '30' ), 500 ), 'Only 429 and 503 carry a wait.' );
	}

	/**
	 * A request its caller marked never-retry is sent once, whatever comes back.
	 */
	public function test_a_never_retry_request_is_sent_once_on_rate_limits_outages_and_timeouts(): void {
		$waits = array();
		Http_Client::use_sleeper(
			static function ( float $seconds ) use ( &$waits ): void {
				$waits[] = $seconds;
			}
		);
		$replies = array(
			array(
				'headers'  => array( 'retry-after' => '1' ),
				'body'     => '{}',
				'response' => array( 'code' => 429, 'message' => '' ),
			),
			array(
				'headers'  => array(),
				'body'     => '{}',
				'response' => array( 'code' => 503, 'message' => '' ),
			),
			new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ),
		);

		try {
			foreach ( $replies as $reply ) {
				$calls   = 0;
				$preempt = static function () use ( &$calls, $reply ) {
					++$calls;
					return $reply;
				};
				add_filter( 'pre_http_request', $preempt, 10, 3 );
				Http_Client::post(
					'https://api.example.com/campaigns/1/actions/send',
					array(
						'campaignbridge_retry'  => false,
						'campaignbridge_origin' => Http_Origin::host( 'api.example.com' ),
					)
				);
				remove_filter( 'pre_http_request', $preempt, 10 );
				$this->assertSame( 1, $calls );
			}
		} finally {
			Http_Client::use_sleeper( null );
		}

		$this->assertSame( array(), $waits );
	}

	public function test_log_url_formatter_removes_credentials_query_and_fragment(): void {
		$method = new ReflectionMethod( Http_Client::class, 'safe_url_for_log' );
		$url    = 'https://user:password@api.example.com:8443/v1/lists?api_key=secret-value#private-fragment';

		$safe = $method->invoke( null, $url );

		$this->assertSame( 'https://api.example.com:8443/v1/lists', $safe );
		$this->assertStringNotContainsString( 'secret-value', $safe );
		$this->assertStringNotContainsString( 'password', $safe );
	}
}
