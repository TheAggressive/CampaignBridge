<?php
/**
 * Native email columns renderer.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Abstract_Renderer;
use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Compile_Diagnostic;
use CampaignBridge\Domain\Email\Render_Context;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a portable one- through six-column row.
 *
 * Each child column emits its own presentation cell, so this renderer owns only
 * the row table, the gap between facing cell edges, and the shared
 * vertical alignment handed to children through a binding.
 */
final class Columns_Renderer extends Abstract_Renderer {
	public const MAX_COLUMNS = 6;

	private const VERTICAL_ALIGNMENTS = array( 'top', 'middle', 'bottom' );

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/columns';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'gap', 'verticalAlign', 'style', 'layout', 'isStackedOnMobile' );
	}

	/** {@inheritDoc} */
	public function allowed_children(): array {
		return array( 'campaignbridge/column' );
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
				'isStackedOnMobile' => Renderer_Support::boolean_attribute( $attributes, 'isStackedOnMobile', true ),
				'gap'               => Renderer_Support::integer_attribute( $attributes, 'gap', 24, 0, 48 ),
				'verticalAlign'     => Renderer_Support::choice_attribute( $attributes, 'verticalAlign', 'top', self::VERTICAL_ALIGNMENTS ),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Validate the supported number of columns before distributing widths.
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 * @return array<int, Compile_Diagnostic>
	 */
	public function validate( Block_Node $block, Render_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$children = $block->children();
		$count    = count( $children );

		if ( 1 > $count || self::MAX_COLUMNS < $count ) {
			return array(
				Compile_Diagnostic::error(
					'columns.count.invalid',
					$block->path(),
					sprintf( 'A columns block requires 1 through %d column blocks.', self::MAX_COLUMNS )
				),
			);
		}

		return array();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized parent block.
	 * @param Render_Context $context Immutable parent context.
	 */
	public function context_for_children( Block_Node $block, Render_Context $context ): Render_Context {
		$attributes = $block->attributes();

		$widths    = array_map( static fn( Block_Node $child ) => isset( $child->attributes()['width'] ) ? Renderer_Support::integer_attribute( $child->attributes(), 'width', 50, 1, 100 ) : null, $block->children() );
		$total     = array_sum( $widths );
		$automatic = count( array_filter( $widths, static fn( $width ) => null === $width ) );
		$share     = $automatic > 0 ? max( 0, 100 - $total ) / $automatic : 0;
		if ( $automatic > 0 && 0 >= $share ) {
			$share = 100 / count( $widths );
		}
		$weights  = array_map( static fn( $width ) => $width ?? $share, $widths );
		$sum      = array_sum( $weights );
		$resolved = array();
		foreach ( $block->children() as $index => $child ) {
			$resolved[ $child->path() ] = $sum > 0 ? 100 * $weights[ $index ] / $sum : 100 / count( $widths );
		}
		return $context->with_binding(
			'columns',
			array(
				'count'         => count( $block->children() ),
				'gap'           => $attributes['gap'],
				'firstPath'     => $block->children()[0]->path(),
				'lastPath'      => $block->children()[ count( $block->children() ) - 1 ]->path(),
				'widths'        => $resolved,
				'verticalAlign' => $attributes['verticalAlign'],
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
	public function render_html( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$attributes = $block->attributes();
		$class      = $attributes['isStackedOnMobile'] ? 'cb-columns-stack cb-columns-gap-' . $attributes['gap'] : 'cb-columns';
		$responsive = $attributes['isStackedOnMobile'] ? sprintf( '<style>@media only screen and (max-width:480px){.cb-columns-gap-%1$d>tbody>tr>.cb-col+.cb-col{padding-top:%1$dpx!important}}</style>', $attributes['gap'] ) : '';
		return $responsive . sprintf(
			'<table role="presentation" class="%1$s" width="100%%" cellpadding="0" cellspacing="0" border="0" style="width:100%%;table-layout:fixed;border-collapse:collapse"><tr>%2$s</tr></table>',
			$class,
			$children
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
		return trim( $children ) . "\n\n";
	}
}
