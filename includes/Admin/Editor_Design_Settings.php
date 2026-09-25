<?php
/**
 * Inject CampaignBridge design tokens into editor settings.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

use CampaignBridge\Domain\Email\Resolved_Email_Design;
use CampaignBridge\Services\Email\Google_Fonts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces the site theme palette with the email brand kit.
 *
 * Core's get_block_editor_settings() supplies theme.json values to the native
 * editor. Email cannot honour that palette, so the Styles sidebar must be
 * shown the same slots the compiler resolves.
 */
final class Editor_Design_Settings {
	/**
	 * Adapt the resolved email design to block editor settings.
	 *
	 * @param array<string, mixed>  $settings Core editor settings.
	 * @param Resolved_Email_Design $design  Canonical runtime design.
	 * @return array<string, mixed>
	 */
	public static function apply( array $settings, Resolved_Email_Design $design ): array {
		$palette      = $design->colors();
		$font_sizes   = self::pixel_presets( $design->font_sizes() );
		$spacing      = self::pixel_presets( $design->spacing_sizes() );
		$fonts        = $design->font_families();
		$editor_fonts = self::editor_font_presets( $fonts );

		$settings['colors']                    = $palette;
		$settings['fontSizes']                 = $font_sizes;
		$settings['spacingSizes']              = $spacing;
		$settings['disableCustomColors']       = ! $design->allows_custom_colors();
		$settings['disableCustomFontSizes']    = ! $design->allows_custom_font_sizes();
		$settings['disableCustomSpacingSizes'] = ! $design->allows_custom_spacing();
		$settings['disableCustomGradients']    = true;
		$settings['gradients']                 = array();

		$features = isset( $settings['__experimentalFeatures'] ) && is_array( $settings['__experimentalFeatures'] )
			? $settings['__experimentalFeatures']
			: array();

		$color                     = isset( $features['color'] ) && is_array( $features['color'] ) ? $features['color'] : array();
		$color['palette']          = array(
			'theme'   => $palette,
			'default' => array(),
			'custom'  => array(),
		);
		$color['defaultPalette']   = false;
		$color['custom']           = $design->allows_custom_colors();
		$color['gradients']        = array(
			'theme'   => array(),
			'default' => array(),
			'custom'  => array(),
		);
		$color['defaultGradients'] = false;
		$color['customGradient']   = false;
		$features['color']         = $color;

		$typography                     = isset( $features['typography'] ) && is_array( $features['typography'] ) ? $features['typography'] : array();
		$typography['fontSizes']        = array(
			'theme'   => $font_sizes,
			'default' => array(),
			'custom'  => array(),
		);
		$typography['fontFamilies']     = array(
			'theme'   => $editor_fonts,
			'default' => array(),
			'custom'  => array(),
		);
		$typography['defaultFontSizes'] = false;
		$typography['customFontSize']   = $design->allows_custom_font_sizes();
		$typography['customFontFamily'] = false;
		$typography['dropCap']          = false;
		$features['typography']         = $typography;

		$spacing_features                        = isset( $features['spacing'] ) && is_array( $features['spacing'] ) ? $features['spacing'] : array();
		$spacing_features['spacingSizes']        = array(
			'theme'   => $spacing,
			'default' => array(),
			'custom'  => array(),
		);
		$spacing_features['defaultSpacingSizes'] = false;
		$spacing_features['customSpacingSize']   = $design->allows_custom_spacing();
		$features['spacing']                     = $spacing_features;

		$settings['__experimentalFeatures']     = $features;
		$settings['campaignbridgeFontAssets']   = self::font_assets( $fonts );
		$settings['campaignbridgeDefaultFonts'] = array_values( array_unique( $design->brand_fonts() ) );

		// Core has already generated preset CSS from theme.json. Replacing the
		// control settings alone leaves those old lengths active in the canvas.
		$declarations = array();
		foreach ( $spacing as $preset ) {
			$declarations[] = sprintf( '--wp--preset--spacing--%s:%s', $preset['slug'], $preset['size'] );
		}
		foreach ( $palette as $preset ) {
			$declarations[] = sprintf( '--wp--preset--color--%s:%s', $preset['slug'], $preset['color'] );
		}
		foreach ( $font_sizes as $preset ) {
			$declarations[] = sprintf( '--wp--preset--font-size--%s:%s', $preset['slug'], $preset['size'] );
		}
		foreach ( $fonts as $preset ) {
			$declarations[] = sprintf( '--wp--preset--font-family--%s:%s', $preset['slug'], $preset['family'] );
		}
		$preset_rules       = self::preset_rules( $palette, $font_sizes, $fonts );
		$styles             = isset( $settings['styles'] ) && is_array( $settings['styles'] ) ? $settings['styles'] : array();
		$styles[]           = array(
			'css' => '.editor-styles-wrapper{' . implode( ';', $declarations ) . '}' . $preset_rules,
		);
		$styles[]           = array(
			'css' => self::design_css( $design )
							. ':where(h1[data-type="core/heading"]){font-size:32px}:where(h2[data-type="core/heading"]){font-size:28px}:where(h3[data-type="core/heading"]){font-size:24px}:where(h4[data-type="core/heading"]){font-size:20px}'
							. '.wp-block-campaignbridge-columns{flex-wrap:nowrap!important}'
							. '.wp-block-campaignbridge-columns>.wp-block-campaignbridge-column{min-width:0;margin:0;overflow-wrap:break-word}'
							. '@media(max-width:480px){.wp-block-campaignbridge-columns:not(.is-not-stacked-on-mobile){flex-wrap:wrap!important}.wp-block-campaignbridge-columns:not(.is-not-stacked-on-mobile)>.wp-block-campaignbridge-column{flex-basis:100%!important}}',
		);
		$settings['styles'] = $styles;

		return $settings;
	}

