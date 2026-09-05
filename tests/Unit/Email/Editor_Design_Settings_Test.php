<?php
/**
 * Editor design-settings overlay tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Unit\Email;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Design_Presets;
use CampaignBridge\REST\Helpers\Editor_Design_Settings;
use PHPUnit\Framework\TestCase;

final class Editor_Design_Settings_Test extends TestCase {
	public function test_replaces_the_theme_palette_with_the_brand_kit(): void {
		$kit      = Brand_Kit::from_colors( array( Brand_Kit::SLOT_BRAND => '#ff5500' ) );
		$settings = Editor_Design_Settings::apply(
			array(
				'__experimentalFeatures' => array(
					'color' => array(
						'palette' => array(
							'theme' => array(
								array(
									'slug'  => 'theme-only',
									'color' => '#00ff00',
								),
							),
						),
					),
				),
			),
			$kit
		);

		$palette = $settings['__experimentalFeatures']['color']['palette']['theme'];
		$slugs   = array_map( static fn( array $preset ): string => $preset['slug'], $palette );

		self::assertSame( Brand_Kit::SLOTS, $slugs );
		self::assertSame( '#ff5500', $this->color_for( $palette, Brand_Kit::SLOT_BRAND ) );
		self::assertSame( array(), $settings['__experimentalFeatures']['color']['palette']['default'] );
		self::assertFalse( $settings['__experimentalFeatures']['color']['defaultPalette'] );
		self::assertFalse( $settings['__experimentalFeatures']['color']['gradients'] );
		self::assertTrue( $settings['disableCustomGradients'] );
		self::assertSame( Design_Presets::font_sizes(), $settings['__experimentalFeatures']['typography']['fontSizes']['theme'] );
		self::assertSame( Design_Presets::spacing_sizes(), $settings['__experimentalFeatures']['spacing']['spacingSizes']['theme'] );
	}

	/**
	 * @param array<int, array{slug: string, color: string}> $palette Editor palette.
	 */
	private function color_for( array $palette, string $slug ): string {
		foreach ( $palette as $preset ) {
			if ( $preset['slug'] === $slug ) {
				return $preset['color'];
			}
		}

		self::fail( 'Missing palette slug ' . $slug );
	}
}
