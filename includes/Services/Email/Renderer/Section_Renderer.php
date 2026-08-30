<?php
/**
 * Native email section renderer.
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

/** Renders a portable full-width email content row. */
final class Section_Renderer extends Abstract_Renderer {
	private const DEFAULT_PADDING = array(
		'top'    => 24,
		'right'  => 0,
		'bottom' => 24,
		'left'   => 0,
	);

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/section';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'padding', 'backgroundColor' );
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
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = $block->attributes();

		return $block->with_attributes(
			array(
				'padding'         => Renderer_Support::spacing( $attributes['padding'] ?? null, self::DEFAULT_PADDING ),
				'backgroundColor' => Renderer_Support::color( $attributes['backgroundColor'] ?? null, '#ffffff' ),
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
		$padding    = $attributes['padding'];

		return sprintf(
			'<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" style="width:100%%;border-collapse:collapse;background-color:%1$s"><tr><td style="padding:%2$dpx %3$dpx %4$dpx %5$dpx">%6$s</td></tr></table>',
			$attributes['backgroundColor'],
			$padding['top'],
			$padding['right'],
			$padding['bottom'],
			$padding['left'],
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
