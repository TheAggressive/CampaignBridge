<?php
/**
 * Email design presets.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The palette, type scale, and spacing scale email templates are built from.
 *
 * These are deliberately CampaignBridge's own rather than the site theme's.
 * A theme palette is designed for a browser with a stylesheet; an email has
 * neither, and every value here has to survive being inlined into a table
 * cell. Keeping one definition in PHP means the editor offers exactly the
 * values the compiler can resolve.
 */
final class Design_Presets {
	/**
	 * Colour presets.
	 *
	 * @var array<int, array{slug: string, name: string, color: string}>
	 */
	private const COLORS = array(
		array(
			'slug'  => 'foreground',
			'name'  => 'Foreground',
			'color' => '#111111',
		),
		array(
			'slug'  => 'muted',
			'name'  => 'Muted',
			'color' => '#666666',
		),
		array(
			'slug'  => 'background',
			'name'  => 'Background',
			'color' => '#ffffff',
		),
		array(
			'slug'  => 'surface',
			'name'  => 'Surface',
			'color' => '#f4f4f4',
		),
		array(
			'slug'  => 'border',
			'name'  => 'Border',
			'color' => '#e0e0e0',
		),
		array(
			'slug'  => 'accent',
			'name'  => 'Accent',
			'color' => '#1a6dcc',
		),
		array(
			'slug'  => 'accent-contrast',
			'name'  => 'Accent contrast',
			'color' => '#ffffff',
		),
	);

	/**
	 * Font size presets, in pixels.
	 *
	 * @var array<int, array{slug: string, name: string, size: string}>
	 */
	private const FONT_SIZES = array(
		array(
			'slug' => 'small',
			'name' => 'Small',
			'size' => '14px',
		),
		array(
			'slug' => 'medium',
			'name' => 'Medium',
			'size' => '16px',
		),
		array(
			'slug' => 'large',
			'name' => 'Large',
			'size' => '20px',
		),
		array(
			'slug' => 'x-large',
			'name' => 'Extra large',
			'size' => '28px',
		),
		array(
			'slug' => 'xx-large',
			'name' => 'Huge',
			'size' => '36px',
		),
	);

	/**
	 * Spacing presets, in pixels.
	 *
	 * Numeric slugs match the convention core uses for spacing scales, so the
	 * editor renders them as an ordered scale rather than arbitrary names.
	 *
	 * @var array<int, array{slug: string, name: string, size: string}>
	 */
	private const SPACING_SIZES = array(
		array(
			'slug' => '20',
			'name' => '1',
			'size' => '8px',
		),
		array(
			'slug' => '30',
			'name' => '2',
			'size' => '12px',
		),
		array(
			'slug' => '40',
			'name' => '3',
			'size' => '16px',
		),
		array(
			'slug' => '50',
			'name' => '4',
			'size' => '24px',
		),
		array(
			'slug' => '60',
			'name' => '5',
			'size' => '32px',
		),
		array(
			'slug' => '70',
			'name' => '6',
			'size' => '48px',
		),
		array(
			'slug' => '80',
			'name' => '7',
			'size' => '64px',
		),
	);

	/**
	 * Get the colour presets.
	 *
	 * @return array<int, array{slug: string, name: string, color: string}>
	 */
	public static function colors(): array {
		return self::COLORS;
	}

	/**
	 * Get the font size presets.
	 *
	 * @return array<int, array{slug: string, name: string, size: string}>
	 */
	public static function font_sizes(): array {
		return self::FONT_SIZES;
	}

	/**
	 * Get the spacing presets.
	 *
	 * @return array<int, array{slug: string, name: string, size: string}>
	 */
	public static function spacing_sizes(): array {
		return self::SPACING_SIZES;
	}

	/**
	 * Resolve a colour preset slug to its portable value.
	 *
	 * @param string $slug Preset slug.
	 */
	public static function color( string $slug ): ?string {
		foreach ( self::COLORS as $preset ) {
			if ( $preset['slug'] === $slug ) {
				return $preset['color'];
			}
		}

		return null;
	}

	/**
	 * Resolve a font size preset slug to its declared size.
	 *
	 * @param string $slug Preset slug.
	 */
	public static function font_size( string $slug ): ?string {
		return self::size_of( self::FONT_SIZES, $slug );
	}

	/**
	 * Resolve a spacing preset slug to its declared size.
	 *
	 * @param string $slug Preset slug.
	 */
	public static function spacing( string $slug ): ?string {
		return self::size_of( self::SPACING_SIZES, $slug );
	}

	/**
	 * Look up a size in a preset table.
	 *
	 * @param array<int, array{slug: string, name: string, size: string}> $presets Preset table.
	 * @param string                                                      $slug    Preset slug.
	 */
	private static function size_of( array $presets, string $slug ): ?string {
		foreach ( $presets as $preset ) {
			if ( $preset['slug'] === $slug ) {
				return $preset['size'];
			}
		}

		return null;
	}
}
