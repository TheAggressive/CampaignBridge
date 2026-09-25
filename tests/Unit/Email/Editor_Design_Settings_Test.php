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
use CampaignBridge\Admin\Editor_Design_Settings;
use CampaignBridge\Services\Email\Design\Email_Design_Factory;
use PHPUnit\Framework\TestCase;

/** Tests the resolved-design adapter for Gutenberg editor settings. */
final class Editor_Design_Settings_Test extends TestCase {
	/** The manifest palette replaces any palette inherited from the site theme. */
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
			Email_Design_Factory::resolve( $kit )
		);

		$palette = $settings['__experimentalFeatures']['color']['palette']['theme'];
		$slugs   = array_map( static fn( array $preset ): string => $preset['slug'], $palette );

		self::assertSame( Brand_Kit::SLOTS, $slugs );
		self::assertSame( '#ff5500', $this->color_for( $palette, Brand_Kit::SLOT_BRAND ) );
		self::assertSame( array(), $settings['__experimentalFeatures']['color']['palette']['default'] );
		self::assertFalse( $settings['__experimentalFeatures']['color']['defaultPalette'] );
		self::assertSame( array(), $settings['__experimentalFeatures']['color']['gradients']['default'] );
		self::assertTrue( $settings['disableCustomGradients'] );
		self::assertTrue( $settings['disableCustomColors'] );
		self::assertTrue( $settings['disableCustomFontSizes'] );
		self::assertTrue( $settings['disableCustomSpacingSizes'] );
		self::assertFalse( $settings['__experimentalFeatures']['color']['custom'] );
		self::assertFalse( $settings['__experimentalFeatures']['typography']['customFontSize'] );
		self::assertFalse( $settings['__experimentalFeatures']['typography']['customFontFamily'] );
		self::assertFalse( $settings['__experimentalFeatures']['typography']['defaultFontSizes'] );
		self::assertSame( array(), $settings['__experimentalFeatures']['typography']['fontFamilies']['custom'] );
		self::assertSame( array(), $settings['__experimentalFeatures']['color']['gradients']['theme'] );
		self::assertFalse( $settings['__experimentalFeatures']['spacing']['customSpacingSize'] );
		self::assertSame( Design_Presets::font_sizes(), $settings['__experimentalFeatures']['typography']['fontSizes']['theme'] );
		self::assertSame( Design_Presets::spacing_sizes(), $settings['__experimentalFeatures']['spacing']['spacingSizes']['theme'] );
	}

	/** Generated canvas variables use the resolved email spacing presets. */
	public function test_canvas_css_uses_the_same_spacing_values_as_the_compiler(): void {
		$existing = array( 'css' => ':root{--wp--preset--spacing--20:1.25rem}' );
		$settings = Editor_Design_Settings::apply( array( 'styles' => array( $existing ) ), Email_Design_Factory::resolve() );
		self::assertSame( $existing, $settings['styles'][0] );
		$css = $settings['styles'][1]['css'];
		self::assertStringContainsString( '.editor-styles-wrapper{', $css );
		foreach ( Design_Presets::spacing_sizes() as $preset ) {
			self::assertStringContainsString( '--wp--preset--spacing--' . $preset['slug'] . ':' . Design_Presets::spacing( $preset['slug'] ), $css );
		}
		self::assertStringContainsString( '--wp--preset--spacing--20:8px', $css );
		self::assertStringContainsString( '.editor-styles-wrapper .has-brand-color{color:var(--wp--preset--color--brand)!important}', $css );
		self::assertStringContainsString( '.editor-styles-wrapper .has-brand-background-color{background-color:var(--wp--preset--color--brand)!important}', $css );
		self::assertStringContainsString( '.editor-styles-wrapper .has-inter-font-family{font-family:var(--wp--preset--font-family--inter)!important}', $css );
		self::assertStringContainsString(
			'.editor-styles-wrapper [data-type="core/social-links"]{display:flex!important}',
			implode( '', array_column( $settings['styles'], 'css' ) )
		);
	}

	/** A configured brand font is exposed as a bounded editor preset. */
	public function test_custom_brand_font_is_available_to_individual_blocks(): void {
		$custom = array(
			'name'    => 'Example Sans',
			'family'  => 'Example Sans,Arial,Helvetica,sans-serif',
			'weights' => array( 400, 700 ),
			'url'     => 'https://fonts.googleapis.com/css2?family=Example+Sans:wght@400;700&display=swap',
		);
		$kit    = Brand_Kit::from_colors( array(), Brand_Kit::SOURCE_CUSTOM, null, array( 'heading' => 'custom' ), $custom );

		$settings = Editor_Design_Settings::apply( array(), Email_Design_Factory::resolve( $kit ) );
		$fonts    = $settings['__experimentalFeatures']['typography']['fontFamilies']['theme'];

		self::assertContains( Brand_Kit::CUSTOM_FONT_SLUG, array_column( $fonts, 'slug' ) );
		self::assertSame( 'Example Sans,Arial,Helvetica,sans-serif', array_column( $fonts, 'fontFamily', 'slug' )['custom'] );
		self::assertStringContainsString( '--wp--preset--font-family--custom:Example Sans,Arial,Helvetica,sans-serif', $settings['styles'][0]['css'] );
		self::assertSame( array( 'custom', 'arial' ), $settings['campaignbridgeDefaultFonts'] );
		self::assertSame( $custom['url'], $settings['campaignbridgeFontAssets']['custom'] );

		$config = Editor_Design_Settings::client_config( Email_Design_Factory::resolve( $kit ) );
		self::assertSame( $fonts, $config['features']['typography']['fontFamilies']['theme'] );
		self::assertSame( $custom['url'], $config['fontAssets']['custom'] );
		self::assertSame( array( 'custom', 'arial' ), $config['defaultFonts'] );
	}

	/** External-font policy prevents every remote editor-canvas request. */
	public function test_external_font_policy_removes_editor_font_assets(): void {
		$disable = static fn(): bool => false;
		add_filter( 'campaignbridge_external_google_fonts_enabled', $disable );

		try {
			$settings = Editor_Design_Settings::apply( array(), Email_Design_Factory::resolve() );
			self::assertSame( array(), $settings['campaignbridgeFontAssets'] );
		} finally {
			remove_filter( 'campaignbridge_external_google_fonts_enabled', $disable );
		}
	}

	/** Malformed remote URLs cannot enter the editor asset map. */
	public function test_editor_font_assets_reject_unapproved_urls(): void {
		$resolved = Email_Design_Factory::resolve();
		$design   = $resolved->to_array();
		$design['settings']['typography']['fontFamilies'][] = array(
			'slug'    => 'unsafe',
			'name'    => 'Unsafe',
			'family'  => 'Unsafe,Arial,sans-serif',
			'type'    => 'web',
			'weights' => array( 400 ),
			'url'     => 'https://example.com/font.css',
		);

		$settings = Editor_Design_Settings::apply( array(), new \CampaignBridge\Domain\Email\Resolved_Email_Design( $design, 'test' ) );

		self::assertArrayNotHasKey( 'unsafe', $settings['campaignbridgeFontAssets'] );
	}

	/**
	 * Find a color value by preset slug.
	 *
	 * @param array<int, array{slug: string, color: string}> $palette Editor palette.
	 * @param string                                         $slug    Preset slug.
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
