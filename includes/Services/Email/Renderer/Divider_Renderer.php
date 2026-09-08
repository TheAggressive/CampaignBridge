<?php
/**
 * Native email divider renderer.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Abstract_Renderer;
use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Render_Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders a bounded horizontal divider. */
final class Divider_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/divider';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'color', 'thickness', 'width', 'style', 'variant', 'borderColor' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = Native_Style_Support::attributes( $block );

		return $block->with_attributes(
			array(
				'nativeBorder' => $attributes['style']['border'] ?? array(),
				'color'        => Renderer_Support::string_attribute( $attributes, 'color', '#dddddd' ),
				'thickness'    => Renderer_Support::integer_attribute( $attributes, 'thickness', 1, 0, 8 ),
				'width'        => Renderer_Support::integer_attribute( $attributes, 'width', 100, 10, 100 ),
				'style'        => Renderer_Support::choice_attribute( $attributes, 'variant', 'solid', array( 'solid', 'dashed', 'dotted', 'none' ) ),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child HTML.
	 * @param Render_Context $context  Immutable scoped context.
	 */
	public function render_html( Block_Node $block, string $children, Render_Context $context ): string {
		$attributes = $block->attributes();
		$color      = Renderer_Support::resolve_color( $attributes['color'], Renderer_Support::brand_kit( $context ) );

		$edges = '';
		if ( isset( $attributes['nativeBorder']['width'] ) || isset( $attributes['nativeBorder']['style'] ) || isset( $attributes['nativeBorder']['color'] ) ) {
			$edges = sprintf( ';border:%dpx %s %s', $attributes['thickness'], $attributes['style'], $color );
		}
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			if ( ! isset( $attributes['nativeBorder'][ $side ] ) ) {
				continue; }
			$border = $attributes['nativeBorder'][ $side ];
			$width  = isset( $border['width'] ) ? \CampaignBridge\Domain\Email\Style_Resolver::length( $border['width'], 'style.border.' . $side . '.width', 0, 8 ) : $attributes['thickness'];
			$kind   = Renderer_Support::choice_attribute( $border, 'style', $attributes['style'], array( 'solid', 'dashed', 'dotted', 'none' ) );
			$paint  = isset( $border['color'] ) ? Renderer_Support::resolve_color( $border['color'], Renderer_Support::brand_kit( $context ) ) : $color;
			$edges .= sprintf( ';border-%s:%dpx %s %s', $side, $width, $kind, $paint );
		}

		return sprintf(
			'<table role="presentation" width="%1$d%%" align="center" cellpadding="0" cellspacing="0" border="0" style="width:%1$d%%;border-collapse:collapse"><tr><td style="border-top:%2$dpx %3$s %4$s%5$s;font-size:0;line-height:0">&nbsp;</td></tr></table>',
			$attributes['width'],
			$attributes['thickness'],
			$attributes['style'],
			$color,
			$edges
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child text.
	 * @param Render_Context $context  Immutable scoped context.
	 */
	public function render_text( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return '';
	}
}
