<?php
/**
 * Native email heading renderer.
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

/** Renders a bounded semantic heading. */
final class Heading_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/heading';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'content', 'level', 'align', 'textColor' );
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
				'level'     => Renderer_Support::integer( $attributes['level'] ?? null, 2, 1, 4 ),
				'align'     => Renderer_Support::alignment( $attributes['align'] ?? null ),
				'textColor' => Renderer_Support::color( $attributes['textColor'] ?? null, '#111111' ),
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
		if ( '' === trim( html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) {
			return array( Compile_Diagnostic::error( 'heading.content.empty', $block->path(), 'Email headings require visible content.' ) );
		}

		if ( preg_match( '/<[^>]*>/', $content ) ) {
			return array( Compile_Diagnostic::error( 'heading.content.invalid', $block->path(), 'Email headings do not permit nested markup.' ) );
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
		$level      = $attributes['level'];
		$sizes      = array(
			1 => 32,
			2 => 28,
			3 => 24,
			4 => 20,
		);
		$content    = Renderer_Support::html( html_entity_decode( $attributes['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		return sprintf(
			'<h%1$d align="%2$s" style="margin:0 0 16px;font-family:Arial,sans-serif;font-size:%3$dpx;line-height:1.25;text-align:%2$s;color:%4$s">%5$s</h%1$d>',
			$level,
			$attributes['align'],
			$sizes[ $level ],
			$attributes['textColor'],
			$content
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
		return trim( html_entity_decode( $block->attributes()['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) . "\n";
	}
}
