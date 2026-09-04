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
		return array(
			'campaignbridge/preheader',
			'campaignbridge/section',
			'campaignbridge/post-card',
			'campaignbridge/compliance-footer',
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = $block->attributes();
		$style      = Renderer_Support::object_attribute( $attributes, 'style', array() );
		$colors     = Renderer_Support::object_attribute( $style, 'color', array(), 'style.color' );

		return $block->with_attributes(
			array(
				'maxWidth'        => Renderer_Support::integer_attribute( $attributes, 'maxWidth', 600, 320, 900 ),
				'outerPadding'    => Renderer_Support::spacing_attribute( $attributes, 'outerPadding', self::DEFAULT_OUTER_PADDING ),
				'padding'         => Renderer_Support::spacing_attribute( $attributes, 'padding', self::DEFAULT_INNER_PADDING ),
				'backgroundColor' => Renderer_Support::color_attribute( $colors, 'background', '#ffffff', 'style.color.background' ),
				'textColor'       => Renderer_Support::color_attribute( $colors, 'text', '#111111', 'style.color.text' ),
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
		$children = $block->children();

		if ( array() === $children ) {
			return array(
				Compile_Diagnostic::warning(
					'container.empty',
					$block->path(),
					'The email container has no content.'
				),
			);
		}

		return array_merge(
			$this->validate_singleton( $block, $children, 'campaignbridge/preheader', 0, 'preheader', 'first' ),
			$this->validate_singleton( $block, $children, 'campaignbridge/compliance-footer', count( $children ) - 1, 'compliance_footer', 'last' )
		);
	}

	/**
	 * Require at most one document-level block, pinned to one position.
	 *
	 * Both blocks describe the document rather than a content row, so a second
	 * copy or a mid-document placement is a source error rather than something
	 * to silently reorder.
	 *
	 * @param Block_Node             $block    Normalized container.
	 * @param array<int, Block_Node> $children Container children.
	 * @param string                 $name     Block name that is limited.
	 * @param int                    $expected Index the block must occupy.
	 * @param string                 $code     Diagnostic code prefix.
	 * @param string                 $position Human-readable expected position.
	 * @return array<int, Compile_Diagnostic>
	 */
	private function validate_singleton( Block_Node $block, array $children, string $name, int $expected, string $code, string $position ): array {
		$found = array();
		foreach ( $children as $index => $child ) {
			if ( $name === $child->name() ) {
				$found[] = $index;
			}
		}

		if ( array() === $found ) {
			return array();
		}

		if ( 1 < count( $found ) ) {
			return array(
				Compile_Diagnostic::error(
					$code . '.duplicated',
					$block->path(),
					sprintf( 'An email can contain only one %s block.', $name )
				),
			);
		}

		if ( $found[0] !== $expected ) {
			return array(
				Compile_Diagnostic::error(
					$code . '.misplaced',
					$children[ $found[0] ]->path(),
					sprintf( 'The %1$s block must be the %2$s block in the email.', $name, $position )
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
