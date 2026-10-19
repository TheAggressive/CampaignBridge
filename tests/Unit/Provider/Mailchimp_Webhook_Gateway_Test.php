<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Mailchimp webhook gateway tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Provider;

use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Webhook\Webhook_Created;
use CampaignBridge\Providers\Mailchimp_Webhook_Gateway;
use WP_UnitTestCase;

/** Proves webhooks are created signed and once, found by URL, and removed idempotently, against the recorded exchange. */
final class Mailchimp_Webhook_Gateway_Test extends WP_UnitTestCase {
	private const URL = 'https://example.com/wp-json/campaignbridge/v1/webhooks/mailchimp';

	/** @var array<string, mixed> */
	private array $fixture;

	public function set_up(): void {
		parent::set_up();
		$this->fixture = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/Fixtures/Mailchimp/webhook-registration.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.
	}

	/** @return array<string, string> */
	private static function settings(): array {
		return array( 'api_key' => str_repeat( 'c0ffee', 5 ) . 'c0-us20' );
	}

	/** @return array<string, mixed> */
	private static function reply( int $status, string $body = '' ): array {
		return array(
			'status_code' => $status,
			'body'        => $body,
			'headers'     => array(),
		);
	}

	/** @return array<string, mixed> The recorded reply at one index, with placeholders filled. */
	private function recorded( int $index ): array {
		$exchange = $this->fixture['exchanges'][ $index ];

		return self::reply( $exchange['status'], str_replace( array( '{{url}}', '{{signing_secret}}' ), array( addcslashes( self::URL, '/' ), 'whsec-recorded-0123456789abcdefghijklmnopq' ), $exchange['body'] ) );
	}

	public function test_create_requests_only_signed_campaign_events_and_returns_the_secret_once(): void {
		$http    = new Sequenced_Http_Client( array( $this->recorded( 0 ) ) );
		$created = ( new Mailchimp_Webhook_Gateway( $http ) )->create( self::settings(), 'abc123', self::URL );

		self::assertInstanceOf( Webhook_Created::class, $created );
		self::assertSame( 'wh0042', $created->remote_id() );
		self::assertSame( 'whsec-recorded-0123456789abcdefghijklmnopq', $created->signing_secret() );

		$request = $http->requests[0];
		self::assertSame( 'POST', $request['method'] );
		self::assertSame( 'https://us20.api.mailchimp.com/3.0/lists/abc123/webhooks', $request['url'] );
		self::assertFalse( $request['args']['campaignbridge_retry'], 'A create is never replayed.' );
		$body = json_decode( $request['args']['body'], true );
		self::assertSame( self::URL, $body['url'] );
		self::assertSame( array( 'campaign' ), array_keys( array_filter( $body['events'] ) ), 'Only campaign events: subscriber events carry personal data.' );
		self::assertSame( array( 'user' => true, 'admin' => true, 'api' => true ), $body['sources'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Compact expectation.
	}

	public function test_an_unsigned_webhook_is_taken_back_down(): void {
		$http   = new Sequenced_Http_Client( array( self::reply( 200, '{"id":"wh0042","signing_enabled":false}' ), $this->recorded( 1 ) ) );
		$result = ( new Mailchimp_Webhook_Gateway( $http ) )->create( self::settings(), 'abc123', self::URL );

		self::assertInstanceOf( Provider_Error::class, $result );
		self::assertSame( 'DELETE', $http->requests[1]['method'] );
		self::assertStringEndsWith( '/lists/abc123/webhooks/wh0042', $http->requests[1]['url'] );
	}

	public function test_a_refused_url_is_explained_without_provider_detail(): void {
		$http   = new Sequenced_Http_Client( array( self::reply( 400, '{"title":"Invalid Resource","detail":"The resource submitted could not be validated."}' ) ) );
		$result = ( new Mailchimp_Webhook_Gateway( $http ) )->create( self::settings(), 'abc123', self::URL );

		self::assertInstanceOf( Provider_Error::class, $result );
		self::assertSame( 'mailchimp_webhook_url_rejected', $result->code() );
		self::assertStringNotContainsString( 'Invalid Resource', $result->message() );
	}

	public function test_a_timed_out_create_is_reported_not_replayed(): void {
		$http   = new Sequenced_Http_Client( array( new \WP_Error( 'http_request_failed', 'Operation timed out after 20001 milliseconds' ) ) );
		$result = ( new Mailchimp_Webhook_Gateway( $http ) )->create( self::settings(), 'abc123', self::URL );

		self::assertInstanceOf( Provider_Error::class, $result );
		self::assertSame( Provider_Error_Category::TIMEOUT, $result->category() );
		self::assertCount( 1, $http->requests );
	}

	public function test_find_returns_only_webhooks_pointing_at_this_site(): void {
		$http  = new Sequenced_Http_Client( array( self::reply( 200, '{"webhooks":[{"id":"wh1","url":"' . addcslashes( self::URL, '/' ) . '"},{"id":"wh2","url":"https://elsewhere.example/hook"},{"id":"wh3","url":"' . addcslashes( self::URL, '/' ) . '"}]}' ) ) );
		$found = ( new Mailchimp_Webhook_Gateway( $http ) )->find( self::settings(), 'abc123', self::URL );

		self::assertSame( array( 'wh1', 'wh3' ), $found );
		self::assertTrue( $http->requests[0]['args']['campaignbridge_retry'], 'Listing is safe to repeat.' );
		self::assertSame( array(), ( new Mailchimp_Webhook_Gateway( new Sequenced_Http_Client( array( $this->recorded( 2 ) ) ) ) )->find( self::settings(), 'abc123', self::URL ) );
	}

	public function test_remove_treats_a_missing_webhook_as_removed(): void {
		$gateway = new Mailchimp_Webhook_Gateway( new Sequenced_Http_Client( array( $this->recorded( 1 ), self::reply( 404, '{"title":"Resource Not Found"}' ), self::reply( 503 ) ) ) );

		self::assertNull( $gateway->remove( self::settings(), 'abc123', 'wh0042' ) );
		self::assertNull( $gateway->remove( self::settings(), 'abc123', 'wh0042' ), 'Already gone counts as removed.' );
		self::assertSame( Provider_Error_Category::PROVIDER_ERROR, $gateway->remove( self::settings(), 'abc123', 'wh0042' )?->category() );
	}

	public function test_an_invalid_key_never_reaches_the_network(): void {
		$http    = new Sequenced_Http_Client( array() );
		$gateway = new Mailchimp_Webhook_Gateway( $http );

		self::assertInstanceOf( Provider_Error::class, $gateway->create( array( 'api_key' => 'nope' ), 'abc123', self::URL ) );
		self::assertInstanceOf( Provider_Error::class, $gateway->find( array(), 'abc123', self::URL ) );
		self::assertNotNull( $gateway->remove( array(), 'abc123', 'wh0042' ) );
		self::assertSame( array(), $http->requests );
	}
}
