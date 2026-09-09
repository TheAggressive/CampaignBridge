<?php
/**
 * Google Fonts integration boundary tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Services\Email\Google_Fonts;
use CampaignBridge\Tests\Helpers\Test_Case;

final class Google_Fonts_Test extends Test_Case {
	private const CACHE_KEY        = 'campaignbridge_google_fonts_catalogue_v1';
	private ?\Closure $http_filter = null;

	public function setUp(): void {
		parent::setUp();
		delete_transient( self::CACHE_KEY );
		add_filter( 'campaignbridge_google_fonts_api_key', array( $this, 'api_key' ) );
	}

	public function tearDown(): void {
		remove_filter( 'campaignbridge_google_fonts_api_key', array( $this, 'api_key' ) );
		if ( null !== $this->http_filter ) {
			remove_filter( 'pre_http_request', $this->http_filter );
		}
		delete_transient( self::CACHE_KEY );
		parent::tearDown();
	}

	public function api_key(): string {
		return 'secret-test-key';
	}

	public function test_searches_catalogue_and_snapshots_safe_google_css(): void {
		$this->http_filter = static function ( mixed $preempt, array $args, string $url ): mixed {
			if ( str_starts_with( $url, 'https://www.googleapis.com/webfonts/' ) ) {
				self::assertStringNotContainsString( 'secret-test-key', $url );
				self::assertSame( 'secret-test-key', $args['headers']['X-Goog-Api-Key'] ?? null );
				return self::response(
					wp_json_encode(
						array(
							'items' => array(
								array(
									'family'   => 'Example Sans',
									'category' => 'sans-serif',
									'variants' => array( 'regular', '600', '700italic' ),
								),
							),
						) 
					) 
				);
			}

				self::assertStringStartsWith( 'https://fonts.googleapis.com/css2?family=Example+Sans:wght@400;600', $url );
				return self::response( "@font-face { font-family: 'Example Sans'; src: url(https://fonts.gstatic.com/s/example/v1/example.woff2) format('woff2'); }" );
		};
		add_filter(
			'pre_http_request',
			$this->http_filter,
			10,
			3
		);

		$service = new Google_Fonts();
		self::assertSame( 'Example Sans', $service->search( 'example' )[0]['family'] ?? null );

		$font = $service->resolve( 'Example Sans' );
		self::assertFalse( is_wp_error( $font ) );
		self::assertSame( array( 400, 600 ), $font['weights'] ?? null );
		self::assertSame( 'custom', $font['slug'] ?? null );
	}

	public function test_rejects_non_google_font_assets(): void {
		set_transient(
			self::CACHE_KEY,
			array(
				array(
					'family'   => 'Unsafe Sans',
					'category' => 'sans-serif',
					'variants' => array( 'regular' ),
				),
			) 
		);
		$this->http_filter = static fn(): array => self::response( "@font-face { font-family: 'Unsafe Sans'; src: url(https://evil.example/font.woff2); }" );
		add_filter( 'pre_http_request', $this->http_filter );

		self::assertWPError( ( new Google_Fonts() )->resolve( 'Unsafe Sans' ) );
	}

	/**
	 * Build a WordPress HTTP response fixture.
	 *
	 * @param string $body Response body.
	 */
	private static function response( string $body ): array {
		return array(
			'headers'  => array(),
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
