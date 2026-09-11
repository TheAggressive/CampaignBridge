<?php
/**
 * Canonical email design normalization.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Converts validated v1 data and Brand Kit identity to typed runtime values. */
final class Email_Design_Normalizer {
	/**
	 * Normalize validated v1 input.
	 *
	 * @param array<string, mixed> $manifest Validated v1 manifest.
	 * @param Brand_Kit            $brand_kit Active brand identity.
	 * @return array<string, mixed>
	 */
	public function normalize( array $manifest, Brand_Kit $brand_kit ): array {
		$settings = $manifest['settings'];
		$colors   = $this->colors( $settings['color']['palette'], $brand_kit );
		$fonts    = $this->fonts( $settings['typography']['fontFamilies'], $brand_kit );
		$sizes    = $this->sizes( $settings['typography']['fontSizes'], 'design.invalid_font' );
		$spacing  = $this->sizes( $settings['spacing']['spacingSizes'], 'design.invalid_spacing' );
		$catalogs = array(
			'color'       => array_column( $colors, 'color', 'slug' ),
			'font-family' => array_column( $fonts, 'family', 'slug' ),
			'font-size'   => array_column( $sizes, 'size', 'slug' ),
			'spacing'     => array_column( $spacing, 'size', 'slug' ),
		);

		return array(
			'version'  => 1,
			'settings' => array(
				'layout'     => array( 'contentWidth' => $this->pixels( $settings['layout']['contentWidth'] ) ),
				'color'      => array(
					'custom'  => false,
					'palette' => $colors,
				),
				'typography' => array(
					'customFontSize' => false,
					'fontFamilies'   => $fonts,
					'fontSizes'      => $sizes,
				),
				'spacing'    => array(
					'custom'       => false,
					'spacingSizes' => $spacing,
				),
			),
			'styles'   => array(
				'global' => $this->resolve_references(
					array(
						'color'      => $manifest['styles']['color'],
						'typography' => $manifest['styles']['typography'],
					),
					$catalogs,
					'$.styles'
				),
				'blocks' => $this->resolve_references( $manifest['styles']['blocks'], $catalogs, '$.styles.blocks' ),
			),
			'brand'    => array( 'fonts' => $brand_kit->fonts() ),
		);
	}

	/**
	 * Apply Brand Kit identity to declared color slots.
	 *
	 * @param array<int, array<string, mixed>> $declared Manifest palette.
	 * @param Brand_Kit                        $brand_kit Active brand identity.
	 * @return array<int, array<string, mixed>>
	 */
	private function colors( array $declared, Brand_Kit $brand_kit ): array {
		$colors = $this->unique_by_slug( $declared );
		foreach ( $colors as &$preset ) {
			$identity        = $brand_kit->color( $preset['slug'] );
			$preset['color'] = null !== $identity ? $identity : strtolower( $preset['color'] );
		}
		unset( $preset );
		return $colors;
	}

	/**
	 * Resolve manifest font selections through the curated catalog.
	 *
	 * @param array<int, array<string, mixed>> $declared Manifest font selections.
	 * @param Brand_Kit                        $brand_kit Active brand identity.
	 * @return array<int, array<string, mixed>>
	 * @throws Email_Design_Error When a font is not curated.
	 */
	private function fonts( array $declared, Brand_Kit $brand_kit ): array {
		$fonts = array();
		foreach ( $this->unique_by_slug( $declared ) as $selection ) {
			$font = Design_Presets::font( $selection['slug'] );
			if ( null === $font ) {
				throw new Email_Design_Error( 'design.invalid_font', '$.settings.typography.fontFamilies', 'Email design references an unknown curated font.' );
			}
			$fonts[] = array_merge( $font, array( 'name' => $selection['name'] ) );
		}

		$custom = $brand_kit->custom_font();
		if ( null !== $custom ) {
			$fonts[] = array_merge(
				$custom,
				array(
					'slug' => Brand_Kit::CUSTOM_FONT_SLUG,
					'type' => 'web',
				)
			);
		}
		return $fonts;
	}

	/**
	 * Convert size presets to whole pixels.
	 *
	 * @param array<int, array<string, mixed>> $declared Preset definitions.
	 * @param string                           $code     Diagnostic code.
	 * @return array<int, array<string, mixed>>
	 * @throws Email_Design_Error When a size is unsafe.
	 */
	private function sizes( array $declared, string $code ): array {
		$sizes = $this->unique_by_slug( $declared );
		foreach ( $sizes as &$preset ) {
			$preset['size'] = $this->pixels( $preset['size'] );
			if ( 0 > $preset['size'] ) {
				throw new Email_Design_Error( $code, '$.settings', 'Email design size must be non-negative.' );
			}
		}
		unset( $preset );
		return $sizes;
	}

	/**
	 * Reject order-dependent duplicate preset slugs.
	 *
	 * @param array<int, array<string, mixed>> $items Preset definitions.
	 * @return array<int, array<string, mixed>>
	 * @throws Email_Design_Error When a slug is duplicated.
	 */
	private function unique_by_slug( array $items ): array {
		$seen = array();
		foreach ( $items as $item ) {
			$slug = $item['slug'];
			if ( isset( $seen[ $slug ] ) ) {
				throw new Email_Design_Error( 'design.invalid_property', '$.settings', 'Email design preset slugs must be unique within a catalog.' );
			}
			$seen[ $slug ] = true;
		}
		return $items;
	}

	/**
	 * Recursively resolve semantic preset references.
	 *
	 * @param mixed                               $value    Value to normalize.
	 * @param array<string, array<string, mixed>> $catalogs Typed lookup maps.
	 * @param string                              $path     Safe manifest path.
	 * @return mixed
	 * @throws Email_Design_Error When a reference cannot resolve.
	 */
	private function resolve_references( mixed $value, array $catalogs, string $path ): mixed {
		if ( is_string( $value ) && str_starts_with( $value, 'var:preset|' ) ) {
			$parts = explode( '|', $value );
			if ( 3 !== count( $parts ) || ! isset( $catalogs[ $parts[1] ][ $parts[2] ] ) ) {
				throw new Email_Design_Error( 'design.unresolved_preset', $path, 'Email design contains an unresolved preset reference.' );
			}
			return $catalogs[ $parts[1] ][ $parts[2] ];
		}
		if ( is_string( $value ) && 1 === preg_match( '/^(?:0|[1-9][0-9]{0,2})px$/', $value ) ) {
			return $this->pixels( $value );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = $this->resolve_references( $item, $catalogs, $path . '.' . (string) $key );
		}
		return $value;
	}

	/**
	 * Convert a schema-validated pixel length to an integer.
	 *
	 * @param string $value Pixel length.
	 */
	private function pixels( string $value ): int {
		return (int) substr( $value, 0, -2 );
	}
}
