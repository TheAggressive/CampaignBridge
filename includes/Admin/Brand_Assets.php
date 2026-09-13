<?php
/**
 * Official CampaignBridge brand assets.
 *
 * @package CampaignBridge\Admin
 */

declare(strict_types=1);

namespace CampaignBridge\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Locates the official brand files shipped in `assets/brand`.
 *
 * File names follow the brand kit: "Light" variants are drawn for light
 * backgrounds and "Dark" variants for dark backgrounds.
 */
final class Brand_Assets {
	/**
	 * White monochrome icon for the dark admin menu. WordPress's SVG painter
	 * replaces its declared fill to match each admin color scheme's menu,
	 * hover, and current-page colors; an icon without a fill stays black.
	 */
	public const MENU_ICON = 'CB_Icon_Mono_Dark.svg';

	/**
	 * White icon for the brand-colored mark in the admin product header.
	 */
	public const HEADER_ICON = 'CB_Icon_Mono_Dark.svg';

	private const DIRECTORY = 'assets/brand/';

	private const FALLBACK_MENU_ICON = 'dashicons-email-alt';

	/**
	 * Absolute filesystem path to a brand file.
	 *
	 * @param string $file Brand file name.
	 */
	public static function path( string $file ): string {
		return \CampaignBridge_Plugin::path() . self::DIRECTORY . $file;
	}

	/**
	 * Public URL of a brand file.
	 *
	 * @param string $file Brand file name.
	 */
	public static function url( string $file ): string {
		return \CampaignBridge_Plugin::url() . self::DIRECTORY . $file;
	}

	/**
	 * Admin menu icon as the base64 SVG data URI WordPress expects.
	 */
	public static function menu_icon(): string {
		$path = self::path( self::MENU_ICON );
		$svg  = is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown,CampaignBridge.Standard.Sniffs.Http.DirectHttpRequest.DirectHttpFunction -- Reads a bundled local SVG, never a remote resource.

		if ( ! is_string( $svg ) || '' === $svg ) {
			return self::FALLBACK_MENU_ICON;
		}

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress requires menu SVG icons as base64 data URIs.
	}
}
