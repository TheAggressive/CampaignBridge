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
use CampaignBridge\Domain\Email\Compile_Diagnostic;
use CampaignBridge\Domain\Email\Render_Context;

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
		return array( 'content', 'align', 'textColor', 'fontSize' );
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
				'content'   => is_string( $attributes['content'] ?? null ) ? $attributes['content'] : '',
				'align'     => Renderer_Support::alignment( $attributes['align'] ?? null ),
				'textColor' => Renderer_Support::color( $attributes['textColor'] ?? null, '#333333' ),
				'fontSize'  => Renderer_Support::integer( $attributes['fontSize'] ?? null, 16, 12, 24 ),
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
					'Email text permits only balanced strong, emphasis, underline, strikethrough, line-break, and HTTPS link markup.'
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

		return sprintf(
			'<p align="%1$s" style="margin:0 0 16px;font-family:Arial,sans-serif;font-size:%2$dpx;line-height:1.6;text-align:%1$s;color:%3$s">%4$s</p>',
			$attributes['align'],
			$attributes['fontSize'],
			$attributes['textColor'],
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
}
