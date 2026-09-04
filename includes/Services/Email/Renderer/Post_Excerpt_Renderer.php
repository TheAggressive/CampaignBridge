<?php
/**
 * Post excerpt renderer.
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

/** Renders a bounded plain-text excerpt from a snapshot. */
final class Post_Excerpt_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/post-excerpt';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'maxWords', 'align', 'textColor', 'fontSize' );
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
				'maxWords'  => Renderer_Support::integer_attribute( $attributes, 'maxWords', 50, 10, 150 ),
				'align'     => Renderer_Support::alignment_attribute( $attributes, 'align' ),
				'textColor' => Renderer_Support::color_attribute( $attributes, 'textColor', '#333333' ),
				'fontSize'  => Renderer_Support::integer_attribute( $attributes, 'fontSize', 16, 12, 24 ),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public function validate( Block_Node $block, Render_Context $context ): array {
		$post = $context->binding( 'post' );
		if ( ! is_array( $post ) || ! is_string( $post['excerpt'] ?? null ) ) {
			return array(
				Compile_Diagnostic::error( 'post.excerpt.missing', $block->path(), 'The post snapshot requires an excerpt.' ),
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

		return sprintf(
			'<p align="%1$s" style="margin:0 0 16px;font-family:Arial,sans-serif;font-size:%2$dpx;line-height:1.6;text-align:%1$s;color:%3$s">%4$s</p>',
			$attributes['align'],
			$attributes['fontSize'],
			$attributes['textColor'],
			Renderer_Support::html( $this->excerpt( $block, $context ) )
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
		return $this->excerpt( $block, $context ) . "\n";
	}

	/**
	 * Build the normalized bounded excerpt.
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	private function excerpt( Block_Node $block, Render_Context $context ): string {
		$post    = $context->binding( 'post' );
		$content = trim( html_entity_decode( wp_strip_all_tags( (string) ( $post['excerpt'] ?? '' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$words   = preg_split( '/\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY );
		$words   = is_array( $words ) ? $words : array();
		$limit   = $block->attributes()['maxWords'];

		if ( count( $words ) <= $limit ) {
			return implode( ' ', $words );
		}

		return implode( ' ', array_slice( $words, 0, $limit ) ) . '…';
	}
}
