<?php
/**
 * Inject CampaignBridge design tokens into editor settings.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST\Helpers;

use CampaignBridge\Domain\Email\Resolved_Email_Design;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces the site theme palette with the email brand kit.
 *
 * Core's get_block_editor_settings() copies theme.json into the standalone
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
		$palette    = $design->colors();
		$font_sizes = self::pixel_presets( $design->font_sizes() );
		$spacing    = self::pixel_presets( $design->spacing_sizes() );
		$fonts      = $design->font_families();

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
		$color['gradients']        = false;
		$color['defaultGradients'] = false;
		$color['customGradient']   = false;
		$features['color']         = $color;

		$typography                     = isset( $features['typography'] ) && is_array( $features['typography'] ) ? $features['typography'] : array();
		$typography['fontSizes']        = array(
			'theme'   => $font_sizes,
			'default' => array(),
		);
		$typography['fontFamilies']     = array(
			'theme'   => $fonts,
			'default' => array(),
		);
		$typography['customFontSize']   = $design->allows_custom_font_sizes();
		$typography['customFontFamily'] = false;
		$features['typography']         = $typography;

		$spacing_features                        = isset( $features['spacing'] ) && is_array( $features['spacing'] ) ? $features['spacing'] : array();
		$spacing_features['spacingSizes']        = array(
			'theme'   => $spacing,
			'default' => array(),
		);
		$spacing_features['defaultSpacingSizes'] = false;
		$spacing_features['customSpacingSize']   = $design->allows_custom_spacing();
		$features['spacing']                     = $spacing_features;

		$settings['__experimentalFeatures'] = $features;

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
		$styles             = isset( $settings['styles'] ) && is_array( $settings['styles'] ) ? $settings['styles'] : array();
		$styles[]           = array(
			'css' => '.editor-styles-wrapper{' . implode( ';', $declarations ) . '}',
		);
		$styles[]           = array(
			'css' => self::design_css( $design )
							. ':where(h1.wp-block-campaignbridge-heading){font-size:32px}:where(h2.wp-block-campaignbridge-heading){font-size:28px}:where(h3.wp-block-campaignbridge-heading){font-size:24px}:where(h4.wp-block-campaignbridge-heading){font-size:20px}'
							. '.wp-block-campaignbridge-columns{flex-wrap:nowrap!important}'
							. '.wp-block-campaignbridge-columns>.wp-block-campaignbridge-column{min-width:0;margin:0;overflow-wrap:break-word}'
							. '@media(max-width:480px){.wp-block-campaignbridge-columns:not(.is-not-stacked-on-mobile){flex-wrap:wrap!important}.wp-block-campaignbridge-columns:not(.is-not-stacked-on-mobile)>.wp-block-campaignbridge-column{flex-basis:100%!important}}',
		);
		$settings['styles'] = $styles;

		return $settings;
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
			'campaignbridge/text'         => '.wp-block-campaignbridge-text',
			'campaignbridge/heading'      => '.wp-block-campaignbridge-heading',
			'campaignbridge/post-title'   => '.wp-block-campaignbridge-post-title',
			'campaignbridge/post-excerpt' => '.wp-block-campaignbridge-post-excerpt',
			'campaignbridge/button'       => '.wp-block-campaignbridge-button a',
			'campaignbridge/post-button'  => '.wp-block-campaignbridge-post-button a',
			'campaignbridge/post-link'    => '.wp-block-campaignbridge-post-link a',
		) as $block_name => $selector ) {
			$css .= ':where(' . $selector . '){' . self::declarations( $design->block_style( $block_name ) ) . '}';
		}

		$columns = $design->block_style( 'campaignbridge/columns' );
		$divider = $design->block_style( 'campaignbridge/divider' );
		$spacer  = $design->block_style( 'campaignbridge/spacer' );
		$global  = $design->global_style();
		$border  = is_array( $divider['border'] ?? null ) ? $divider['border'] : array();
		$colors  = is_array( $global['color'] ?? null ) ? $global['color'] : array();

		$css .= sprintf( ':where(.wp-block-campaignbridge-columns){gap:%dpx}', (int) ( $columns['spacing']['blockGap'] ?? 0 ) );
		$css .= sprintf(
			':where(.wp-block-campaignbridge-divider){border:0;border-top:%dpx %s %s;width:100%%}',
			(int) ( $border['width'] ?? 0 ),
			(string) ( $border['style'] ?? 'solid' ),
			(string) ( $border['color'] ?? 'transparent' )
		);
		$css .= sprintf( ':where(.wp-block-campaignbridge-spacer){min-height:%dpx}', (int) ( $spacer['dimensions']['minHeight'] ?? 0 ) );
		$css .= ':where(.wp-block-campaignbridge-post-button.is-style-link a){background:transparent;color:' . ( $colors['text'] ?? 'inherit' ) . '}';

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
