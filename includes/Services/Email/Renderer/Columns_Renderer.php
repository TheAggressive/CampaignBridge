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
 * Renders a portable one- or two-column row.
 *
 * Each child column emits its own presentation cell, so this renderer owns only
 * the row table, the uniform cell padding derived from the gap, and the shared
 * vertical alignment handed to children through a binding.
 */
final class Columns_Renderer extends Abstract_Renderer {
	public const MAX_COLUMNS = 2;

	private const VERTICAL_ALIGNMENTS = array( 'top', 'middle', 'bottom' );

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/columns';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'gap', 'verticalAlign' );
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
		$attributes = $block->attributes();

		return $block->with_attributes(
			array(
				'gap'           => Renderer_Support::integer_attribute( $attributes, 'gap', 24, 0, 48 ),
				'verticalAlign' => Renderer_Support::choice_attribute( $attributes, 'verticalAlign', 'top', self::VERTICAL_ALIGNMENTS ),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Column widths are a sibling concern, so they are checked here rather than
	 * in the column renderer, which cannot see its neighbours.
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

		$declared = array();
		foreach ( $children as $child ) {
			$width = $child->attributes()['width'] ?? null;
			if ( null !== $width ) {
				$declared[] = $width;
			}
		}

		if ( array() === $declared ) {
			return array();
		}

		if ( count( $declared ) !== $count ) {
			return array(
				Compile_Diagnostic::error(
					'columns.width.partial',
					$block->path(),
					'Set an explicit width on every column or on none of them.'
				),
			);
		}

		$total = 0;
		foreach ( $declared as $width ) {
			$total += is_int( $width ) ? $width : 0;
		}

		if ( 100 !== $total ) {
			return array(
				Compile_Diagnostic::error(
					'columns.width.total',
					$block->path(),
					sprintf( 'Column widths must total 100 percent; found %d.', $total )
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

		return $context->with_binding(
			'columns',
			array(
				'count'         => count( $block->children() ),
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
		// Half the gap on each facing cell edge reproduces the requested gap
		// between columns, using an attribute desktop Outlook honours.
		$cell_padding = intdiv( $block->attributes()['gap'], 2 );

		return sprintf(
			'<table role="presentation" width="100%%" cellpadding="%1$d" cellspacing="0" border="0" style="width:100%%;border-collapse:collapse"><tr>%2$s</tr></table>',
			$cell_padding,
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
