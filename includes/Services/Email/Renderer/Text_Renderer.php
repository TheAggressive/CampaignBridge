<?php
/**
 * Native rich email text renderer.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Abstract_Renderer;
use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Compile_Diagnostic;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Domain\Email\Style_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders validated inline rich text with portable typography. */
final class Text_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/text';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'content', 'align', 'textColor', 'fontSize', 'style', 'backgroundColor' );
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
				'content'   => Renderer_Support::string_attribute( $attributes, 'content', '' ),
				'align'     => Renderer_Support::alignment_attribute( $attributes, 'align' ),
				'textColor' => Renderer_Support::string_attribute( $attributes, 'textColor', '#333333' ),
				'fontSize'  => Renderer_Support::integer_attribute( $attributes, 'fontSize', 16, 10, 72 ),
				'style'     => is_array( $attributes['style'] ?? null ) ? $attributes['style'] : array(),
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
		$content = $block->attributes()['content'];
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return array( Compile_Diagnostic::error( 'text.content.empty', $block->path(), 'Email text requires visible content.' ) );
		}

		if ( null === Renderer_Support::rich_text( $content ) ) {
			return array(
				Compile_Diagnostic::error(
					'text.content.invalid',
					$block->path(),
					'Email text permits only balanced strong, emphasis, underline, strikethrough, line-break, and link markup.'
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
		$content    = Renderer_Support::rich_text( $attributes['content'] );
		$style      = $this->build_style( $attributes, $context );

		return sprintf(
			'<p align="%1$s" style="%2$s">%3$s</p>',
			$attributes['align'],
			$style,
			(string) $content
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
		return (string) Renderer_Support::rich_text_to_plain( $block->attributes()['content'] ) . "\n";
	}

	/**
	 * Build the portable inline style string for the text block.
	 *
	 * Resolves the design-system style tree (color, spacing, typography)
	 * through the Style_Resolver, falling back to the legacy per-block
	 * attributes when no style choice was made.
	 *
	 * @param array<string, mixed> $attributes Normalized attributes.
	 * @param Render_Context       $context    Immutable scoped context.
	 * @return string Portable CSS declarations, semicolon-separated.
	 */
	private function build_style( array $attributes, Render_Context $context ): string {
		$style_tree = is_array( $attributes['style'] ?? null ) ? $attributes['style'] : array();
		$wrapper    = array( 'style' => $style_tree );
		$kit        = $context->metadata( 'brandKit' );
		$kit        = $kit instanceof Brand_Kit ? $kit : Brand_Kit::defaults();

		$margin = Style_Resolver::spacing(
			$wrapper,
			'margin',
			array(
				'top'    => 0,
				'right'  => 0,
				'bottom' => 16,
				'left'   => 0,
			)
		);
		$style  = isset( $style_tree['spacing']['margin'] )
			? sprintf( 'margin:%dpx %dpx %dpx %dpx;font-family:Arial,sans-serif', $margin['top'], $margin['right'], $margin['bottom'], $margin['left'] )
			: 'margin:0 0 16px;font-family:Arial,sans-serif';

		// Font size: design-system shape first, then the legacy number attr.
		$font_size = null;
		if ( isset( $style_tree['typography']['fontSize'] ) ) {
			$font_size = Style_Resolver::font_size( $wrapper, null, 10, 72 );
		}
		if ( null === $font_size ) {
			$font_size = (int) $attributes['fontSize'];
		}
		$style .= sprintf( ';font-size:%dpx', $font_size );

		// Line height: design-system shape first, then a conservative default.
		$line_height = Style_Resolver::line_height( $wrapper, 1.6 );
		$style      .= sprintf( ';line-height:%s', $line_height );

		// Text color: design-system shape first, then the legacy hex attr.
		$color = null;
		if ( isset( $style_tree['color']['text'] ) ) {
			$color = Style_Resolver::color( $wrapper, 'text', null, $kit );
		}
		if ( null === $color ) {
			$color = Renderer_Support::resolve_color( (string) $attributes['textColor'], $kit );
		}
		$style .= sprintf( ';color:%s', $color );

		// Text align (always emitted).
		$style .= sprintf( ';text-align:%s', $attributes['align'] );

		// Padding: emit only when at least one edge is non-zero.
		$padding = Style_Resolver::spacing(
			$wrapper,
			'padding',
			array(
				'top'    => 0,
				'right'  => 0,
				'bottom' => 0,
				'left'   => 0,
			)
		);
		if ( $this->has_spacing( $padding ) ) {
			$style .= sprintf(
				';padding:%dpx %dpx %dpx %dpx',
				$padding['top'],
				$padding['right'],
				$padding['bottom'],
				$padding['left']
			);
		}

		// Background color: emit only when a value was resolved.
		if ( isset( $style_tree['color']['background'] ) ) {
			$background = Style_Resolver::color( $wrapper, 'background', null, $kit );
			if ( null !== $background ) {
				$style .= sprintf( ';background-color:%s', $background );
			}
		}

		return $style;
	}

	/**
	 * Whether any padding edge is non-zero.
	 *
	 * @param array<string, mixed> $spacing Normalized spacing values.
	 * @return bool
	 */
	private function has_spacing( array $spacing ): bool {
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $edge ) {
			if ( (int) ( $spacing[ $edge ] ?? 0 ) > 0 ) {
				return true;
			}
		}

		return false;
	}
}
