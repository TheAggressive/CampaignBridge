<?php
/**
 * Core block style resolution for email.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns the style data core writes into values email markup can carry.
 *
 * WordPress records a style choice in one of three shapes, and a block can use
 * all three at once:
 *
 * - a preset slug in a top-level attribute, `backgroundColor: "accent"`;
 * - a literal in the style tree, `style.color.background: "#1a6dcc"`;
 * - a preset reference in the style tree, `var:preset|spacing|40`.
 *
 * A browser resolves the last two through CSS custom properties supplied by a
 * stylesheet. Email has no stylesheet, so every one of them has to become a
 * literal here, before it reaches a renderer.
 */
final class Style_Resolver {
	/**
	 * Pixels per rem, used only to normalize author input.
	 *
	 * Email clients do not agree on a root font size, so a rem value is
	 * converted once here rather than shipped and hoped for.
	 */
	private const PIXELS_PER_REM = 16;

	/**
	 * Resolve a colour, preferring the preset attribute core writes first.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $slot       Colour slot: background, text, or link.
	 * @param string|null          $fallback   Value when the author chose nothing.
	 * @throws Invalid_Block_Attribute When an explicit value is not portable.
	 */
	public static function color( array $attributes, string $slot, ?string $fallback = null ): ?string {
		$preset_attribute = array(
			'background' => 'backgroundColor',
			'text'       => 'textColor',
			'link'       => 'linkColor',
		)[ $slot ] ?? null;

		if ( null !== $preset_attribute && isset( $attributes[ $preset_attribute ] ) ) {
			$slug = $attributes[ $preset_attribute ];

			if ( ! is_string( $slug ) || '' === $slug ) {
				throw new Invalid_Block_Attribute( $preset_attribute, 'must be a colour preset slug.' );
			}

			$resolved = Design_Presets::color( $slug );
			if ( null === $resolved ) {
				throw new Invalid_Block_Attribute( $preset_attribute, sprintf( 'is not a known colour preset: %s.', $slug ) );
			}

			return $resolved;
		}

		$value = self::style_value( $attributes, array( 'color', $slot ) );

		if ( null === $value ) {
			return $fallback;
		}

		$resolved = self::resolve_preset_reference( $value, 'color' );
		$path     = 'style.color.' . $slot;

		if ( null === $resolved ) {
			throw new Invalid_Block_Attribute( $path, sprintf( 'is not a known colour preset: %s.', $value ) );
		}

		if ( 1 !== preg_match( '/^#[0-9a-f]{6}$/i', $resolved ) ) {
			throw new Invalid_Block_Attribute( $path, 'must be a six-digit hexadecimal colour.' );
		}

		return $resolved;
	}

	/**
	 * Resolve a font size to whole pixels.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param int|null             $fallback   Value when the author chose nothing.
	 * @param int                  $minimum    Smallest portable size.
	 * @param int                  $maximum    Largest portable size.
	 * @throws Invalid_Block_Attribute When an explicit value is not portable.
	 */
	public static function font_size( array $attributes, ?int $fallback = null, int $minimum = 10, int $maximum = 72 ): ?int {
		$value = null;
		$path  = 'fontSize';

		if ( isset( $attributes['fontSize'] ) ) {
			$slug = $attributes['fontSize'];

			if ( ! is_string( $slug ) ) {
				throw new Invalid_Block_Attribute( 'fontSize', 'must be a font size preset slug.' );
			}

			$value = Design_Presets::font_size( $slug );
			if ( null === $value ) {
				throw new Invalid_Block_Attribute( 'fontSize', sprintf( 'is not a known font size preset: %s.', $slug ) );
			}
		} else {
			$value = self::style_value( $attributes, array( 'typography', 'fontSize' ) );
			$path  = 'style.typography.fontSize';
		}

		if ( null === $value ) {
			return $fallback;
		}

		return self::pixels( $value, $path, $minimum, $maximum );
	}

