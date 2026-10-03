<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort
/**
 * Outbound HTTP origin policy tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Security;

use CampaignBridge\Core\Http_Client;
use CampaignBridge\Core\Http_Origin;
use CampaignBridge\Domain\Campaign\Provider_Error_Category;
use CampaignBridge\Providers\Mailchimp_Errors;
use CampaignBridge\Providers\Mailchimp_Provider;
use WP_UnitTestCase;

/** Proves credentials and requests can reach only the origin an integration fixes in code. */
final class Outbound_Origin_Test extends WP_UnitTestCase {
	/** @var array<int, array{url: string, args: array<string, mixed>}> */
	private array $sent = array();

	private ?\Closure $transport = null;

	public function setUp(): void {
		parent::setUp();
		$this->sent      = array();
		$this->transport = function ( $preempt, array $args, string $url ): array {
			$this->sent[] = array(
				'url'  => $url,
				'args' => $args,
			);

			return array(
				'headers'  => array(),
				'body'     => '{}',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
			);
		};
		add_filter( 'pre_http_request', $this->transport, 10, 3 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', $this->transport, 10 );
		parent::tearDown();
	}

	/** @return array<string, array{0: string}> */
	public static function mailchimp_urls(): array {
		return array(
			'root'          => array( 'https://us20.api.mailchimp.com/3.0/' ),
			'campaign'      => array( 'https://us1.api.mailchimp.com/3.0/campaigns/abc123/actions/test' ),
			'fields query'  => array( 'https://us20.api.mailchimp.com/3.0/campaigns/abc?fields=status,recipients.list_id' ),
			'host and path' => array( 'https://us20.api.mailchimp.com' ),
		);
	}

	/** @dataProvider mailchimp_urls */
	public function test_mailchimp_origin_allows_its_data_center_hosts( string $url ): void {
		self::assertTrue( Mailchimp_Provider::origin()->allows( $url ) );
	}

	/** @return array<string, array{0: string}> */
	public static function untrusted_urls(): array {
		return array(
			'plain http'          => array( 'http://us20.api.mailchimp.com/3.0/ping' ),
			'uppercase scheme'    => array( 'HTTPS://us20.api.mailchimp.com/3.0/ping' ),
			'protocol relative'   => array( '//us20.api.mailchimp.com/3.0/ping' ),
			'other scheme'        => array( 'ftp://us20.api.mailchimp.com/3.0/ping' ),
			'bare parent'         => array( 'https://api.mailchimp.com/3.0/ping' ),
			'nested subdomain'    => array( 'https://a.us20.api.mailchimp.com/3.0/ping' ),
			'suffix lookalike'    => array( 'https://us20.api.mailchimp.com.evil.example/3.0/ping' ),
			'prefix lookalike'    => array( 'https://us20api.mailchimp.com/3.0/ping' ),
			'label outside shape' => array( 'https://login.api.mailchimp.com/3.0/ping' ),
			'unrelated host'      => array( 'https://attacker.example/3.0/ping' ),
			'userinfo'            => array( 'https://user:pass@us20.api.mailchimp.com/3.0/ping' ),
			'userinfo redirect'   => array( 'https://us20.api.mailchimp.com@attacker.example/3.0/ping' ),
			'at in path'          => array( 'https://us20.api.mailchimp.com/3.0/@attacker.example' ),
			'explicit port'       => array( 'https://us20.api.mailchimp.com:8443/3.0/ping' ),
			'default port'        => array( 'https://us20.api.mailchimp.com:443/3.0/ping' ),
			'backslash authority' => array( 'https://attacker.example\\@us20.api.mailchimp.com/3.0/ping' ),
			'backslash path'      => array( 'https://us20.api.mailchimp.com\\attacker.example/ping' ),
			'fragment'            => array( 'https://us20.api.mailchimp.com/3.0/ping#frag' ),
			'whitespace'          => array( 'https://us20.api.mailchimp.com/3.0/ ping' ),
			'newline'             => array( "https://us20.api.mailchimp.com/3.0/ping\nHost: attacker.example" ),
			'trailing newline'    => array( "https://us20.api.mailchimp.com/3.0/ping\n" ),
			'null byte'           => array( "https://us20.api.mailchimp.com\0.attacker.example/ping" ),
			'uppercase host'      => array( 'https://US20.API.MAILCHIMP.COM/3.0/ping' ),
			'trailing dot host'   => array( 'https://us20.api.mailchimp.com./3.0/ping' ),
			'encoded host'        => array( 'https://us20%2eapi.mailchimp.com/3.0/ping' ),
			'ip literal'          => array( 'https://127.0.0.1/3.0/ping' ),
			'ipv6 literal'        => array( 'https://[::1]/3.0/ping' ),
			'empty'               => array( '' ),
		);
	}

	/** @dataProvider untrusted_urls */
	public function test_mailchimp_origin_refuses_every_other_destination( string $url ): void {
		self::assertFalse( Mailchimp_Provider::origin()->allows( $url ) );
	}

	public function test_an_exact_host_origin_allows_only_that_host(): void {
		$origin = Http_Origin::host( 'fonts.googleapis.com' );

		self::assertTrue( $origin->allows( 'https://fonts.googleapis.com/css2?family=Inter&display=swap' ) );
		self::assertFalse( $origin->allows( 'https://fonts.gstatic.com/s/inter.woff2' ) );
		self::assertFalse( $origin->allows( 'https://evil.fonts.googleapis.com/css2' ) );
		self::assertFalse( $origin->allows( 'http://fonts.googleapis.com/css2' ) );
	}

	public function test_an_origin_cannot_be_declared_from_a_malformed_host(): void {
		foreach ( array( '', 'localhost', 'Example.com', 'example.com:443', 'user@example.com', '127.0.0.1x', 'exa mple.com', '-bad.example.com' ) as $host ) {
			try {
				Http_Origin::host( $host );
				self::fail( 'Accepted a malformed trusted host: ' . $host );
			} catch ( \InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	public function test_a_request_without_a_declared_origin_never_leaves_wordpress(): void {
		$response = Http_Client::get( 'https://us20.api.mailchimp.com/3.0/ping', array( 'headers' => array( 'Authorization' => 'Bearer secret-credential' ) ) );

		self::assertWPError( $response );
		self::assertSame( Http_Client::UNTRUSTED_ORIGIN, $response->get_error_code() );
		self::assertSame( array(), $this->sent, 'The transport must not be reached.' );
	}

	public function test_a_credential_bearing_request_outside_its_origin_never_leaves_wordpress(): void {
		foreach ( array( 'post', 'get', 'put', 'patch', 'delete' ) as $method ) {
			$response = Http_Client::$method(
				'https://attacker.example/3.0/campaigns',
				array(
					'headers'               => array( 'Authorization' => 'Bearer secret-credential' ),
					'campaignbridge_origin' => Mailchimp_Provider::origin(),
				)
			);

			self::assertWPError( $response, $method );
			self::assertSame( Http_Client::UNTRUSTED_ORIGIN, $response->get_error_code(), $method );
			self::assertStringNotContainsString( 'secret-credential', $response->get_error_message() );
		}
		self::assertSame( array(), $this->sent, 'No request, and therefore no credential, may reach another origin.' );
	}

	public function test_a_request_to_its_declared_origin_is_sent_without_redirects_or_policy_arguments(): void {
		$response = Http_Client::post(
			'https://us20.api.mailchimp.com/3.0/campaigns',
			array(
				'headers'               => array( 'Authorization' => 'Bearer secret-credential' ),
				'redirection'           => 5,
				'campaignbridge_origin' => Mailchimp_Provider::origin(),
			)
		);

		self::assertIsArray( $response );
		self::assertCount( 1, $this->sent );
		self::assertSame( 'https://us20.api.mailchimp.com/3.0/campaigns', $this->sent[0]['url'] );
		self::assertSame( 0, $this->sent[0]['args']['redirection'], 'A credential-bearing call must never follow a redirect.' );
		self::assertArrayNotHasKey( 'campaignbridge_origin', $this->sent[0]['args'] );
	}

	public function test_a_redirect_response_is_returned_not_followed(): void {
		remove_filter( 'pre_http_request', $this->transport, 10 );
		$this->transport = function ( $preempt, array $args, string $url ): array {
			$this->sent[] = array(
				'url'  => $url,
				'args' => $args,
			);

			return array(
				'headers'  => array( 'location' => 'https://attacker.example/collect' ),
				'body'     => '',
				'response' => array(
					'code'    => 301,
					'message' => 'Moved Permanently',
				),
			);
		};
		add_filter( 'pre_http_request', $this->transport, 10, 3 );

		$response = Http_Client::get(
			'https://us20.api.mailchimp.com/3.0/ping',
			array(
				'headers'               => array( 'Authorization' => 'Bearer secret-credential' ),
				'campaignbridge_origin' => Mailchimp_Provider::origin(),
			)
		);

		self::assertIsArray( $response );
		self::assertSame( 301, $response['status_code'] );
		self::assertCount( 1, $this->sent, 'The redirect target must never be requested.' );
	}

	public function test_permitted_public_service_still_works_under_its_own_policy(): void {
		$response = Http_Client::get(
			'https://fonts.googleapis.com/css2?family=Inter&display=swap',
			array( 'campaignbridge_origin' => Http_Origin::host( 'fonts.googleapis.com' ) )
		);

		self::assertIsArray( $response );
		self::assertCount( 1, $this->sent );
		self::assertArrayNotHasKey( 'Authorization', $this->sent[0]['args']['headers'] );
	}

	public function test_an_origin_refusal_is_a_definite_failure_not_an_ambiguous_one(): void {
		$error = Mailchimp_Errors::from_transport( new \WP_Error( Http_Client::UNTRUSTED_ORIGIN, 'refused' ) );

		self::assertSame( Provider_Error_Category::VALIDATION, $error->category() );
		self::assertFalse( Provider_Error_Category::may_have_applied( $error->category() ) );
	}
}
