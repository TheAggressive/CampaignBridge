<?php
/**
 * Native Core buttons wrapper renderer.
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

/**
 * Groups Core buttons under one shared horizontal alignment.
 *
 * Each child button renders as its own bulletproof row, so a group of buttons
 * stacks vertically in email regardless of the editor's flex orientation.
 */
final class Buttons_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'core/buttons';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'align' );
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
		return $block->with_attributes( array( 'align' => Renderer_Support::alignment_attribute( $block->attributes(), 'align' ) ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable parent context.
	 */
	public function context_for_children( Block_Node $block, Render_Context $context ): Render_Context {
		return $context->with_binding( 'button_align', array( 'align' => $block->attributes()['align'] ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child HTML.
	 * @param Render_Context $context  Immutable scoped context.
	 */
	public function render_html( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $children;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child text.
	 * @param Render_Context $context  Immutable scoped context.
	 */
	public function render_text( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $children;
	}
}
