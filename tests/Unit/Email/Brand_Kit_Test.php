<?php
/**
 * Brand kit value object tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Design_Presets;
use PHPUnit\Framework\TestCase;

final class Brand_Kit_Test extends TestCase {
	public function test_defaults_match_the_design_preset_table(): void {
		$kit = Brand_Kit::defaults();

		self::assertSame( Brand_Kit::SOURCE_DEFAULTS, $kit->source() );
		self::assertNull( $kit->theme_fingerprint() );

		foreach ( Design_Presets::colors() as $preset ) {
			self::assertSame( $preset['color'], $kit->color( $preset['slug'] ) );
		}
	}

	public function test_overlays_only_the_slots_that_were_provided(): void {
		$kit = Brand_Kit::from_colors(
			array(
				Brand_Kit::SLOT_BRAND => '#ff5500',
			),
			Brand_Kit::SOURCE_CUSTOM
		);

		self::assertSame( '#ff5500', $kit->color( Brand_Kit::SLOT_BRAND ) );
		self::assertSame( Design_Presets::color( Brand_Kit::SLOT_TEXT ), $kit->color( Brand_Kit::SLOT_TEXT ) );
		self::assertSame( Brand_Kit::SOURCE_CUSTOM, $kit->source() );
	}

	public function test_expands_three_digit_hex(): void {
		$kit = Brand_Kit::from_colors( array( Brand_Kit::SLOT_BRAND => '#f50' ) );

		self::assertSame( '#ff5500', $kit->color( Brand_Kit::SLOT_BRAND ) );
	}

	public function test_rejects_a_colour_that_is_not_portable(): void {
		$this->expectException( \InvalidArgumentException::class );

		Brand_Kit::from_colors( array( Brand_Kit::SLOT_BRAND => 'oklch(0.7 0.1 200)' ) );
	}

	public function test_round_trips_through_the_stored_array(): void {
		$original = Brand_Kit::from_colors(
			array( Brand_Kit::SLOT_BRAND => '#112233' ),
			Brand_Kit::SOURCE_THEME,
			'fingerprint-1'
		);

		$restored = Brand_Kit::from_array( $original->to_array() );

		self::assertSame( $original->to_array(), $restored->to_array() );
		self::assertSame( 'fingerprint-1', $restored->theme_fingerprint() );
	}

	public function test_editor_palette_keeps_the_stable_slot_order(): void {
		$slugs = array_map(
			static fn( array $preset ): string => $preset['slug'],
			Brand_Kit::defaults()->colors()
		);

		self::assertSame( Brand_Kit::SLOTS, $slugs );
	}
}
