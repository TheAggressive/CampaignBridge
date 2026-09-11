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
			'css' => ':where(.editor-styles-wrapper){font-family:Arial,sans-serif;font-size:16px;line-height:1.6}'
							. ':where(.wp-block-campaignbridge-text,.wp-block-campaignbridge-post-excerpt){font-size:16px;line-height:1.6;color:#333333;margin:0 0 16px}'
							. ':where(.wp-block-campaignbridge-post-title){font-size:24px;font-weight:bold;line-height:1.25;margin:0 0 12px}'
							. ':where(.wp-block-campaignbridge-heading){font-weight:bold;line-height:1.25;margin:0 0 16px}'
							. ':where(h1.wp-block-campaignbridge-heading){font-size:32px}:where(h2.wp-block-campaignbridge-heading){font-size:28px}:where(h3.wp-block-campaignbridge-heading){font-size:24px}:where(h4.wp-block-campaignbridge-heading){font-size:20px}'
							. ':where(.wp-block-campaignbridge-columns){gap:24px}'
							. '.wp-block-campaignbridge-columns{flex-wrap:nowrap!important}'
							. '.wp-block-campaignbridge-columns>.wp-block-campaignbridge-column{min-width:0;margin:0;overflow-wrap:break-word}'
							. '@media(max-width:480px){.wp-block-campaignbridge-columns:not(.is-not-stacked-on-mobile){flex-wrap:wrap!important}.wp-block-campaignbridge-columns:not(.is-not-stacked-on-mobile)>.wp-block-campaignbridge-column{flex-basis:100%!important}}'
							. ':where(.wp-block-campaignbridge-spacer){min-height:24px}'
							. ':where(.wp-block-campaignbridge-divider){border:0;border-top:1px solid #dddddd;width:100%}'
							. ':where(.wp-block-campaignbridge-post-link a){color:#111111;text-decoration:underline}'
							. ':where(.wp-block-campaignbridge-post-button a){background:#111111;color:#ffffff}'
							. ':where(.wp-block-campaignbridge-post-button.is-style-link a){background:transparent;color:#111111}',
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
}
