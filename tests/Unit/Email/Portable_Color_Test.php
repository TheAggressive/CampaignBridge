<?php
/**
 * Portable colour conversion tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Portable_Color;
use PHPUnit\Framework\TestCase;

final class Portable_Color_Test extends TestCase {
	public function test_keeps_six_digit_hex(): void {
		self::assertSame( '#1a6dcc', Portable_Color::from( '#1A6DCC' ) );
		self::assertSame( '#ff5500', Portable_Color::from( '#f50' ) );
	}

	public function test_converts_oklch_white_and_black(): void {
		self::assertSame( '#ffffff', Portable_Color::from( 'oklch(100% 0 0)' ) );
		self::assertSame( '#000000', Portable_Color::from( 'oklch(0% 0 0)' ) );
	}

	public function test_converts_laao_oklch_tokens(): void {
		$black      = Portable_Color::from( 'oklch(6.72% 0 0)' );
		$red        = Portable_Color::from( 'oklch(57.71% 0.2152 27.33)' );
		$light_gray = Portable_Color::from( 'oklch(91.887% 0 0)' );

		self::assertNotNull( $black );
		self::assertNotNull( $red );
		self::assertNotNull( $light_gray );
		self::assertLessThan( 40, hexdec( substr( $black, 1, 2 ) ) );
		self::assertGreaterThan( 200, hexdec( substr( $light_gray, 1, 2 ) ) );
		self::assertGreaterThan( hexdec( substr( $red, 3, 2 ) ), hexdec( substr( $red, 1, 2 ) ) );
		self::assertGreaterThan( hexdec( substr( $red, 5, 2 ) ), hexdec( substr( $red, 1, 2 ) ) );
		self::assertNotSame( '#0693e3', $red );
	}

	public function test_converts_rgb_and_hsl(): void {
		self::assertSame( '#1a6dcc', Portable_Color::from( 'rgb(26, 109, 204)' ) );
		self::assertSame( '#ffffff', Portable_Color::from( 'hsl(0, 0%, 100%)' ) );
	}

	public function test_rejects_transparent_and_unportable_values(): void {
		self::assertNull( Portable_Color::from( 'oklch(0% 0 0 / 0)' ) );
		self::assertNull( Portable_Color::from( 'rgba(0, 0, 0, 0)' ) );
		self::assertNull( Portable_Color::from( 'color-mix(in srgb, red 50%, blue)' ) );
		self::assertNull( Portable_Color::from( 'var(--wp--custom--color--red)' ) );
		self::assertNull( Portable_Color::from( 12 ) );
	}
}
