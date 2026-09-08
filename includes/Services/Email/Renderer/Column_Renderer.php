<?php
/**
 * Native email column renderer.
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

/**
 * Renders one constrained cell of a columns row.
 *
 * A parent renderer receives its children already concatenated, so the cell is
 * emitted here rather than by the columns renderer. Width defaults to an even
 * share of the row, read from the binding the parent supplies.
 */
final class Column_Renderer extends Abstract_Renderer {
	private const MIN_WIDTH = 1;
	private const MAX_WIDTH = 100;

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/column';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'width', 'backgroundColor', 'style' );
	}

	/** {@inheritDoc} */
	public function allowed_children(): array {
		return array(
			'campaignbridge/text',
			'campaignbridge/heading',
			'campaignbridge/image',
			'campaignbridge/button',
			'campaignbridge/divider',
			'campaignbridge/spacer',
			'campaignbridge/post-card',
			'campaignbridge/post-image',
			'campaignbridge/post-title',
			'campaignbridge/post-excerpt',
			'campaignbridge/post-button',
			'campaignbridge/post-link',
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * An omitted width stays null so the even share can be resolved at render
	 * time, when the sibling count is known.
	 *
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = Native_Style_Support::attributes( $block );

		return $block->with_attributes(
			array(
				'style'           => $attributes['style'],
				'width'           => array_key_exists( 'width', $attributes )
					? Renderer_Support::integer_attribute( $attributes, 'width', 50, self::MIN_WIDTH, self::MAX_WIDTH )
					: null,
				// Omitted stays null so a column never paints over its section.
				'backgroundColor' => array_key_exists( 'backgroundColor', $attributes )
					? Renderer_Support::string_attribute( $attributes, 'backgroundColor', '#ffffff' )
					: null,
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
		$binding    = $context->binding( 'columns' ) ?? array();
		$count      = max( 1, (int) ( $binding['count'] ?? 1 ) );
		$align      = (string) ( $binding['verticalAlign'] ?? 'top' );
		$width      = $binding['widths'][ $block->path() ] ?? ( $attributes['width'] ?? 100 / $count );

		$spacing = '';
		if ( $count > 1 && ( $binding['gap'] ?? 0 ) > 0 ) {
			$gap     = (int) $binding['gap'];
			$left    = $block->path() === ( $binding['firstPath'] ?? '' ) ? 0 : $gap - intdiv( $gap, 2 );
			$right   = $block->path() === ( $binding['lastPath'] ?? '' ) ? 0 : intdiv( $gap, 2 );
			$spacing = ( $left ? ';padding-left:' . $left . 'px' : '' ) . ( $right ? ';padding-right:' . $right . 'px' : '' );
		}
		$width = rtrim( rtrim( number_format( (float) $width, 4, '.', '' ), '0' ), '.' );
		if ( null !== $attributes['backgroundColor'] ) {
			$children = sprintf(
				'<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" style="width:100%%;border-collapse:collapse;background-color:%1$s"><tr><td>%2$s</td></tr></table>',
				Renderer_Support::resolve_color( $attributes['backgroundColor'], Renderer_Support::brand_kit( $context ) ),
				$children
			);
		}

		return sprintf(
			'<td class="cb-col" valign="%1$s" width="%2$s%%" style="width:%2$s%%;vertical-align:%1$s;overflow-wrap:break-word%3$s">%4$s</td>',
			$align,
			$width,
			$spacing,
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
		$value = trim( $children );

		return '' === $value ? '' : $value . "\n\n";
	}
}
