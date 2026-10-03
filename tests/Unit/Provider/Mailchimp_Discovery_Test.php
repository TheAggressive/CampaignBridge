<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.VariableComment,Generic.Files.OneObjectStructurePerFile
/**
 * Mailchimp discovery adapter tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Provider;

use CampaignBridge\Core\Http_Client_Interface;
use CampaignBridge\Core\Http_Origin;
use CampaignBridge\Domain\Campaign\Provider_Error;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Domain\Provider\Discovery_Batch;
use CampaignBridge\Providers\Mailchimp_Discovery;
use WP_UnitTestCase;

/** Records requests and replays one scripted response. */
final class Scripted_Http_Client implements Http_Client_Interface {
	/** @var array<int, array{url: string, args: array<string, mixed>}> */
	public array $requests = array();

	/** @param array<string, mixed>|\WP_Error $response Scripted response. */
	public function __construct( private readonly array|\WP_Error $response ) {}

	public function get( string $url, array $args = array() ) {
		if ( ! ( $args['campaignbridge_origin'] ?? null ) instanceof Http_Origin || ! $args['campaignbridge_origin']->allows( $url ) ) {
			throw new \LogicException( 'Every Mailchimp request must declare a trusted origin that allows its URL.' );
		}
		$this->requests[] = array(
			'url'  => $url,
			'args' => $args,
		);

		return $this->response;
	}

	public function post( string $url, array $args = array() ) {
		throw new \LogicException( 'Discovery must never write to Mailchimp.' );
	}

	public function put( string $url, array $args = array() ) {
		throw new \LogicException( 'Discovery must never write to Mailchimp.' );
	}

	public function patch( string $url, array $args = array() ) {
		throw new \LogicException( 'Discovery must never write to Mailchimp.' );
	}

	public function delete( string $url, array $args = array() ) {
		throw new \LogicException( 'Discovery must never write to Mailchimp.' );
	}
}

/** Proves Mailchimp responses are bounded, normalized, and never leak raw payloads. */
final class Mailchimp_Discovery_Test extends WP_UnitTestCase {
	/**
	 * A syntactically valid, fabricated key built at runtime so no literal
	 * credential-shaped string is committed.
	 */
	private static function api_key(): string {
		return str_repeat( 'c0ffee', 5 ) . 'c0' . '-us20';
	}

	/** @return array<string, mixed> */
	private static function ok( array $body ): array {
		return array(
			'status_code' => 200,
			'body'        => (string) wp_json_encode( $body ),
			'headers'     => array(),
		);
	}

	public function test_audiences_request_only_kept_fields_and_normalize_defaults(): void {
		$http  = new Scripted_Http_Client(
			self::ok(
				array(
					'lists'       => array(
						array(
							'id'                  => 'abc123',
							'name'                => 'Customers',
							'stats'               => array(
								'member_count'      => 1200,
								'unsubscribe_count' => 4,
							),
							'campaign_defaults'   => array(
								'from_name'  => 'Example Shop',
								'from_email' => 'News@Example.com',
								'subject'    => 'Hidden default subject',
							),
							'contact'             => array( 'address1' => '1 Private Lane' ),
							'permission_reminder' => 'You signed up at our store.',
							'members'             => array( array( 'email_address' => 'subscriber@example.net' ) ),
							'api_key'             => self::api_key(),
						),
						array(
							'id'                => 'def456',
							'name'              => 'Newsletter',
							'campaign_defaults' => array(
								'from_name'  => 'Example Shop',
								'from_email' => '',
							),
						),
					),
					'total_items' => 2,
				)
			)
		);
		$batch = ( new Mailchimp_Discovery( $http ) )->discover_audiences( array( 'api_key' => self::api_key() ) );

		self::assertInstanceOf( Discovery_Batch::class, $batch );
		self::assertSame(
			'https://us20.api.mailchimp.com/3.0/lists?count=1000&offset=0&fields=lists.id%2Clists.name%2Clists.stats.member_count%2Clists.campaign_defaults.from_name%2Clists.campaign_defaults.from_email%2Ctotal_items',
			$http->requests[0]['url']
		);
		self::assertSame( 'Bearer ' . self::api_key(), $http->requests[0]['args']['headers']['Authorization'] );
		self::assertTrue( $batch->is_complete() );

		$items = array_map( static fn ( $item ): array => $item->to_array(), $batch->items() );
		self::assertSame(
			array(
				array(
					'id'             => 'abc123',
					'name'           => 'Customers',
					'member_count'   => 1200,
					'default_sender' => array(
						'from_name'  => 'Example Shop',
						'from_email' => 'news@example.com',
					),
				),
				array(
					'id'             => 'def456',
					'name'           => 'Newsletter',
					'member_count'   => null,
					'default_sender' => null,
				),
			),
			$items
		);
		$json = (string) wp_json_encode( $items );
		foreach ( array( self::api_key(), 'Private Lane', 'subscriber@example.net', 'signed up', 'Hidden default subject', 'unsubscribe' ) as $leak ) {
			self::assertStringNotContainsString( $leak, $json );
		}
	}

