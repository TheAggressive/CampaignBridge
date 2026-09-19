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
		return array( 'ordered' );
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
		return $block->with_attributes( array( 'ordered' => Renderer_Support::boolean_attribute( $block->attributes(), 'ordered', false ) ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child HTML.
	 * @param Render_Context $context  Immutable scoped context.
	 */
	public function render_html( Block_Node $block, string $children, Render_Context $context ): string {
		$tag = $block->attributes()['ordered'] ? 'ol' : 'ul';

		return sprintf( '<%1$s style="margin:0;padding:0 0 0 24px">%2$s</%1$s>', $tag, $children );
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
