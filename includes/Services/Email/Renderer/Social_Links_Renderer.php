<?php
/**
 * Bounded Core Social Icons row renderer.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Abstract_Renderer;
use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Compile_Diagnostic;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Email_Block_Contract;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders a fixed-width row of accessible packaged social icons. */
final class Social_Links_Renderer extends Abstract_Renderer {
	public const MAX_ITEMS = 6;

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'core/social-links';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'align', 'gap', 'iconSize', 'openInNewTab', 'showLabels' );
	}

	/** {@inheritDoc} */
	public function allowed_children(): array {
		return Email_Block_Contract::children( $this->block_name() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Render context.
	 */
	public function validate( Block_Node $block, Render_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$count = count( $block->children() );
		if ( 1 > $count || self::MAX_ITEMS < $count ) {
			return array(
				Compile_Diagnostic::error(
					'social.items.invalid',
					$block->path(),
					'Social Icons must contain one through six links.'
				),
			);
		}

		return array();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Render context.
	 */
	public function context_for_children( Block_Node $block, Render_Context $context ): Render_Context {
		return $context->with_binding( 'social_links', $block->attributes() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Rendered child HTML.
	 * @param Render_Context $context  Render context.
	 */
	public function render_html( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$align = $block->attributes()['align'];

		return sprintf(
			'<div role="navigation" aria-label="Social links"><table role="presentation" cellpadding="0" cellspacing="0" border="0" align="%1$s" style="border-collapse:collapse"><tr>%2$s</tr></table></div>',
			$align,
			$children
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Rendered child text.
	 * @param Render_Context $context  Render context.
	 */
	public function render_text( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed,Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $children;
	}
}
