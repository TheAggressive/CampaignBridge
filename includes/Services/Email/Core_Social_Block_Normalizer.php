<?php
/**
 * Bounded normalization for WordPress Core Social Icons blocks.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email;

use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Invalid_Block_Attribute;
use CampaignBridge\Domain\Email\Style_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reads the known Core Social Icons serialization contract. */
final class Core_Social_Block_Normalizer {
	/**
	 * Normalize a Social Icons parent into one bounded email row.
	 *
	 * @param Block_Node           $block      Source block.
	 * @param array<string, mixed> $attributes Known Core attributes.
	 * @return Block_Node Normalized block.
	 * @throws Invalid_Block_Attribute When Core serialization is unsupported.
	 */
	public static function links( Block_Node $block, array $attributes ): Block_Node {
		$html = trim( $block->inner_html() );
		if ( '' !== $html && 1 !== preg_match( '/^<ul\b[^>]*>\s*<\/ul>$/i', $html ) ) {
			throw new Invalid_Block_Attribute( 'style', 'must use the known Core Social Icons wrapper.' );
		}

		$layout = self::layout( $attributes );
		$align  = $attributes['align'] ?? $layout['justifyContent'] ?? 'left';
		if ( ! is_string( $align ) || ! in_array( $align, array( 'left', 'center', 'right' ), true ) ) {
			throw new Invalid_Block_Attribute( 'align', 'must be left, center, or right in email.' );
		}
		if ( isset( $attributes['align'], $layout['justifyContent'] ) && $attributes['align'] !== $layout['justifyContent'] ) {
			throw new Invalid_Block_Attribute( 'layout.justifyContent', 'must match the Social Icons alignment.' );
		}

		$style = $attributes['style'] ?? array();
		if ( ! is_array( $style ) ) {
			throw new Invalid_Block_Attribute( 'style', 'must be a native WordPress style object.' );
		}
		$gap = self::gap( $style );

		$open_in_new_tab = $attributes['openInNewTab'] ?? false;
		$show_labels     = $attributes['showLabels'] ?? false;
		if ( ! is_bool( $open_in_new_tab ) ) {
			throw new Invalid_Block_Attribute( 'openInNewTab', 'must be a boolean.' );
		}
		if ( ! is_bool( $show_labels ) ) {
			throw new Invalid_Block_Attribute( 'showLabels', 'must be a boolean.' );
		}

		$size_map = array(
			''                     => 24,
			'has-small-icon-size'  => 18,
			'has-normal-icon-size' => 24,
			'has-large-icon-size'  => 36,
			'has-huge-icon-size'   => 48,
		);
		$size     = $attributes['size'] ?? '';
		if ( ! is_string( $size ) || ! isset( $size_map[ $size ] ) ) {
			throw new Invalid_Block_Attribute( 'size', 'must be a supported Core icon size.' );
		}
		self::style( $attributes['className'] ?? '' );

		return $block->with_attributes(
			array(
				'align'        => $align,
				'gap'          => $gap,
				'iconSize'     => $size_map[ $size ],
				'openInNewTab' => $open_in_new_tab,
				'showLabels'   => $show_labels,
			)
		);
	}

	/**
	 * Normalize one dynamic Social Icon child.
	 *
	 * @param Block_Node            $block      Source block.
	 * @param array<string, string> $attributes Known string attributes.
	 * @return Block_Node Normalized block.
	 * @throws Invalid_Block_Attribute When static child markup is present.
	 */
	public static function link( Block_Node $block, array $attributes ): Block_Node {
		if ( '' !== trim( $block->inner_html() ) ) {
			throw new Invalid_Block_Attribute( 'url', 'must use Core Social Icon dynamic serialization.' );
		}

		return $block->with_attributes( $attributes );
	}

	/**
	 * Validate Core's horizontal layout object.
	 *
	 * @param array<string, mixed> $attributes Known Core attributes.
	 * @return array<string, mixed> Validated layout.
	 * @throws Invalid_Block_Attribute When the layout is unsupported.
	 */
	private static function layout( array $attributes ): array {
		$layout = $attributes['layout'] ?? array();
		if ( ! is_array( $layout ) ) {
			throw new Invalid_Block_Attribute( 'layout', 'must be a Core flex layout.' );
		}
		foreach ( array_keys( $layout ) as $key ) {
			if ( ! in_array( $key, array( 'type', 'justifyContent', 'orientation', 'flexWrap' ), true ) ) {
				throw new Invalid_Block_Attribute( 'layout.' . $key, 'is not supported by email social links.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
			}
		}
		if ( 'flex' !== ( $layout['type'] ?? 'flex' ) || 'horizontal' !== ( $layout['orientation'] ?? 'horizontal' ) ) {
			throw new Invalid_Block_Attribute( 'layout', 'must be a horizontal flex layout.' );
		}
		if ( isset( $layout['flexWrap'] ) && 'nowrap' !== $layout['flexWrap'] ) {
			throw new Invalid_Block_Attribute( 'layout.flexWrap', 'must be nowrap for email social links.' );
		}

		return $layout;
	}

	/**
	 * Resolve the only supported spacing value.
	 *
	 * @param array<string, mixed> $style Core style object.
	 * @throws Invalid_Block_Attribute When spacing is unsupported.
	 */
	private static function gap( array $style ): int {
		if ( array() === $style ) {
			return 8;
		}
		if ( array() !== array_diff( array_keys( $style ), array( 'spacing' ) )
			|| ! is_array( $style['spacing'] ?? null )
			|| array() !== array_diff( array_keys( $style['spacing'] ), array( 'blockGap' ) ) ) {
			throw new Invalid_Block_Attribute( 'style', 'supports only a horizontal block gap for email social links.' );
		}

		return Style_Resolver::length( $style['spacing']['blockGap'] ?? 8, 'style.spacing.blockGap', 0, 32 );
	}

	/**
	 * Accept Core's default or logos-only visual style.
	 *
	 * @param mixed $class_name Core custom class attribute.
	 * @throws Invalid_Block_Attribute When the style is unsupported.
	 */
	private static function style( mixed $class_name ): void {
		if ( ! is_string( $class_name ) || ! in_array( trim( $class_name ), array( '', 'is-style-default', 'is-style-logos-only' ), true ) ) {
			throw new Invalid_Block_Attribute( 'className', 'must name the logos-only Social Icons style.' );
		}
	}
}
