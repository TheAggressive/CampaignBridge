<?php
/**
 * Email root container renderer.
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

/** Renders the single email root and its bounded content cell. */
final class Container_Renderer extends Abstract_Renderer {
	private const DEFAULT_OUTER_PADDING = array(
		'top'    => 20,
		'right'  => 0,
		'bottom' => 20,
		'left'   => 0,
	);

	private const DEFAULT_INNER_PADDING = array(
		'top'    => 0,
		'right'  => 24,
		'bottom' => 0,
		'left'   => 24,
	);

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/container';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'maxWidth', 'outerPadding', 'padding', 'style' );
	}

	/** {@inheritDoc} */
	public function allowed_children(): array {
		return array( 'campaignbridge/section', 'campaignbridge/post-card' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = $block->attributes();
		$style      = is_array( $attributes['style'] ?? null ) ? $attributes['style'] : array();
		$colors     = is_array( $style['color'] ?? null ) ? $style['color'] : array();

		return $block->with_attributes(
			array(
				'maxWidth'        => Renderer_Support::integer( $attributes['maxWidth'] ?? null, 600, 320, 900 ),
				'outerPadding'    => Renderer_Support::spacing( $attributes['outerPadding'] ?? null, self::DEFAULT_OUTER_PADDING ),
				'padding'         => Renderer_Support::spacing( $attributes['padding'] ?? null, self::DEFAULT_INNER_PADDING ),
				'backgroundColor' => Renderer_Support::color( $colors['background'] ?? null, '#ffffff' ),
				'textColor'       => Renderer_Support::color( $colors['text'] ?? null, '#111111' ),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public function validate( Block_Node $block, Render_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( array() === $block->children() ) {
			return array(
				Compile_Diagnostic::warning(
					'container.empty',
					$block->path(),
					'The email container has no content.'
				),
			);
		}

		return array();
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
		$outer      = $attributes['outerPadding'];
		$inner      = $attributes['padding'];
		$width      = $attributes['maxWidth'];
		$background = $attributes['backgroundColor'];
		$text       = $attributes['textColor'];

		return sprintf(
			'<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" style="width:100%%;border-collapse:collapse;background-color:%1$s;color:%2$s"><tr><td align="center" style="padding:%3$dpx %4$dpx %5$dpx %6$dpx"><table role="presentation" class="cb-email-container" width="%7$d" cellpadding="0" cellspacing="0" border="0" style="width:%7$dpx;max-width:100%%;border-collapse:collapse;background-color:%1$s;color:%2$s"><tr><td class="cb-email-cell" style="padding:%8$dpx %9$dpx %10$dpx %11$dpx">%12$s</td></tr></table></td></tr></table>',
			$background,
			$text,
			$outer['top'],
			$outer['right'],
			$outer['bottom'],
			$outer['left'],
			$width,
			$inner['top'],
			$inner['right'],
			$inner['bottom'],
			$inner['left'],
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
		return $children;
	}
}
