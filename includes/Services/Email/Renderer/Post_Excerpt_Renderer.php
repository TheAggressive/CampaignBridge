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
use CampaignBridge\Domain\Email\Style_Resolver;

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
		return array( 'maxWords', 'align', 'textColor', 'fontSize', 'style' );
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
				'style'     => $attributes['style'],
				'maxWords'  => Renderer_Support::integer_attribute( $attributes, 'maxWords', \CampaignBridge\REST\Rest_Constants::DEFAULT_EXCERPT_MAX_WORDS, 10, 150 ),
				'align'     => Renderer_Support::alignment_attribute( $attributes, 'align' ),
				'textColor' => Renderer_Support::string_attribute( $attributes, 'textColor', '#333333' ),
				'fontSize'  => Renderer_Support::integer_attribute( $attributes, 'fontSize', 16, 10, 72 ),
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
	public function render_html( Block_Node $block, string $children, Render_Context $context ): string {
		$attributes = $block->attributes();
		$text_color = Renderer_Support::resolve_color( $attributes['textColor'], Renderer_Support::brand_kit( $context ) );

		$font_family = Renderer_Support::resolve_font( $attributes, Renderer_Support::brand_kit( $context ) )['family'];

		return sprintf(
			'<p align="%1$s" style="margin:0 0 16px;font-family:' . $font_family . ';font-size:%2$dpx;line-height:1.6%5$s;text-align:%1$s;color:%3$s">%4$s</p>',
			$attributes['align'],
			$attributes['fontSize'],
			$text_color,
			Renderer_Support::html( $this->excerpt( $block, $context ) ),
			isset( $attributes['style']['typography']['lineHeight'] ) ? ';line-height:' . Style_Resolver::line_height( $attributes ) : ''
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public function referenced_assets( Block_Node $block, Render_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$kit  = Renderer_Support::brand_kit( $context );
		$font = Renderer_Support::resolve_font( $block->attributes(), $kit );

		return 'web' === $font['type'] && null !== $font['url']
			? array(
				array(
					'type' => 'font',
					'slug' => $font['slug'],
					'url'  => $font['url'],
				),
			)
			: array();
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
		$post  = $context->binding( 'post' );
		$raw   = (string) ( $post['excerpt'] ?? '' );
		$limit = (int) $block->attributes()['maxWords'];
		$limit = $limit > 0 ? $limit : 50;

		return Renderer_Support::truncate_words( $raw, $limit );
	}
}
