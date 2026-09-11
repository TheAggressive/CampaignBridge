<?php
/**
 * Adapt resolved email design defaults to compiler block input.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Applies normalized design defaults without overriding authored attributes. */
final class Email_Design_Block_Defaults {
	/**
	 * Apply the resolved defaults for one block.
	 *
	 * @param Block_Node            $block  Parsed source block.
	 * @param Resolved_Email_Design $design Canonical runtime design.
	 */
	public function apply( Block_Node $block, Resolved_Email_Design $design ): Block_Node {
		$attributes = $block->attributes();
		$style      = $design->block_style( $block->name() );

		if ( 'campaignbridge/container' === $block->name() ) {
			$attributes = $this->default_attribute( $attributes, 'maxWidth', $design->content_width() );
			$global     = $design->global_style();
			$style      = $this->merge_styles( array( 'color' => $global['color'] ?? array() ), $style );
		}

		$attributes = $this->apply_color_defaults( $block->name(), $attributes, $style );
		$attributes = $this->apply_typography_defaults( $attributes, $style );
		$attributes = $this->apply_structural_defaults( $block->name(), $attributes, $style );

		return $block->with_attributes( $attributes );
	}

	/**
	 * Supply color defaults through the renderer's semantic attributes.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param array<string, mixed> $style      Resolved block style.
	 * @return array<string, mixed>
	 */
	private function apply_color_defaults( string $block_name, array $attributes, array $style ): array {
		$colors = is_array( $style['color'] ?? null ) ? $style['color'] : array();
		if ( isset( $colors['background'] ) && ! $this->has_style( $attributes, array( 'color', 'background' ) ) ) {
			$attributes = $this->default_attribute( $attributes, 'backgroundColor', $colors['background'] );
		}
		// PR 5 migrates these two legacy #333333 defaults to the semantic text
		// slot after editor/compiler parity fixtures are in place.
		$legacy_text_defaults = array( 'campaignbridge/text', 'campaignbridge/post-excerpt' );
		if ( isset( $colors['text'] ) && ! in_array( $block_name, $legacy_text_defaults, true ) && ! $this->has_style( $attributes, array( 'color', 'text' ) ) ) {
			$key        = 'campaignbridge/post-link' === $block_name ? 'linkColor' : 'textColor';
			$attributes = $this->default_attribute( $attributes, $key, $colors['text'] );
		}
		return $attributes;
	}

	/**
	 * Supply portable typography defaults.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param array<string, mixed> $style      Resolved block style.
	 * @return array<string, mixed>
	 */
	private function apply_typography_defaults( array $attributes, array $style ): array {
		$typography = is_array( $style['typography'] ?? null ) ? $style['typography'] : array();
		foreach ( array( 'fontFamily', 'fontSize' ) as $key ) {
			if ( isset( $typography[ $key ] ) && ! $this->has_style( $attributes, array( 'typography', $key ) ) ) {
				$attributes = $this->default_attribute( $attributes, $key, $typography[ $key ] );
			}
		}
		return $attributes;
	}

	/**
	 * Supply block-specific spacing and dimension defaults.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param array<string, mixed> $style      Resolved block style.
	 * @return array<string, mixed>
	 */
	private function apply_structural_defaults( string $block_name, array $attributes, array $style ): array {
		if ( isset( $style['spacing']['blockGap'] ) && 'campaignbridge/columns' === $block_name ) {
			$attributes = $this->default_attribute( $attributes, 'gap', $style['spacing']['blockGap'] );
		}
		if ( isset( $style['dimensions']['minHeight'] ) && 'campaignbridge/spacer' === $block_name ) {
			$attributes = $this->default_attribute( $attributes, 'height', $style['dimensions']['minHeight'] );
		}
		if ( isset( $style['border'] ) && 'campaignbridge/divider' === $block_name ) {
			$border     = $style['border'];
			$attributes = $this->default_attribute( $attributes, 'color', $border['color'] ?? null );
			$attributes = $this->default_attribute( $attributes, 'thickness', $border['width'] ?? null );
			$attributes = $this->default_attribute( $attributes, 'variant', $border['style'] ?? null );
		}
		return $attributes;
	}

	/**
	 * Set an omitted semantic attribute.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param string               $key        Attribute name.
	 * @param mixed                $value      Resolved default.
	 * @return array<string, mixed>
	 */
	private function default_attribute( array $attributes, string $key, mixed $value ): array {
		if ( null !== $value && ! array_key_exists( $key, $attributes ) ) {
			$attributes[ $key ] = $value;
		}
		return $attributes;
	}

	/**
	 * Determine whether an author supplied a native style value.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param array<int, string>   $path       Native style path.
	 */
	private function has_style( array $attributes, array $path ): bool {
		$cursor = $attributes['style'] ?? null;
		foreach ( $path as $key ) {
			if ( ! is_array( $cursor ) || ! array_key_exists( $key, $cursor ) ) {
				return false;
			}
			$cursor = $cursor[ $key ];
		}
		return true;
	}

	/**
	 * Recursively merge resolved style objects.
	 *
	 * @param array<string, mixed> $base    Lower-precedence styles.
	 * @param array<string, mixed> $overlay Higher-precedence styles.
	 * @return array<string, mixed>
	 */
	private function merge_styles( array $base, array $overlay ): array {
		foreach ( $overlay as $key => $value ) {
			$base[ $key ] = isset( $base[ $key ] ) && is_array( $base[ $key ] ) && is_array( $value )
				? $this->merge_styles( $base[ $key ], $value )
				: $value;
		}
		return $base;
	}
}