	/**
	 * Export the bounded values needed after Gutenberg resolves global styles.
	 *
	 * Gutenberg's client-side global-styles resolver can replace the initial
	 * PHP feature tree with the site theme. The native useSetting filter and
	 * editor-canvas font loader consume this independent immutable payload.
	 *
	 * @param Resolved_Email_Design $design Canonical runtime design.
	 * @return array<string, mixed>
	 */
	public static function client_config( Resolved_Email_Design $design ): array {
		$settings = self::apply( array(), $design );

		return array(
			'features'     => $settings['__experimentalFeatures'],
			'fontAssets'   => $settings['campaignbridgeFontAssets'],
			'defaultFonts' => $settings['campaignbridgeDefaultFonts'],
		);
	}

	/**
	 * Recreate Core preset utility rules after replacing theme-owned catalogs.
	 *
	 * Core generated the original stylesheet before this adapter replaces the
	 * settings. Variables alone are insufficient when the active theme did not
	 * declare the CampaignBridge slug, because saved Core blocks use utility
	 * classes such as `has-brand-color` and `has-inter-font-family`.
	 *
	 * @param array<int, array<string, mixed>> $palette    Color presets.
	 * @param array<int, array<string, mixed>> $font_sizes Font-size presets.
	 * @param array<int, array<string, mixed>> $fonts      Font-family presets.
	 */
	private static function preset_rules( array $palette, array $font_sizes, array $fonts ): string {
		$rules = array();
		foreach ( $palette as $preset ) {
			$slug     = (string) $preset['slug'];
			$variable = 'var(--wp--preset--color--' . $slug . ')';
			$rules[]  = '.editor-styles-wrapper .has-' . $slug . '-color{color:' . $variable . '!important}';
			$rules[]  = '.editor-styles-wrapper .has-' . $slug . '-background-color{background-color:' . $variable . '!important}';
			$rules[]  = '.editor-styles-wrapper .has-' . $slug . '-border-color{border-color:' . $variable . '!important}';
		}
		foreach ( $font_sizes as $preset ) {
			$slug    = (string) $preset['slug'];
			$rules[] = '.editor-styles-wrapper .has-' . $slug . '-font-size{font-size:var(--wp--preset--font-size--' . $slug . ')!important}';
		}
		foreach ( $fonts as $preset ) {
			$slug    = (string) $preset['slug'];
			$rules[] = '.editor-styles-wrapper .has-' . $slug . '-font-family{font-family:var(--wp--preset--font-family--' . $slug . ')!important}';
		}

		return implode( '', $rules );
	}

	/**
	 * Adapt canonical font records to Core's theme.json editor shape.
	 *
	 * @param array<int, array<string, mixed>> $fonts Canonical font records.
	 * @return array<int, array{slug: string, name: string, fontFamily: string}>
	 */
	private static function editor_font_presets( array $fonts ): array {
		$presets = array();
		foreach ( $fonts as $font ) {
			$presets[] = array(
				'slug'       => (string) $font['slug'],
				'name'       => (string) $font['name'],
				'fontFamily' => (string) $font['family'],
			);
		}

		return $presets;
	}

