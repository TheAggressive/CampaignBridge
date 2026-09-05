<?php
/**
 * Portable slice of the active theme's colours.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Repository;

use CampaignBridge\Domain\Email\Portable_Color;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads theme.json through WordPress and keeps only email-portable colours.
 *
 * Theme palettes often wrap hex or oklch() in CSS custom properties. Those
 * are resolved here. WordPress's default rainbow is ignored when the theme
 * declared its own palette, so a failed resolve cannot import core cyan.
 */
final class Theme_Style_Reader {
	/**
	 * Extract the portable theme slice used to seed a brand kit.
	 *
	 * @return array{palette: array<int, array{slug: string, name: string, color: string}>, text: string|null, background: string|null, link: string|null, fingerprint: string}
	 */
	public function extract(): array {
		$settings = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings() : array();
		$styles   = function_exists( 'wp_get_global_styles' ) ? wp_get_global_styles() : array();

		return $this->extract_from(
			is_array( $settings ) ? $settings : array(),
			is_array( $styles ) ? $styles : array()
		);
	}

	/**
	 * Extract from already-loaded theme.json settings and styles.
	 *
	 * @param array<string, mixed> $settings Global settings.
	 * @param array<string, mixed> $styles   Global styles.
	 * @return array{palette: array<int, array{slug: string, name: string, color: string}>, text: string|null, background: string|null, link: string|null, fingerprint: string}
	 */
	public function extract_from( array $settings, array $styles ): array {
		$custom  = isset( $settings['custom'] ) && is_array( $settings['custom'] ) ? $settings['custom'] : array();
		$palette = $this->portable_palette( $settings['color']['palette'] ?? array(), $custom );

		$text       = $this->resolve_style_color( $styles['color']['text'] ?? null, $palette, $custom );
		$background = $this->resolve_style_color( $styles['color']['background'] ?? null, $palette, $custom );
		$link       = $this->resolve_style_color( $styles['elements']['link']['color']['text'] ?? null, $palette, $custom );

		return array(
			'palette'     => $palette,
			'text'        => $text,
			'background'  => $background,
			'link'        => $link,
			'fingerprint' => $this->fingerprint( $palette, $text, $background, $link ),
		);
	}

	/**
	 * Flatten origins and keep portable hex only.
	 *
	 * @param mixed                $palette Raw theme palette.
	 * @param array<string, mixed> $custom  theme.json custom tokens.
	 * @return array<int, array{slug: string, name: string, color: string}>
	 */
	private function portable_palette( mixed $palette, array $custom ): array {
		$items = array();

		foreach ( $this->flatten_palette( $palette ) as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['slug'], $item['color'] ) || ! is_string( $item['slug'] ) ) {
				continue;
			}

			$hex = $this->resolve_value( $item['color'], $items, $custom, array() );
			if ( null === $hex ) {
				continue;
			}

