<?php
/**
 * Renderer support URL policy unit tests.
 *
 * Verifies the shared absolute-URL gate: it accepts HTTP and HTTPS links (so
 * previews and sends work on HTTP development sites) while rejecting relative
 * and non-HTTP(S) schemes.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Services\Email\Renderer\Renderer_Support;
use PHPUnit\Framework\TestCase;

final class Renderer_Support_Test extends TestCase {
	public function test_accepts_https_and_http_urls_including_case_variants(): void {
		self::assertSame( 'https://example.com/a', Renderer_Support::https_url( 'https://example.com/a' ) );
		self::assertSame( 'http://example.com/a', Renderer_Support::https_url( 'http://example.com/a' ) );
		self::assertSame( 'HTTPS://EXAMPLE.COM/A', Renderer_Support::https_url( 'HTTPS://EXAMPLE.COM/A' ) );
		self::assertSame( 'Http://example.com/a?b=1#c', Renderer_Support::https_url( 'Http://example.com/a?b=1#c' ) );
	}

	/**
	 * @dataProvider dangerousSchemes
	 */
	public function test_rejects_dangerous_and_non_http_schemes( string $value ): void {
		self::assertNull( Renderer_Support::https_url( $value ), "Expected rejection of: $value" );
	}

	public function test_rejects_relative_and_malformed_urls(): void {
		self::assertNull( Renderer_Support::https_url( '/relative/path' ) );
		self::assertNull( Renderer_Support::https_url( 'example.com/a' ) );
		self::assertNull( Renderer_Support::https_url( '' ) );
		self::assertNull( Renderer_Support::https_url( 'not a url' ) );
	}

	public function test_rejects_non_string_input(): void {
		self::assertNull( Renderer_Support::https_url( null ) );
		self::assertNull( Renderer_Support::https_url( 123 ) );
		self::assertNull( Renderer_Support::https_url( array( 'https://example.com/a' ) ) );
		self::assertNull( Renderer_Support::https_url( true ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function dangerousSchemes(): array {
		return array(
			'javascript'    => array( 'javascript:alert(1)' ),
			'javascript-mixed-case' => array( 'JavaScript:alert(1)' ),
			'data'          => array( 'data:text/html,<script>alert(1)</script>' ),
			'vbscript'      => array( 'vbscript:msgbox(1)' ),
			'mailto'        => array( 'mailto:example@example.com' ),
			'ftp'           => array( 'ftp://example.com/file' ),
		);
	}
}