	/**
	 * Publish a bounded slug-to-stylesheet map for the editor canvas loader.
	 *
	 * The browser receives the validated catalog but loads only the resolved
	 * defaults and explicit presets used by blocks in this editor session.
	 *
	 * @param array<int, array<string, mixed>> $fonts Canonical font records.
	 * @return array<string, string>
	 */
	private static function font_assets( array $fonts ): array {
		if ( ! Google_Fonts::external_enabled() ) {
			return array();
		}

		$assets = array();
		foreach ( $fonts as $font ) {
			$slug = $font['slug'] ?? null;
			$url  = $font['url'] ?? null;
			if ( 'web' !== ( $font['type'] ?? null ) || ! is_string( $slug ) || '' === $slug || ! is_string( $url ) || ! Google_Fonts::is_safe_stylesheet_url( $url ) ) {
				continue;
			}

			$assets[ $slug ] = $url;
		}

		return $assets;
	}

	/**
	 * Convert normalized pixel integers back to Gutenberg preset strings.
	 *
	 * @param array<int, array<string, mixed>> $presets Normalized presets.
	 * @return array<int, array<string, mixed>>
	 */
	private static function pixel_presets( array $presets ): array {
		foreach ( $presets as &$preset ) {
			$preset['size'] = (string) $preset['size'] . 'px';
		}
		unset( $preset );

		return $presets;
	}

	/**
	 * Build editor visual defaults from the canonical resolved design.
	 *
	 * @param Resolved_Email_Design $design Canonical runtime design.
	 */
	private static function design_css( Resolved_Email_Design $design ): string {
		$css = ':where(.editor-styles-wrapper){' . self::declarations( $design->global_style() ) . '}';
		foreach ( array(
			'core/paragraph' => '[data-type="core/paragraph"]',
			'core/heading'   => '[data-type="core/heading"]',
			'core/button'    => '[data-type="core/button"] .wp-block-button__link',
		) as $block_name => $selector ) {
			$css .= ':where(' . $selector . '){' . self::declarations( $design->block_style( $block_name ) ) . '}';
		}

		$columns = $design->block_style( 'campaignbridge/columns' );
		$divider = $design->block_style( 'core/separator' );
		$global  = $design->global_style();
		$border  = is_array( $divider['border'] ?? null ) ? $divider['border'] : array();
		$colors  = is_array( $global['color'] ?? null ) ? $global['color'] : array();

		$css .= sprintf( ':where(.wp-block-campaignbridge-columns){gap:%dpx}', (int) ( $columns['spacing']['blockGap'] ?? 0 ) );
		// The compiler renders every Core separator as a full-width design rule.
		$css .= sprintf(
			':where([data-type="core/separator"]){color:%3$s;border-top-width:%1$dpx;border-top-style:%2$s}',
			(int) ( $border['width'] ?? 0 ),
			(string) ( $border['style'] ?? 'solid' ),
			(string) ( $border['color'] ?? 'transparent' )
		);
		$css .= '.editor-styles-wrapper .block-editor-block-list__block.wp-block-separator{width:100%;max-width:none}';
		// Site themes can hide Social Icons outside their own footer or navigation
		// regions. Email templates support this Core block in any permitted section.
		$css .= '.editor-styles-wrapper [data-type="core/social-links"]{display:flex!important}';
		$css .= ':where([data-type="core/button"].is-style-ghost .wp-block-button__link){background:transparent;color:' . ( $colors['text'] ?? 'inherit' ) . '}';

		return $css;
	}

	/**
	 * Convert one normalized design style to browser CSS declarations.
	 *
	 * @param array<string, mixed> $style Normalized design style.
	 */
	private static function declarations( array $style ): string {
		$color      = is_array( $style['color'] ?? null ) ? $style['color'] : array();
		$typography = is_array( $style['typography'] ?? null ) ? $style['typography'] : array();
		$spacing    = is_array( $style['spacing'] ?? null ) ? $style['spacing'] : array();
		$values     = array();

		foreach ( array(
			'background' => 'background-color',
			'text'       => 'color',
		) as $key => $property ) {
			if ( isset( $color[ $key ] ) ) {
				$values[] = $property . ':' . $color[ $key ];
			}
		}
		foreach ( array(
			'fontFamily' => 'font-family',
			'fontSize'   => 'font-size',
			'fontWeight' => 'font-weight',
			'lineHeight' => 'line-height',
		) as $key => $property ) {
			if ( isset( $typography[ $key ] ) ) {
				$suffix   = 'fontSize' === $key ? 'px' : '';
				$values[] = $property . ':' . $typography[ $key ] . $suffix;
			}
		}
		if ( isset( $spacing['marginBottom'] ) ) {
			$values[] = 'margin:0 0 ' . $spacing['marginBottom'] . 'px';
		}

		return implode( ';', $values );
	}
}
