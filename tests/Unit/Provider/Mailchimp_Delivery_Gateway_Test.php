<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Mailchimp delivery gateway tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Provider;

use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Action_Outcome;
use CampaignBridge\Providers\Mailchimp_Delivery_Gateway;
use WP_UnitTestCase;

require_once __DIR__ . '/Mailchimp_Test_Gateway_Test.php';

/** Proves schedule and unschedule each post one action, never retry, and never send. */
final class Mailchimp_Delivery_Gateway_Test extends WP_UnitTestCase {
	private static function api_key(): string {
		return str_repeat( 'c0ffee', 5 ) . 'c0-us20';
	}

	/** @return array<string, string> */
	private static function settings(): array {
		return array( 'api_key' => self::api_key() );
	}

	/** @return array<string, mixed> */
	private static function reply( int $status, string $body = '' ): array {
		return array(
			'status_code' => $status,
			'body'        => $body,
			'headers'     => array(),
		);
	}

	public function test_schedule_posts_the_utc_time_once_without_retry(): void {
		$http    = new Recording_Test_Http_Client( self::reply( 204 ) );
		$gateway = new Mailchimp_Delivery_Gateway( $http );

		self::assertSame( 15, $gateway->schedule_interval_minutes(), 'Mailchimp schedules only on the quarter-hour.' );
		self::assertSame( Action_Outcome::ACCEPTED, $gateway->schedule( self::settings(), 'mc0042', '2026-10-05T15:00:00Z' )->status() );
		self::assertCount( 1, $http->requests );
		$request = $http->requests[0];
		self::assertSame( array( 'POST', 'https://us20.api.mailchimp.com/3.0/campaigns/mc0042/actions/schedule' ), array( $request['method'], $request['url'] ) );
		self::assertSame( 'Bearer ' . self::api_key(), $request['args']['headers']['Authorization'] );
		self::assertFalse( $request['args']['campaignbridge_retry'], 'A schedule must never be retried automatically.' );
		self::assertSame( array( 'schedule_time' => '2026-10-05T15:00:00+00:00' ), json_decode( $request['args']['body'], true ) );
	}

	public function test_unschedule_posts_once_without_a_body(): void {
		$http = new Recording_Test_Http_Client( self::reply( 204 ) );

		self::assertSame( Action_Outcome::ACCEPTED, ( new Mailchimp_Delivery_Gateway( $http ) )->unschedule( self::settings(), 'mc0042' )->status() );
		self::assertCount( 1, $http->requests );
		self::assertSame( 'https://us20.api.mailchimp.com/3.0/campaigns/mc0042/actions/unschedule', $http->requests[0]['url'] );
		self::assertArrayNotHasKey( 'body', $http->requests[0]['args'] );
		self::assertFalse( $http->requests[0]['args']['campaignbridge_retry'] );
	}

	public function test_send_posts_once_without_a_body_or_retry(): void {
		$http = new Recording_Test_Http_Client( self::reply( 204 ) );

		self::assertSame( Action_Outcome::ACCEPTED, ( new Mailchimp_Delivery_Gateway( $http ) )->send( self::settings(), 'mc0042' )->status() );
		self::assertCount( 1, $http->requests );
		self::assertSame( array( 'POST', 'https://us20.api.mailchimp.com/3.0/campaigns/mc0042/actions/send' ), array( $http->requests[0]['method'], $http->requests[0]['url'] ) );
		self::assertArrayNotHasKey( 'body', $http->requests[0]['args'] );
		self::assertFalse( $http->requests[0]['args']['campaignbridge_retry'], 'A send must never be retried automatically.' );
	}

	/** @return array<string, array{array<string, mixed>|\WP_Error, string, string}> */
	public static function failures(): array {
		return array(
			'not ready'         => array( self::reply( 400, '{"detail":"private detail"}' ), Action_Outcome::FAILED, 'mailchimp_request_rejected' ),
			'bad credentials'   => array( self::reply( 401 ), Action_Outcome::FAILED, 'mailchimp_authentication_failed' ),
			'forbidden'         => array( self::reply( 403 ), Action_Outcome::FAILED, 'mailchimp_authorization_failed' ),
			'missing campaign'  => array( self::reply( 404 ), Action_Outcome::FAILED, 'mailchimp_not_found' ),
			'throttled'         => array( self::reply( 429 ), Action_Outcome::FAILED, 'mailchimp_rate_limited' ),
			'server error'      => array( self::reply( 500 ), Action_Outcome::AMBIGUOUS, 'mailchimp_provider_error' ),
			'unavailable'       => array( self::reply( 503 ), Action_Outcome::AMBIGUOUS, 'mailchimp_provider_error' ),
			'unexpected status' => array( self::reply( 302 ), Action_Outcome::AMBIGUOUS, 'mailchimp_provider_error' ),
			'timeout'           => array( new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ), Action_Outcome::AMBIGUOUS, 'mailchimp_connection_timeout' ),
			'network'           => array( new \WP_Error( 'http_request_failed', 'Could not resolve host' ), Action_Outcome::AMBIGUOUS, 'mailchimp_connection_unavailable' ),
		);
	}

	/**
	 * @dataProvider failures
	 * @param array<string, mixed>|\WP_Error $response Scripted transport response.
	 */
	public function test_failures_keep_their_retryability_classification( array|\WP_Error $response, string $status, string $code ): void {
		foreach ( array( 'schedule', 'unschedule', 'send' ) as $action ) {
			$http    = new Recording_Test_Http_Client( $response );
			$gateway = new Mailchimp_Delivery_Gateway( $http );
			$outcome = match ( $action ) {
				'schedule' => $gateway->schedule( self::settings(), 'mc0042', '2026-10-05T15:00:00Z' ),
				'send'     => $gateway->send( self::settings(), 'mc0042' ),
				default    => $gateway->unschedule( self::settings(), 'mc0042' ),
			};

			self::assertSame( $status, $outcome->status(), $action );
			self::assertSame( $code, $outcome->error()?->code(), $action );
			self::assertStringNotContainsString( 'private detail', (string) wp_json_encode( $outcome->error()?->to_array() ) );
			self::assertCount( 1, $http->requests, 'Every failure is reported, never retried.' );
		}
	}

	public function test_a_throttled_action_is_a_retryable_definite_refusal(): void {
		$outcome = ( new Mailchimp_Delivery_Gateway( new Recording_Test_Http_Client( self::reply( 429 ) ) ) )->schedule( self::settings(), 'mc0042', '2026-10-05T15:00:00Z' );

		self::assertSame( Action_Outcome::FAILED, $outcome->status() );
		self::assertSame( Provider_Error_Category::RATE_LIMITED, $outcome->error()?->category() );
		self::assertTrue( $outcome->error()?->is_retryable() );
	}

	public function test_invalid_credentials_fail_before_any_request(): void {
		$http    = new Recording_Test_Http_Client( self::reply( 204 ) );
		$outcome = ( new Mailchimp_Delivery_Gateway( $http ) )->schedule( array( 'api_key' => 'nope' ), 'mc0042', '2026-10-05T15:00:00Z' );

		self::assertSame( Action_Outcome::FAILED, $outcome->status() );
		self::assertSame( array(), $http->requests );
	}
}
