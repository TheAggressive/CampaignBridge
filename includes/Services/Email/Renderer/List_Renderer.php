<?php
/**
 * Native Core list renderer.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Abstract_Renderer;
use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Domain\Email\Style_Resolver;
use CampaignBridge\Services\Email\Email_Block_Contract;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders a constrained Core unordered or ordered list. */
final class List_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'core/list';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'ordered', 'style' );
	}

	/** {@inheritDoc} */
	public function allowed_children(): array {
		return Email_Block_Contract::children( $this->block_name() );
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
				'ordered' => Renderer_Support::boolean_attribute( $attributes, 'ordered', false ),
				'style'   => $attributes['style'],
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
		$tag        = $attributes['ordered'] ? 'ol' : 'ul';
		// Unset sides keep the default marker indent.
		$padding = Style_Resolver::spacing(
			array( 'style' => $attributes['style'] ),
			'padding',
			array(
				'top'    => 0,
				'right'  => 0,
				'bottom' => 0,
				'left'   => 24,
			)
		);
		$padding = 0 === $padding['top'] && 0 === $padding['right'] && 0 === $padding['bottom']
			? sprintf( 'padding:0 0 0 %dpx', $padding['left'] )
			: Box_Style::spacing_css( 'padding', $padding );

		return Box_Style::with_margin( sprintf( '<%1$s style="margin:0;%2$s">%3$s</%1$s>', $tag, $padding, $children ), $attributes['style'] );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child text.
	 * @param Render_Context $context  Immutable scoped context.
	 */
	public function render_text( Block_Node $block, string $children, Render_Context $context ): string {
		$lines = array_values( array_filter( explode( "\n", trim( $children ) ), static fn ( string $line ): bool => '' !== trim( $line ) ) );
		foreach ( $lines as $index => &$line ) {
			$line = $block->attributes()['ordered'] ? (string) ( $index + 1 ) . '. ' . $line : '- ' . $line;
		}
		unset( $line );

		return array() === $lines ? '' : implode( "\n", $lines ) . "\n";
	}
}