	public function test_merge_fields_and_segments_are_audience_scoped_and_normalized(): void {
		$fields = new Scripted_Http_Client(
			self::ok(
				array(
					'merge_fields' => array(
						array(
							'tag'           => 'FNAME',
							'name'          => 'First Name',
							'type'          => 'text',
							'required'      => false,
							'default_value' => 'Friend',
						),
						array(
							'tag'      => 'BIRTHDAY',
							'name'     => 'Birthday',
							'type'     => 'birthday',
							'required' => true,
						),
					),
					'total_items'  => 2,
				)
			)
		);
		$batch  = ( new Mailchimp_Discovery( $fields ) )->discover_merge_fields( array( 'api_key' => self::api_key() ), 'abc123' );
		self::assertInstanceOf( Discovery_Batch::class, $batch );
		self::assertStringStartsWith( 'https://us20.api.mailchimp.com/3.0/lists/abc123/merge-fields?count=1000&offset=0&fields=', $fields->requests[0]['url'] );
		self::assertSame(
			array(
				array(
					'tag'      => 'FNAME',
					'name'     => 'First Name',
					'type'     => 'text',
					'required' => false,
				),
				array(
					'tag'      => 'BIRTHDAY',
					'name'     => 'Birthday',
					'type'     => 'birthday',
					'required' => true,
				),
			),
			array_map( static fn ( $item ): array => $item->to_array(), $batch->items() )
		);

		$segments = new Scripted_Http_Client(
			self::ok(
				array(
					'segments'    => array(
						array(
							'id'           => 101,
							'name'         => 'VIP',
							'type'         => 'static',
							'member_count' => 7,
							'options'      => array( 'conditions' => array() ),
						),
						array(
							'id'           => 102,
							'name'         => 'Recent buyers',
							'type'         => 'saved',
							'member_count' => 30,
						),
					),
					'total_items' => 2,
				)
			)
		);
		$batch    = ( new Mailchimp_Discovery( $segments ) )->discover_segments( array( 'api_key' => self::api_key() ), 'abc123' );
		self::assertInstanceOf( Discovery_Batch::class, $batch );
		self::assertStringStartsWith( 'https://us20.api.mailchimp.com/3.0/lists/abc123/segments?', $segments->requests[0]['url'] );
		self::assertSame(
			array( array( '101', 'tag' ), array( '102', 'segment' ) ),
			array_map( static fn ( $item ): array => array( $item->id(), $item->kind() ), $batch->items() )
		);
	}

	public function test_truncated_or_partly_invalid_lists_are_reported_incomplete(): void {
		$truncated = ( new Mailchimp_Discovery(
			new Scripted_Http_Client(
				self::ok(
					array(
						'lists'       => array(
							array(
								'id'   => 'abc123',
								'name' => 'Customers',
							),
						),
						'total_items' => 5000,
					)
				)
			)
		) )->discover_audiences( array( 'api_key' => self::api_key() ) );
		self::assertInstanceOf( Discovery_Batch::class, $truncated );
		self::assertFalse( $truncated->is_complete() );

		$invalid = ( new Mailchimp_Discovery(
			new Scripted_Http_Client(
				self::ok(
					array(
						'lists'       => array(
							array(
								'id'   => 'abc123',
								'name' => 'Customers',
							),
							array(
								'id'   => '../escape',
								'name' => 'Broken',
							),
							'not an object',
						),
						'total_items' => 3,
					)
				)
			)
		) )->discover_audiences( array( 'api_key' => self::api_key() ) );
		self::assertInstanceOf( Discovery_Batch::class, $invalid );
		self::assertCount( 1, $invalid->items() );
		self::assertFalse( $invalid->is_complete() );
	}