	/**
	 * Resolve a unitless line height.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param float|null           $fallback   Value when the author chose nothing.
	 * @throws Invalid_Block_Attribute When an explicit value is not portable.
	 */
	public static function line_height( array $attributes, ?float $fallback = null ): ?float {
		$value = self::style_value( $attributes, array( 'typography', 'lineHeight' ) );

		if ( null === $value ) {
			return $fallback;
		}

		if ( 1 !== preg_match( '/^\d+(\.\d+)?$/', $value ) ) {
			throw new Invalid_Block_Attribute( 'style.typography.lineHeight', 'must be a unitless number.' );
		}

		$line_height = (float) $value;
		if ( 0.5 > $line_height || 4.0 < $line_height ) {
			throw new Invalid_Block_Attribute( 'style.typography.lineHeight', 'must be between 0.5 and 4.' );
		}

		return $line_height;
	}

	/**
	 * Resolve one spacing group into whole pixels per side.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $group      Spacing group: padding or margin.
	 * @param array<string, int>   $fallback   Values when the author chose nothing.
	 * @param int                  $maximum    Largest portable value.
	 * @return array<string, int>
	 * @throws Invalid_Block_Attribute When an explicit value is not portable.
	 */
	public static function spacing( array $attributes, string $group, array $fallback, int $maximum = 96 ): array {
		$declared = self::style_value( $attributes, array( 'spacing', $group ) );
		$resolved = $fallback;

		if ( is_array( $declared ) ) {
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( ! isset( $declared[ $side ] ) ) {
					continue;
				}

				$resolved[ $side ] = self::pixels(
					$declared[ $side ],
					sprintf( 'style.spacing.%1$s.%2$s', $group, $side ),
					0,
					$maximum
				);
			}
		}

		return $resolved;
	}

	/**
	 * Read a value from the nested style tree.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param array<int, string>   $path       Nested keys below `style`.
	 * @return mixed Null when the branch is absent.
	 */
	private static function style_value( array $attributes, array $path ): mixed {
		$cursor = $attributes['style'] ?? null;

		foreach ( $path as $key ) {
			if ( ! is_array( $cursor ) || ! array_key_exists( $key, $cursor ) ) {
				return null;
			}

			$cursor = $cursor[ $key ];
		}

		return $cursor;
	}

	/**
	 * Expand a `var:preset|kind|slug` reference, or pass a literal through.
	 *
	 * @param mixed  $value Raw style value.
	 * @param string $kind  Preset kind to expand.
	 * @return string|null Null when the reference names an unknown preset.
	 */
	private static function resolve_preset_reference( mixed $value, string $kind ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$prefix = 'var:preset|' . $kind . '|';
		if ( ! str_starts_with( $value, $prefix ) ) {
			return $value;
		}

		$slug = substr( $value, strlen( $prefix ) );

		return 'color' === $kind
			? Design_Presets::color( $slug )
			: Design_Presets::spacing( $slug );
	}

	/**
	 * Convert a portable length to bounded whole pixels.
	 *
	 * @param mixed  $value   Raw length.
	 * @param string $path    Diagnostic attribute path.
	 * @param int    $minimum Smallest accepted value.
	 * @param int    $maximum Largest accepted value.
	 * @throws Invalid_Block_Attribute When the length is not portable.
	 */
	private static function pixels( mixed $value, string $path, int $minimum, int $maximum ): int {
		$resolved = self::resolve_preset_reference( $value, 'spacing' );

		if ( null === $resolved ) {
			throw new Invalid_Block_Attribute( $path, sprintf( 'is not a known spacing preset: %s.', is_string( $value ) ? $value : gettype( $value ) ) );
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			$pixels = (float) $value;
		} elseif ( 1 === preg_match( '/^(\d+(?:\.\d+)?)(px|rem|em)?$/', trim( $resolved ), $matches ) ) {
			$pixels = (float) $matches[1];

			if ( isset( $matches[2] ) && 'px' !== $matches[2] ) {
				$pixels *= self::PIXELS_PER_REM;
			}
		} else {
			throw new Invalid_Block_Attribute( $path, 'must be a length in px, rem, or em.' );
		}

		$rounded = (int) round( $pixels );

		if ( $minimum > $rounded || $maximum < $rounded ) {
			throw new Invalid_Block_Attribute( $path, sprintf( 'must resolve to %1$d through %2$d pixels.', $minimum, $maximum ) );
		}

		return $rounded;
	}
}
