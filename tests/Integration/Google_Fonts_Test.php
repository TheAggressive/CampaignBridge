<?php
/**
 * Google Fonts integration boundary tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Core\Storage;
use CampaignBridge\Services\Email\Google_Fonts;
use CampaignBridge\Tests\Helpers\Test_Case;

final class Google_Fonts_Test extends Test_Case {
	private const NEW_FAMILY       = 'Campaign Bridge Sans';
	private const INVALID_FAMILY   = 'Definitely Not A Google Font';
	private ?\Closure $http_filter = null;

	public function tearDown(): void {
		if ( null !== $this->http_filter ) {
			remove_filter( 'pre_http_request', $this->http_filter );
		}
		Storage::delete_transient( 'google_font_valid_' . hash( 'sha256', strtolower( self::NEW_FAMILY ) ) );
		Storage::delete_transient( 'google_font_valid_' . hash( 'sha256', strtolower( self::INVALID_FAMILY ) ) );
		remove_filter( 'campaignbridge_external_google_fonts_enabled', '__return_false' );
		parent::tearDown();
	}

	public function test_searches_the_bundled_catalogue_without_a_remote_request(): void {
		$this->http_filter = static function (): mixed {
			self::fail( 'Bundled catalogue searches must not make a remote request.' );
		};
		add_filter( 'pre_http_request', $this->http_filter );

		$service = new Google_Fonts();
		self::assertSame( 'Roboto', $service->search( 'Roboto' )[0]['family'] ?? null );

		$font = $service->resolve( 'Roboto' );
		self::assertFalse( is_wp_error( $font ) );
		self::assertSame( 'custom', $font['slug'] ?? null );
		self::assertSame( 'https://fonts.googleapis.com/css2?family=Roboto&display=swap', $font['url'] ?? null );
	}

	public function test_validates_an_exact_new_family_through_the_bounded_css_endpoint(): void {
		$this->http_filter = static function ( mixed $preempt, array $args, string $url ): mixed {
			self::assertSame( 'https://fonts.googleapis.com/css2?family=Campaign+Bridge+Sans&display=swap', $url );
			self::assertSame( false, $args['campaignbridge_retry'] ?? false );
			self::assertSame( 3, $args['timeout'] ?? null );
			self::assertSame( 65536, $args['limit_response_size'] ?? null );
			self::assertArrayNotHasKey( 'X-Goog-Api-Key', $args['headers'] ?? array() );
			return self::response( '@font-face { font-family: "Campaign Bridge Sans"; }' );
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		$font = ( new Google_Fonts() )->resolve( self::NEW_FAMILY );
		self::assertFalse( is_wp_error( $font ) );
		self::assertSame( self::NEW_FAMILY, $font['name'] ?? null );
	}

	public function test_caches_an_invalid_exact_family(): void {
		$requests          = 0;
		$this->http_filter = static function () use ( &$requests ): mixed {
			++$requests;
			return self::response( '400: Missing font family' );
		};
		add_filter( 'pre_http_request', $this->http_filter );

		$service = new Google_Fonts();
		self::assertTrue( is_wp_error( $service->resolve( self::INVALID_FAMILY ) ) );
		self::assertTrue( is_wp_error( $service->resolve( self::INVALID_FAMILY ) ) );
		self::assertSame( 1, $requests );
	}

	public function test_site_policy_can_disable_external_google_fonts(): void {
		add_filter( 'campaignbridge_external_google_fonts_enabled', '__return_false' );

		$result = ( new Google_Fonts() )->resolve( 'Roboto' );
		self::assertTrue( is_wp_error( $result ) );
		self::assertSame( 'external_google_fonts_disabled', $result->get_error_code() );
	}

	/**
	 * Build a WordPress HTTP response fixture.
	 *
	 * @param string $body Response body.
	 */
	private static function response( string $body ): array {
		return array(
			'headers'  => array( 'content-type' => 'text/css' ),
			'body'     => $body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