	/** @return array<string, array{array<string, mixed>|\WP_Error, string, bool}> */
	public static function failures(): array {
		$body = '{"title":"API Key Invalid","detail":"Your API key may be invalid ' . self::api_key() . '","instance":"x"}';

		return array(
			'unauthorized'     => array(
				array(
					'status_code' => 401,
					'body'        => $body,
				),
				Provider_Error_Category::AUTHENTICATION,
				false,
			),
			'forbidden'        => array(
				array(
					'status_code' => 403,
					'body'        => $body,
				),
				Provider_Error_Category::AUTHORIZATION,
				false,
			),
			'missing audience' => array(
				array(
					'status_code' => 404,
					'body'        => $body,
				),
				Provider_Error_Category::NOT_FOUND,
				false,
			),
			'rate limited'     => array(
				array(
					'status_code' => 429,
					'body'        => $body,
				),
				Provider_Error_Category::RATE_LIMITED,
				true,
			),
			'server error'     => array(
				array(
					'status_code' => 503,
					'body'        => $body,
				),
				Provider_Error_Category::PROVIDER_ERROR,
				false,
			),
			'timeout'          => array( new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ), Provider_Error_Category::TIMEOUT, true ),
			'network'          => array( new \WP_Error( 'http_request_failed', 'Could not resolve host' ), Provider_Error_Category::NETWORK, true ),
			'malformed body'   => array(
				array(
					'status_code' => 200,
					'body'        => '<html>maintenance</html>',
				),
				Provider_Error_Category::UNKNOWN,
				true,
			),
			'wrong shape'      => array( self::ok( array( 'lists' => 'nope' ) ), Provider_Error_Category::UNKNOWN, true ),
		);
	}

	/**
	 * @dataProvider failures
	 * @param array<string, mixed>|\WP_Error $response Scripted response.
	 */
	public function test_failures_are_normalized_without_provider_detail( array|\WP_Error $response, string $category, bool $retryable ): void {
		$error = ( new Mailchimp_Discovery( new Scripted_Http_Client( $response ) ) )->discover_audiences( array( 'api_key' => self::api_key() ) );

		self::assertInstanceOf( Provider_Error::class, $error );
		self::assertSame( $category, $error->category() );
		self::assertSame( $retryable, $error->is_retryable() );
		self::assertSame( 'mailchimp', $error->provider() );
		$exposed = (string) wp_json_encode( $error->to_array() );
		foreach ( array( 'API Key Invalid', self::api_key(), 'maintenance', 'cURL', 'resolve host' ) as $leak ) {
			self::assertStringNotContainsString( $leak, $exposed );
		}
	}

	public function test_invalid_credentials_fail_before_any_request(): void {
		$http      = new Scripted_Http_Client( self::ok( array() ) );
		$discovery = new Mailchimp_Discovery( $http );

		$error = $discovery->discover_audiences( array( 'api_key' => 'not-a-mailchimp-key' ) );
		self::assertInstanceOf( Provider_Error::class, $error );
		self::assertSame( Provider_Error_Category::VALIDATION, $error->category() );
		self::assertNull( $discovery->account_key( array() ) );
		self::assertSame( array(), $http->requests );

		$this->expectException( \InvalidArgumentException::class );
		$discovery->discover_segments( array( 'api_key' => self::api_key() ), '../../ping' );
	}

	public function test_account_key_is_stable_and_does_not_reveal_the_credential(): void {
		$discovery = new Mailchimp_Discovery( new Scripted_Http_Client( self::ok( array() ) ) );
		$key       = $discovery->account_key( array( 'api_key' => self::api_key() ) );

		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', (string) $key );
		self::assertSame( $key, $discovery->account_key( array( 'api_key' => self::api_key() ) ) );
		self::assertNotSame( $key, $discovery->account_key( array( 'api_key' => str_repeat( 'b', 32 ) . '-us20' ) ) );
		self::assertStringNotContainsString( 'c0ffeec0ffee', (string) $key );
	}
}
