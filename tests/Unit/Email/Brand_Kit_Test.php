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
		self::assertNull( $kit->logo() );

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

	public function test_normalizes_a_version_one_color_kit_to_the_current_version(): void {
		$kit = Brand_Kit::from_array(
			array(
				'version' => 1,
				'source'  => Brand_Kit::SOURCE_CUSTOM,
				'colors'  => array( Brand_Kit::SLOT_BRAND => '#123456' ),
			)
		);

		self::assertSame( Brand_Kit::VERSION, $kit->to_array()['version'] );
		self::assertSame( Brand_Kit::FONT_DEFAULTS, $kit->fonts() );
		self::assertNull( $kit->logo() );
	}

	public function test_logo_round_trips_as_a_frozen_asset(): void {
		$logo = array(
			'url'      => 'https://cdn.example.com/logo.png',
			'alt'      => 'Example & Co.',
			'width'    => 800,
			'height'   => 240,
			'link_url' => 'https://example.com/',
		);
		$kit  = Brand_Kit::from_colors( array(), Brand_Kit::SOURCE_THEME, 'theme', null, null, $logo );

		self::assertSame( $logo, $kit->logo() );
		self::assertSame( $kit->to_array(), Brand_Kit::from_array( $kit->to_array() )->to_array() );
	}

	/** @dataProvider invalid_logos */
	public function test_rejects_an_invalid_logo( array $logo ): void {
		$this->expectException( \InvalidArgumentException::class );
		Brand_Kit::from_colors( array(), Brand_Kit::SOURCE_CUSTOM, null, null, null, $logo );
	}

	/** @return array<string, array{array<string, mixed>}> */
	public static function invalid_logos(): array {
		$valid = array(
			'url'      => 'https://cdn.example.com/logo.png',
			'alt'      => 'Example',
			'width'    => 800,
			'height'   => 240,
			'link_url' => 'https://example.com/',
		);

		return array(
			'http asset'       => array( array_replace( $valid, array( 'url' => 'http://cdn.example.com/logo.png' ) ) ),
			'credentialed URL' => array( array_replace( $valid, array( 'url' => 'https://user:pass@cdn.example.com/logo.png' ) ) ),
			'empty alt'        => array( array_replace( $valid, array( 'alt' => '' ) ) ),
			'markup alt'       => array( array_replace( $valid, array( 'alt' => '<b>Example</b>' ) ) ),
			'control alt'      => array( array_replace( $valid, array( 'alt' => "Example\nLogo" ) ) ),
			'oversize width'   => array( array_replace( $valid, array( 'width' => Brand_Kit::MAX_LOGO_DIMENSION + 1 ) ) ),
			'unknown field'    => array( $valid + array( 'attachment_id' => 7 ) ),
		);
	}

	public function test_rejects_a_future_brand_kit_version(): void {
		$this->expectException( \InvalidArgumentException::class );
		Brand_Kit::from_array( array( 'version' => Brand_Kit::VERSION + 1 ) );
	}

	public function test_rejects_a_malformed_version_three_logo_record(): void {
		$this->expectException( \InvalidArgumentException::class );
		Brand_Kit::from_array( array( 'version' => 3, 'logo' => 'https://example.com/logo.png' ) );
	}

	public function test_custom_google_font_round_trips_with_a_valid_css2_url(): void {
		$custom = array(
			'name'    => 'Example Sans',
			'family'  => 'Example Sans,Arial,Helvetica,sans-serif',
			'weights' => array( 700, 400, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Example+Sans:wght@400;700&display=swap',
		);
		$kit    = Brand_Kit::from_colors( array(), Brand_Kit::SOURCE_CUSTOM, null, array( 'heading' => 'custom' ), $custom );

		self::assertSame( 'custom', $kit->font( 'heading' ) );
		self::assertSame( array( 400, 700 ), $kit->custom_font()['weights'] ?? null );
		self::assertSame( $kit->to_array(), Brand_Kit::from_array( $kit->to_array() )->to_array() );

		$custom['url'] = 'https://evil.example/font.css';
		$unsafe        = Brand_Kit::from_colors( array(), Brand_Kit::SOURCE_CUSTOM, null, array( 'heading' => 'custom' ), $custom );
		self::assertNull( $unsafe->custom_font() );
		self::assertSame( 'arial', $unsafe->font( 'heading' ) );
	}
}
