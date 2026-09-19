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
use CampaignBridge\Domain\Email\Style_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders a bounded horizontal divider. */
final class Divider_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'core/separator';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'color', 'thickness', 'variant' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * The Core separator supplies only its colour; thickness and line style come
	 * from the email design's divider border.
	 *
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = Native_Style_Support::attributes( $block );

		return $block->with_attributes(
			array(
				'color'     => Renderer_Support::string_attribute( $attributes, 'color', '#dddddd' ),
				'thickness' => Style_Resolver::length( $attributes['thickness'] ?? 1, 'thickness', 0, 8 ),
				'style'     => Renderer_Support::choice_attribute( $attributes, 'variant', 'solid', array( 'solid', 'dashed', 'dotted', 'none' ) ),
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
	public function render_html( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		$attributes = $block->attributes();
		$color      = Renderer_Support::resolve_color( $attributes['color'], Renderer_Support::brand_kit( $context ) );

		return sprintf(
			'<table role="presentation" width="100%%" align="center" cellpadding="0" cellspacing="0" border="0" style="width:100%%;border-collapse:collapse"><tr><td style="border-top:%1$dpx %2$s %3$s;font-size:0;line-height:0">&nbsp;</td></tr></table>',
			$attributes['thickness'],
			$attributes['style'],
			$color
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