			$items[] = array(
				'slug'  => $item['slug'],
				'name'  => isset( $item['name'] ) && is_string( $item['name'] ) ? $item['name'] : $item['slug'],
				'color' => $hex,
			);
		}

		return $items;
	}

	/**
	 * Walk origin-keyed or flat palette tables.
	 *
	 * When the theme declared a palette, core defaults are ignored. Theme
	 * entries win over custom-origin entries.
	 *
	 * @param mixed $palette Raw palette.
	 * @return array<int, mixed>
	 */
	private function flatten_palette( mixed $palette ): array {
		if ( ! is_array( $palette ) ) {
			return array();
		}

		if ( $this->is_preset_list( $palette ) ) {
			return array_values( $palette );
		}

		$has_theme = isset( $palette['theme'] ) && is_array( $palette['theme'] ) && array() !== $palette['theme'];
		$origins   = $has_theme ? array( 'custom', 'theme' ) : array( 'default', 'custom' );
		$merged    = array();

		foreach ( $origins as $origin ) {
			if ( isset( $palette[ $origin ] ) && is_array( $palette[ $origin ] ) ) {
				$merged = array_merge( $merged, array_values( $palette[ $origin ] ) );
			}
		}

		return $merged;
	}

	/**
	 * Whether the table looks like a list of preset objects.
	 *
	 * @param array<int|string, mixed> $palette Palette table.
	 */
	private function is_preset_list( array $palette ): bool {
		$first = reset( $palette );

		return is_array( $first ) && array_key_exists( 'slug', $first );
	}

	/**
	 * Resolve a global-style colour to portable hex.
	 *
	 * @param mixed                                                        $value   Raw style value.
	 * @param array<int, array{slug: string, name: string, color: string}> $palette Portable palette.
	 * @param array<string, mixed>                                         $custom  Custom tokens.
	 */
	private function resolve_style_color( mixed $value, array $palette, array $custom ): ?string {
		return $this->resolve_value( $value, $palette, $custom, array() );
	}

	/**
	 * Resolve hex, oklch, rgb, hsl, or a CSS/theme.json variable.
	 *
	 * @param mixed                                                        $value   Raw colour.
	 * @param array<int, array{slug: string, name: string, color: string}> $palette Already-resolved presets.
	 * @param array<string, mixed>                                         $custom  Custom tokens.
	 * @param array<string, true>                                          $seen    Guards against cycles.
	 */
	private function resolve_value( mixed $value, array $palette, array $custom, array $seen ): ?string {
		$hex = Portable_Color::from( $value );
		if ( null !== $hex ) {
			return $hex;
		}

		if ( ! is_string( $value ) ) {
			return null;
		}

		$reference = $this->dereference( trim( $value ), $palette, $custom );
		if ( null === $reference || isset( $seen[ $reference['key'] ] ) ) {
			return null;
		}

		$seen[ $reference['key'] ] = true;

		return $this->resolve_value( $reference['value'], $palette, $custom, $seen );
	}

	/**
	 * Turn a CSS or theme.json variable into the next value to resolve.
	 *
	 * @param string                                                       $value   Raw variable.
	 * @param array<int, array{slug: string, name: string, color: string}> $palette Already-resolved presets.
	 * @param array<string, mixed>                                         $custom  Custom tokens.
	 * @return array{key: string, value: mixed}|null
	 */
	private function dereference( string $value, array $palette, array $custom ): ?array {
		if ( 1 === preg_match( '/^var\(--wp--custom--([a-z0-9-]+)\)$/i', $value, $matches ) ) {
			$path = explode( '--', strtolower( $matches[1] ) );

			return array(
				'key'   => 'custom:' . implode( '.', $path ),
				'value' => $this->custom_value( $custom, $path ),
			);
		}

		$slug = null;
		if ( 1 === preg_match( '/^var\(--wp--preset--color--([a-z0-9-]+)\)$/i', $value, $matches ) ) {
			$slug = strtolower( $matches[1] );
		} elseif ( str_starts_with( $value, 'var:preset|color|' ) ) {
			$slug = strtolower( substr( $value, strlen( 'var:preset|color|' ) ) );
		}

		if ( null === $slug ) {
			return null;
		}

		foreach ( $palette as $item ) {
			if ( $item['slug'] === $slug ) {
				return array(
					'key'   => 'preset:' . $slug,
					'value' => $item['color'],
				);
			}
		}

		return null;
	}

	/**
	 * Read a nested custom token.
	 *
	 * @param mixed              $cursor Current branch.
	 * @param array<int, string> $path   Remaining keys.
	 */
	private function custom_value( mixed $cursor, array $path ): mixed {
		foreach ( $path as $key ) {
			if ( ! is_array( $cursor ) || ! array_key_exists( $key, $cursor ) ) {
				return null;
			}

			$cursor = $cursor[ $key ];
		}

		return $cursor;
	}

	/**
	 * Hash the portable slice so a later import can see theme drift.
	 *
	 * @param array<int, array{slug: string, name: string, color: string}> $palette    Portable palette.
	 * @param string|null                                                  $text       Resolved text colour.
	 * @param string|null                                                  $background Resolved background colour.
	 * @param string|null                                                  $link       Resolved link colour.
	 */
	private function fingerprint( array $palette, ?string $text, ?string $background, ?string $link ): string {
		$payload = array(
			'palette'    => $palette,
			'text'       => $text,
			'background' => $background,
			'link'       => $link,
		);

		return hash( 'sha256', (string) wp_json_encode( $payload ) );
	}
}
