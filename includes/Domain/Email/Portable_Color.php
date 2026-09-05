<?php
/**
 * Portable colour parsing for email.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a theme colour into a six-digit hex the compiler can inline.
 *
 * Hex is accepted as-is. oklch(), rgb(), and hsl() are converted once.
 * Transparent or unresolvable values are rejected rather than guessed.
 */
final class Portable_Color {
	/**
	 * Convert a raw CSS colour to six-digit hex.
	 *
	 * @param mixed $value Raw colour.
	 */
	public static function from( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = trim( $value );
		$hex   = Brand_Kit::normalize_hex( $value );
		if ( null !== $hex ) {
			return $hex;
		}

		if ( 1 === preg_match( '/^oklch\(\s*([\d.]+)(%?)\s+([\d.]+)\s+([\d.]+)(?:deg)?(?:\s*\/\s*([\d.]+%?))?\s*\)$/i', $value, $matches ) ) {
			return self::from_oklch(
				self::component( $matches[1], $matches[2], true ),
				(float) $matches[3],
				(float) $matches[4],
				self::alpha( $matches[5] ?? null )
			);
		}

		if ( 1 === preg_match( '/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+%?))?\s*\)$/i', $value, $matches ) ) {
			if ( 0.0 === self::alpha( $matches[4] ?? null ) ) {
				return null;
			}

			return self::from_rgb( (float) $matches[1], (float) $matches[2], (float) $matches[3] );
		}

		if ( 1 === preg_match( '/^hsla?\(\s*([\d.]+)\s*,\s*([\d.]+)%\s*,\s*([\d.]+)%(?:\s*,\s*([\d.]+%?))?\s*\)$/i', $value, $matches ) ) {
			if ( 0.0 === self::alpha( $matches[4] ?? null ) ) {
				return null;
			}

			return self::from_hsl( (float) $matches[1], (float) $matches[2] / 100, (float) $matches[3] / 100 );
		}

		return null;
	}

	/**
	 * Parse an alpha channel. Missing alpha is fully opaque.
	 *
	 * @param string|null $value Raw alpha.
	 */
	private static function alpha( ?string $value ): float {
		if ( null === $value || '' === $value ) {
			return 1.0;
		}

		if ( str_ends_with( $value, '%' ) ) {
			return (float) substr( $value, 0, -1 ) / 100;
		}

		return (float) $value;
	}

	/**
	 * Normalise a component that may be a percentage.
	 *
	 * @param string $value      Raw number.
	 * @param string $percent    '%' when the number was a percentage.
	 * @param bool   $allow_bare Whether a bare 0-100 value is a percentage.
	 */
	private static function component( string $value, string $percent, bool $allow_bare ): float {
		$number = (float) $value;

		if ( '%' === $percent || ( $allow_bare && 1.0 < $number ) ) {
			return $number / 100;
		}

		return $number;
	}

	/**
	 * Convert OKLCH to hex. Transparent colours are rejected.
	 *
	 * @param float $lightness Lightness 0-1.
	 * @param float $chroma    Chroma.
	 * @param float $hue       Hue in degrees.
	 * @param float $alpha     Opacity 0-1.
	 */
	private static function from_oklch( float $lightness, float $chroma, float $hue, float $alpha ): ?string {
		if ( 0.0 === $alpha ) {
			return null;
		}

		$lightness = max( 0.0, min( 1.0, $lightness ) );
		$radians   = deg2rad( $hue );
		$a         = $chroma * cos( $radians );
		$b         = $chroma * sin( $radians );

		$l = $lightness + ( 0.3963377774 * $a ) + ( 0.2158037573 * $b );
		$m = $lightness - ( 0.1055613458 * $a ) - ( 0.0638541728 * $b );
		$s = $lightness - ( 0.0894841775 * $a ) - ( 1.2914855480 * $b );

		$l = $l ** 3;
		$m = $m ** 3;
		$s = $s ** 3;

		return self::from_linear_srgb(
			( 4.0767416621 * $l ) - ( 3.3077115913 * $m ) + ( 0.2309699292 * $s ),
			( -1.2684380046 * $l ) + ( 2.6097574011 * $m ) - ( 0.3413193965 * $s ),
			( -0.0041960863 * $l ) - ( 0.7034186147 * $m ) + ( 1.7076147010 * $s )
		);
	}

	/**
	 * Convert 0-255 sRGB channels to hex.
	 *
	 * @param float $red   Red 0-255.
	 * @param float $green Green 0-255.
	 * @param float $blue  Blue 0-255.
	 */
	private static function from_rgb( float $red, float $green, float $blue ): string {
		return sprintf(
			'#%02x%02x%02x',
			self::clamp_byte( $red ),
			self::clamp_byte( $green ),
			self::clamp_byte( $blue )
		);
	}

	/**
	 * Convert HSL to hex.
	 *
	 * @param float $hue        Hue in degrees.
	 * @param float $saturation Saturation 0-1.
	 * @param float $lightness  Lightness 0-1.
	 */
	private static function from_hsl( float $hue, float $saturation, float $lightness ): string {
		$hue = fmod( $hue, 360 ) / 360;
		$c   = ( 1 - abs( ( 2 * $lightness ) - 1 ) ) * $saturation;
		$x   = $c * ( 1 - abs( fmod( $hue * 6, 2 ) - 1 ) );
		$m   = $lightness - ( $c / 2 );

		if ( $hue < 1 / 6 ) {
			$red   = $c;
			$green = $x;
			$blue  = 0.0;
		} elseif ( $hue < 2 / 6 ) {
			$red   = $x;
			$green = $c;
			$blue  = 0.0;
		} elseif ( $hue < 3 / 6 ) {
			$red   = 0.0;
			$green = $c;
			$blue  = $x;
		} elseif ( $hue < 4 / 6 ) {
			$red   = 0.0;
			$green = $x;
			$blue  = $c;
		} elseif ( $hue < 5 / 6 ) {
			$red   = $x;
			$green = 0.0;
			$blue  = $c;
		} else {
			$red   = $c;
			$green = 0.0;
			$blue  = $x;
		}

		return self::from_rgb( ( $red + $m ) * 255, ( $green + $m ) * 255, ( $blue + $m ) * 255 );
	}

	/**
	 * Convert linear sRGB 0-1 channels to hex.
	 *
	 * @param float $red   Linear red.
	 * @param float $green Linear green.
	 * @param float $blue  Linear blue.
	 */
	private static function from_linear_srgb( float $red, float $green, float $blue ): string {
		return self::from_rgb(
			self::linear_to_srgb( $red ) * 255,
			self::linear_to_srgb( $green ) * 255,
			self::linear_to_srgb( $blue ) * 255
		);
	}

	/**
	 * Compand a linear sRGB channel.
	 *
	 * @param float $channel Linear channel.
	 */
	private static function linear_to_srgb( float $channel ): float {
		$channel = max( 0.0, min( 1.0, $channel ) );

		return 0.0031308 >= $channel
			? 12.92 * $channel
			: ( 1.055 * ( $channel ** ( 1 / 2.4 ) ) ) - 0.055;
	}

	/**
	 * Clamp a 0-255 channel.
	 *
	 * @param float $value Channel.
	 */
	private static function clamp_byte( float $value ): int {
		return (int) max( 0, min( 255, (int) round( $value ) ) );
	}
}
