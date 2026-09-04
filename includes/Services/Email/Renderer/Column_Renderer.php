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
	private const MIN_WIDTH = 20;
	private const MAX_WIDTH = 80;

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/column';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'width', 'backgroundColor' );
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
		$attributes = $block->attributes();

		return $block->with_attributes(
			array(
				'width'           => array_key_exists( 'width', $attributes )
					? Renderer_Support::integer_attribute( $attributes, 'width', 50, self::MIN_WIDTH, self::MAX_WIDTH )
					: null,
				// Omitted stays null so a column never paints over its section.
				'backgroundColor' => array_key_exists( 'backgroundColor', $attributes )
					? Renderer_Support::color_attribute( $attributes, 'backgroundColor', '#ffffff' )
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
		$width      = $attributes['width'] ?? intdiv( 100, $count );

		$background = null === $attributes['backgroundColor']
			? ''
			: sprintf( ';background-color:%s', $attributes['backgroundColor'] );

		return sprintf(
			'<td class="cb-col" valign="%1$s" width="%2$d%%" style="width:%2$d%%;vertical-align:%1$s%3$s">%4$s</td>',
			$align,
			$width,
			$background,
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
