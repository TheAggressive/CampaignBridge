<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,Generic.Files.OneObjectStructurePerFile
/**
 * Mailchimp test gateway tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Provider;

use CampaignBridge\Core\Http_Client_Interface;
use CampaignBridge\Core\Http_Origin;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Test_Delivery;
use CampaignBridge\Domain\Provider\Action_Outcome;
use CampaignBridge\Providers\Mailchimp_Test_Gateway;
use WP_UnitTestCase;

/** Answers one scripted response and records every request. */
final class Recording_Test_Http_Client implements Http_Client_Interface {
	/** @var array<int, array{method: string, url: string, args: array<string, mixed>}> */
	public array $requests = array();

	/** @param array<string, mixed>|\WP_Error $response Response to every request. */
	public function __construct( private array|\WP_Error $response ) {}

	public function get( string $url, array $args = array() ) {
		return $this->record( 'GET', $url, $args );
	}

	public function post( string $url, array $args = array() ) {
		return $this->record( 'POST', $url, $args );
	}

	public function put( string $url, array $args = array() ) {
		return $this->record( 'PUT', $url, $args );
	}

	public function patch( string $url, array $args = array() ) {
		return $this->record( 'PATCH', $url, $args );
	}

	public function delete( string $url, array $args = array() ) {
		return $this->record( 'DELETE', $url, $args );
	}

	/**
	 * @param array<string, mixed> $args Request arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function record( string $method, string $url, array $args ) {
		if ( ! ( $args['campaignbridge_origin'] ?? null ) instanceof Http_Origin || ! $args['campaignbridge_origin']->allows( $url ) ) {
			throw new \LogicException( 'Every Mailchimp request must declare a trusted origin that allows its URL.' );
		}
		$this->requests[] = array(
			'method' => $method,
			'url'    => $url,
			'args'   => $args,
		);

		return $this->response;
	}
}

/** Proves the test adapter calls only the test action, once, and classifies outcomes truthfully. */
final class Mailchimp_Test_Gateway_Test extends WP_UnitTestCase {
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

	public function test_test_action_is_posted_once_without_retry(): void {
		$http    = new Recording_Test_Http_Client( self::reply( 204 ) );
		$outcome = ( new Mailchimp_Test_Gateway( $http ) )->send_test( self::settings(), 'mc0042', Test_Delivery::create( array( 'QA@example.com', 'lead@example.org' ), Test_Delivery::FORMAT_TEXT ) );

		self::assertSame( Action_Outcome::ACCEPTED, $outcome->status() );
		self::assertCount( 1, $http->requests );
		$request = $http->requests[0];
		self::assertSame( 'POST', $request['method'] );
		self::assertSame( 'https://us20.api.mailchimp.com/3.0/campaigns/mc0042/actions/test', $request['url'] );
		self::assertSame( 'Bearer ' . self::api_key(), $request['args']['headers']['Authorization'] );
		self::assertFalse( $request['args']['campaignbridge_retry'], 'A test send must never be retried automatically.' );
		self::assertSame(
			array(
				'test_emails' => array( 'qa@example.com', 'lead@example.org' ),
				'send_type'   => 'plaintext',
			),
			json_decode( $request['args']['body'], true )
		);
	}

	public function test_html_format_maps_to_mailchimp_html(): void {
		$http = new Recording_Test_Http_Client( self::reply( 204 ) );
		( new Mailchimp_Test_Gateway( $http ) )->send_test( self::settings(), 'mc0042', Test_Delivery::create( array( 'qa@example.com' ), Test_Delivery::FORMAT_HTML ) );

		self::assertSame( 'html', json_decode( $http->requests[0]['args']['body'], true )['send_type'] );
	}

	/** @return array<string, array{array<string, mixed>|\WP_Error, string, string}> */
	public static function failures(): array {
		return array(
			'rejected request'  => array( self::reply( 400, '{"detail":"private detail"}' ), Action_Outcome::FAILED, 'mailchimp_request_rejected' ),
			'bad credentials'   => array( self::reply( 401 ), Action_Outcome::FAILED, 'mailchimp_authentication_failed' ),
			'missing draft'     => array( self::reply( 404 ), Action_Outcome::FAILED, 'mailchimp_not_found' ),
			'throttled'         => array( self::reply( 429 ), Action_Outcome::FAILED, 'mailchimp_rate_limited' ),
			'server error'      => array( self::reply( 503 ), Action_Outcome::AMBIGUOUS, 'mailchimp_provider_error' ),
			'unexpected status' => array( self::reply( 302 ), Action_Outcome::AMBIGUOUS, 'mailchimp_provider_error' ),
			'timeout'           => array( new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ), Action_Outcome::AMBIGUOUS, 'mailchimp_connection_timeout' ),
			'network'           => array( new \WP_Error( 'http_request_failed', 'Could not resolve host' ), Action_Outcome::AMBIGUOUS, 'mailchimp_connection_unavailable' ),
		);
	}

	/**
	 * @dataProvider failures
	 * @param array<string, mixed>|\WP_Error $response Scripted transport response.
	 */
	public function test_failures_are_classified_without_provider_detail( array|\WP_Error $response, string $status, string $code ): void {
		$http    = new Recording_Test_Http_Client( $response );
		$outcome = ( new Mailchimp_Test_Gateway( $http ) )->send_test( self::settings(), 'mc0042', Test_Delivery::create( array( 'qa@example.com' ), 'html' ) );

		self::assertSame( $status, $outcome->status() );
		self::assertSame( $code, $outcome->error()?->code() );
		self::assertStringNotContainsString( 'private detail', (string) wp_json_encode( $outcome->error()?->to_array() ) );
		self::assertCount( 1, $http->requests, 'Every failure is reported, never retried.' );
	}

	public function test_invalid_credentials_fail_before_any_request(): void {
		$http    = new Recording_Test_Http_Client( self::reply( 204 ) );
		$outcome = ( new Mailchimp_Test_Gateway( $http ) )->send_test( array( 'api_key' => 'nope' ), 'mc0042', Test_Delivery::create( array( 'qa@example.com' ), 'html' ) );

		self::assertSame( Action_Outcome::FAILED, $outcome->status() );
		self::assertSame( Provider_Error_Category::VALIDATION, $outcome->error()?->category() );
		self::assertSame( array(), $http->requests );
	}
}
