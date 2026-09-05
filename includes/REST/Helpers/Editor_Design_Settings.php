<?php
/**
 * Inject CampaignBridge design tokens into editor settings.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\REST\Helpers;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Design_Presets;

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
	 * Overlay brand-kit presets onto block editor settings.
	 *
	 * @param array<string, mixed> $settings Core editor settings.
	 * @param Brand_Kit            $kit      Active brand kit.
	 * @return array<string, mixed>
	 */
	public static function apply( array $settings, Brand_Kit $kit ): array {
		$palette    = $kit->colors();
		$font_sizes = Design_Presets::font_sizes();
		$spacing    = Design_Presets::spacing_sizes();

		$settings['colors']                 = $palette;
		$settings['fontSizes']              = $font_sizes;
		$settings['disableCustomColors']    = false;
		$settings['disableCustomGradients'] = true;
		$settings['gradients']              = array();

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
		$color['custom']           = true;
		$color['gradients']        = false;
		$color['defaultGradients'] = false;
		$color['customGradient']   = false;
		$features['color']         = $color;

		$typography                   = isset( $features['typography'] ) && is_array( $features['typography'] ) ? $features['typography'] : array();
		$typography['fontSizes']      = array(
			'theme'   => $font_sizes,
			'default' => array(),
		);
		$typography['customFontSize'] = true;
		$features['typography']       = $typography;

		$spacing_features                        = isset( $features['spacing'] ) && is_array( $features['spacing'] ) ? $features['spacing'] : array();
		$spacing_features['spacingSizes']        = array(
			'theme'   => $spacing,
			'default' => array(),
		);
		$spacing_features['defaultSpacingSizes'] = false;
		$features['spacing']                     = $spacing_features;

		$settings['__experimentalFeatures'] = $features;

		return $settings;
	}
}
