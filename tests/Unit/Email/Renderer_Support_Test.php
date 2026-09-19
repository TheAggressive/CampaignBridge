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

	public function test_link_url_accepts_literal_urls_and_exact_provider_url_tokens(): void {
		self::assertSame( 'https://example.com/a', Renderer_Support::link_url( 'https://example.com/a' ) );
		self::assertSame( '{{cb:campaign.unsubscribe_url}}', Renderer_Support::link_url( '{{cb:campaign.unsubscribe_url}}' ) );
		self::assertSame( '{{cb:campaign.view_online_url}}', Renderer_Support::link_url( '{{cb:campaign.view_online_url}}' ) );
	}

	/**
	 * @dataProvider rejectedLinkUrls
	 */
	public function test_link_url_rejects_unsafe_or_interpolated_destinations( mixed $value ): void {
		self::assertNull( Renderer_Support::link_url( $value ) );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function rejectedLinkUrls(): array {
		return array(
			'javascript'          => array( 'javascript:alert(1)' ),
			'data'                => array( 'data:text/html,x' ),
			'relative'            => array( '/unsubscribe' ),
			'non-string'          => array( null ),
			'string token'        => array( '{{cb:subscriber.first_name}}' ),
			'local string token'  => array( '{{cb:organization.name}}' ),
			'unknown token'       => array( '{{cb:campaign.archive_url}}' ),
			'query interpolation' => array( 'https://example.com/?email={{cb:subscriber.email}}' ),
			'path interpolation'  => array( 'https://example.com/{{cb:campaign.unsubscribe_url}}' ),
			'javascript token'    => array( 'javascript:{{cb:campaign.unsubscribe_url}}' ),
			'prefixed token'      => array( 'prefix-{{cb:campaign.unsubscribe_url}}' ),
			'foreign braces'      => array( 'https://example.com/{{FNAME}}' ),
		);
	}

	public function test_https_url_contract_is_unchanged_for_non_link_urls(): void {
		self::assertNull( Renderer_Support::https_url( '{{cb:campaign.unsubscribe_url}}' ) );
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
