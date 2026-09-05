<?php
/**
 * Map a portable theme slice onto brand kit slots.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fills semantic email slots from a theme palette without inheriting it live.
 *
 * The reader has already dropped gradients, CSS variables, and non-hex
 * colours. This mapper only chooses which portable value belongs in each
 * slot. Unmapped slots keep the CampaignBridge defaults.
 */
final class Theme_Brand_Mapper {
	/**
	 * Theme slugs tried for each kit slot, first match wins.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const SLOT_CANDIDATES = array(
		Brand_Kit::SLOT_BRAND      => array( 'brand', 'accent', 'primary', 'accent-1', 'red' ),
		Brand_Kit::SLOT_TEXT       => array( 'text', 'foreground', 'contrast', 'black' ),
		Brand_Kit::SLOT_BACKGROUND => array( 'background', 'base', 'light-gray', 'white' ),
		Brand_Kit::SLOT_SECONDARY  => array( 'secondary', 'muted', 'contrast-2', 'gray' ),
		Brand_Kit::SLOT_CARD       => array( 'card', 'surface', 'base-2', 'white' ),
		Brand_Kit::SLOT_BORDER     => array( 'border', 'contrast-3', 'dark-gray' ),
	);

	/**
	 * Map an extracted theme slice onto a brand kit.
	 *
	 * @param array{palette?: array<int, array{slug?: string, color?: string}>, text?: string|null, background?: string|null, link?: string|null, fingerprint?: string|null} $extracted Portable theme slice.
	 */
	public static function from_theme( array $extracted ): Brand_Kit {
		$by_slug = array();

		foreach ( $extracted['palette'] ?? array() as $item ) {
			if ( ! isset( $item['slug'], $item['color'] ) || ! is_string( $item['slug'] ) ) {
				continue;
			}

			$hex = Portable_Color::from( $item['color'] );
			if ( null !== $hex ) {
				$by_slug[ $item['slug'] ] = $hex;
			}
		}

		$colors = array();

		foreach ( self::SLOT_CANDIDATES as $slot => $candidates ) {
			$match = self::first_match( $by_slug, $candidates );
			if ( null !== $match ) {
				$colors[ $slot ] = $match;
			}
		}

		$text = Portable_Color::from( $extracted['text'] ?? null );
		if ( ! isset( $colors[ Brand_Kit::SLOT_TEXT ] ) && null !== $text ) {
			$colors[ Brand_Kit::SLOT_TEXT ] = $text;
		}

		$background = Portable_Color::from( $extracted['background'] ?? null );
		if ( ! isset( $colors[ Brand_Kit::SLOT_BACKGROUND ] ) && null !== $background ) {
			$colors[ Brand_Kit::SLOT_BACKGROUND ] = $background;
		}

		$link = Portable_Color::from( $extracted['link'] ?? null );
		if ( ! isset( $colors[ Brand_Kit::SLOT_BRAND ] ) && null !== $link ) {
			$colors[ Brand_Kit::SLOT_BRAND ] = $link;
		}

		if ( isset( $colors[ Brand_Kit::SLOT_BRAND ] ) ) {
			$colors[ Brand_Kit::SLOT_ON_BRAND ] = self::contrast_on( $colors[ Brand_Kit::SLOT_BRAND ] );
		}

		$fingerprint = isset( $extracted['fingerprint'] ) && is_string( $extracted['fingerprint'] )
			? $extracted['fingerprint']
			: null;

		return Brand_Kit::from_colors( $colors, Brand_Kit::SOURCE_THEME, $fingerprint );
	}

	/**
	 * Pick the more readable text colour for a solid brand fill.
	 *
	 * Compares real WCAG contrast ratios rather than thresholding a luma
	 * value. Applying the luminance coefficients to raw sRGB skips the gamma
	 * step and misjudges mid-tones: it picks white on #ff0000 at 4.00:1 where
	 * black reaches 4.72:1, and white on #00a86b at 3.08:1 where black
	 * reaches 6.13:1. Both of those fail AA while the alternative passes.
	 *
	 * @param string $hex Six-digit brand colour.
	 */
	public static function contrast_on( string $hex ): string {
		$dark  = '#111111';
		$light = '#ffffff';

		return self::contrast_ratio( $hex, $dark ) >= self::contrast_ratio( $hex, $light )
			? $dark
			: $light;
	}

	/**
	 * WCAG 2.x contrast ratio between two opaque colours.
	 *
	 * @param string $first  Six-digit colour.
	 * @param string $second Six-digit colour.
	 */
	public static function contrast_ratio( string $first, string $second ): float {
		$lighter = self::relative_luminance( $first );
		$darker  = self::relative_luminance( $second );

		if ( $lighter < $darker ) {
			[ $lighter, $darker ] = array( $darker, $lighter );
		}

		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * WCAG relative luminance, with sRGB channels linearized first.
	 *
	 * @param string $hex Six-digit colour.
	 */
	private static function relative_luminance( string $hex ): float {
		$channels = array(
			(int) hexdec( substr( $hex, 1, 2 ) ),
			(int) hexdec( substr( $hex, 3, 2 ) ),
			(int) hexdec( substr( $hex, 5, 2 ) ),
		);

		$linear = array_map(
			static function ( int $channel ): float {
				$value = $channel / 255;

				return 0.04045 >= $value
					? $value / 12.92
					: ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
			},
			$channels
		);

		return ( 0.2126 * $linear[0] ) + ( 0.7152 * $linear[1] ) + ( 0.0722 * $linear[2] );
	}

	/**
	 * Find the first candidate in the palette, including theme-prefixed slugs.
	 *
	 * `laao-red` matches the `red` candidate after the theme prefix is stripped.
	 *
	 * @param array<string, string> $by_slug    Resolved palette.
	 * @param array<int, string>    $candidates Preferred slugs.
	 */
	private static function first_match( array $by_slug, array $candidates ): ?string {
		foreach ( $candidates as $candidate ) {
			if ( isset( $by_slug[ $candidate ] ) ) {
				return $by_slug[ $candidate ];
			}

			foreach ( $by_slug as $slug => $hex ) {
				$base = preg_replace( '/^[a-z0-9]+-/', '', $slug );
				if ( $candidate === $base ) {
					return $hex;
				}
			}
		}

		return null;
	}
}
