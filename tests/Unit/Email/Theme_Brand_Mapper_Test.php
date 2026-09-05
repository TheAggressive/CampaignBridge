<?php
/**
 * Theme-to-brand-kit mapping tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Design_Presets;
use CampaignBridge\Domain\Email\Theme_Brand_Mapper;
use PHPUnit\Framework\TestCase;

final class Theme_Brand_Mapper_Test extends TestCase {
	public function test_maps_common_theme_slugs_onto_semantic_slots(): void {
		$kit = Theme_Brand_Mapper::from_theme(
			array(
				'palette'     => array(
					array(
						'slug'  => 'base',
						'color' => '#fafafa',
					),
					array(
						'slug'  => 'contrast',
						'color' => '#111111',
					),
					array(
						'slug'  => 'accent-1',
						'color' => '#1a6dcc',
					),
					array(
						'slug'  => 'contrast-2',
						'color' => '#666666',
					),
					array(
						'slug'  => 'base-2',
						'color' => '#eeeeee',
					),
					array(
						'slug'  => 'contrast-3',
						'color' => '#dddddd',
					),
				),
				'fingerprint' => 'theme-a',
			)
		);

		self::assertSame( Brand_Kit::SOURCE_THEME, $kit->source() );
		self::assertSame( 'theme-a', $kit->theme_fingerprint() );
		self::assertSame( '#1a6dcc', $kit->color( Brand_Kit::SLOT_BRAND ) );
		self::assertSame( '#ffffff', $kit->color( Brand_Kit::SLOT_ON_BRAND ) );
		self::assertSame( '#111111', $kit->color( Brand_Kit::SLOT_TEXT ) );
		self::assertSame( '#fafafa', $kit->color( Brand_Kit::SLOT_BACKGROUND ) );
		self::assertSame( '#666666', $kit->color( Brand_Kit::SLOT_SECONDARY ) );
		self::assertSame( '#eeeeee', $kit->color( Brand_Kit::SLOT_CARD ) );
		self::assertSame( '#dddddd', $kit->color( Brand_Kit::SLOT_BORDER ) );
	}

	public function test_uses_global_style_text_and_background_when_slugs_are_missing(): void {
		$kit = Theme_Brand_Mapper::from_theme(
			array(
				'palette'    => array(),
				'text'       => '#222222',
				'background' => '#f5f5f5',
				'link'       => '#aa0000',
			)
		);

		self::assertSame( '#222222', $kit->color( Brand_Kit::SLOT_TEXT ) );
		self::assertSame( '#f5f5f5', $kit->color( Brand_Kit::SLOT_BACKGROUND ) );
		self::assertSame( '#aa0000', $kit->color( Brand_Kit::SLOT_BRAND ) );
	}

	public function test_prefers_primary_over_core_default_cyan(): void {
		$kit = Theme_Brand_Mapper::from_theme(
			array(
				'palette' => array(
					array(
						'slug'  => 'primary',
						'color' => '#aa0000',
					),
					array(
						'slug'  => 'vivid-cyan-blue',
						'color' => '#0693e3',
					),
				),
			)
		);

		self::assertSame( '#aa0000', $kit->color( Brand_Kit::SLOT_BRAND ) );
	}

	public function test_maps_theme_prefixed_slugs_without_confusing_gray_variants(): void {
		$kit = Theme_Brand_Mapper::from_theme(
			array(
				'palette' => array(
					array(
						'slug'  => 'laao-black',
						'color' => '#111111',
					),
					array(
						'slug'  => 'laao-white',
						'color' => '#ffffff',
					),
					array(
						'slug'  => 'laao-red',
						'color' => '#c4122f',
					),
					array(
						'slug'  => 'laao-dark-gray',
						'color' => '#3d3d3d',
					),
					array(
						'slug'  => 'laao-gray',
						'color' => '#7a7a7a',
					),
					array(
						'slug'  => 'laao-light-gray',
						'color' => '#e6e6e6',
					),
				),
			)
		);

		self::assertSame( '#c4122f', $kit->color( Brand_Kit::SLOT_BRAND ) );
		self::assertSame( '#111111', $kit->color( Brand_Kit::SLOT_TEXT ) );
		self::assertSame( '#e6e6e6', $kit->color( Brand_Kit::SLOT_BACKGROUND ) );
		self::assertSame( '#7a7a7a', $kit->color( Brand_Kit::SLOT_SECONDARY ) );
		self::assertSame( '#3d3d3d', $kit->color( Brand_Kit::SLOT_BORDER ) );
		self::assertSame( '#ffffff', $kit->color( Brand_Kit::SLOT_CARD ) );
	}

	public function test_converts_portable_rgb_palette_entries(): void {
		$kit = Theme_Brand_Mapper::from_theme(
			array(
				'palette' => array(
					array(
						'slug'  => 'brand',
						'color' => 'rgb(26, 109, 204)',
					),
				),
			)
		);

		self::assertSame( '#1a6dcc', $kit->color( Brand_Kit::SLOT_BRAND ) );
	}

	public function test_skips_unportable_palette_entries(): void {
		$kit = Theme_Brand_Mapper::from_theme(
			array(
				'palette' => array(
					array(
						'slug'  => 'brand',
						'color' => 'color-mix(in srgb, red 50%, blue)',
					),
				),
			)
		);

		self::assertSame( Design_Presets::color( Brand_Kit::SLOT_BRAND ), $kit->color( Brand_Kit::SLOT_BRAND ) );
	}

	public function test_empty_theme_keeps_campaignbridge_defaults(): void {
		$kit = Theme_Brand_Mapper::from_theme( array() );

		self::assertSame( Brand_Kit::defaults()->to_array()['colors'], $kit->to_array()['colors'] );
		self::assertSame( Brand_Kit::SOURCE_THEME, $kit->source() );
	}

	public function test_light_brand_gets_dark_on_brand_text(): void {
		self::assertSame( '#111111', Theme_Brand_Mapper::contrast_on( '#f4f4f4' ) );
		self::assertSame( '#ffffff', Theme_Brand_Mapper::contrast_on( '#1a6dcc' ) );
	}

	/**
	 * The picker must compare real contrast ratios, not a luma threshold.
	 *
	 * Applying the WCAG coefficients to raw sRGB skips gamma correction and
	 * misjudges mid-tones, choosing the failing colour on common brands.
	 *
	 * @dataProvider brand_fills
	 * @param string $brand    Six-digit brand colour.
	 * @param string $expected Colour that actually reads better on it.
	 */
	public function test_picks_the_higher_contrast_text_colour( string $brand, string $expected ): void {
		self::assertSame( $expected, Theme_Brand_Mapper::contrast_on( $brand ) );
	}

	/**
	 * Brand fills whose better text colour is known.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function brand_fills(): array {
		return array(
			// White scores 4.00 here and black 4.72; the old heuristic chose white.
			'pure red'      => array( '#ff0000', '#111111' ),
			// White scores 3.08 and black 6.13.
			'emerald green' => array( '#00a86b', '#111111' ),
			// White scores 3.95 and black 4.78.
			'mid grey'      => array( '#808080', '#111111' ),
			'brand blue'    => array( '#1a6dcc', '#ffffff' ),
			'indigo'        => array( '#4b0082', '#ffffff' ),
			'amber'         => array( '#ffcc00', '#111111' ),
			'saddle brown'  => array( '#8b4513', '#ffffff' ),
		);
	}

	public function test_never_returns_the_worse_of_the_two_options(): void {
		foreach ( array( '#ff0000', '#00a86b', '#808080', '#2e8b57', '#767676', '#c0c0c0', '#f5a623' ) as $brand ) {
			$chosen    = Theme_Brand_Mapper::contrast_on( $brand );
			$rejected  = '#111111' === $chosen ? '#ffffff' : '#111111';

			self::assertGreaterThanOrEqual(
				Theme_Brand_Mapper::contrast_ratio( $brand, $rejected ),
				Theme_Brand_Mapper::contrast_ratio( $brand, $chosen ),
				$brand
			);
		}
	}

	public function test_contrast_ratio_matches_the_wcag_reference_points(): void {
		// Black on white is the maximum possible ratio.
		self::assertEqualsWithDelta( 21.0, Theme_Brand_Mapper::contrast_ratio( '#000000', '#ffffff' ), 0.01 );
		// A colour against itself has no contrast.
		self::assertEqualsWithDelta( 1.0, Theme_Brand_Mapper::contrast_ratio( '#1a6dcc', '#1a6dcc' ), 0.001 );
		// Order must not change the result.
		self::assertSame(
			Theme_Brand_Mapper::contrast_ratio( '#ff0000', '#ffffff' ),
			Theme_Brand_Mapper::contrast_ratio( '#ffffff', '#ff0000' )
		);
	}
}
