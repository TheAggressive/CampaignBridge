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
		return array( 'maxWords', 'align', 'textColor', 'fontSize', 'fontFamily', 'style' );
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
				'style'      => $attributes['style'],
				'maxWords'   => Renderer_Support::integer_attribute( $attributes, 'maxWords', \CampaignBridge\REST\Rest_Constants::DEFAULT_EXCERPT_MAX_WORDS, 10, 150 ),
				'align'      => Renderer_Support::alignment_attribute( $attributes, 'align' ),
				'textColor'  => Renderer_Support::string_attribute( $attributes, 'textColor', '#333333' ),
				'fontSize'   => Renderer_Support::integer_attribute( $attributes, 'fontSize', 16, 10, 72 ),
				'fontFamily' => Renderer_Support::string_attribute( $attributes, 'fontFamily', '' ),
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
		$line_height = Style_Resolver::line_height( $attributes, 1.6 );
		$margin      = Style_Resolver::spacing(
			$attributes,
			'margin',
			array(
				'top'    => 0,
				'right'  => 0,
				'bottom' => 16,
				'left'   => 0,
			)
		);
		$margin_css  = 0 === $margin['top'] && 0 === $margin['right'] && 0 === $margin['left']
			? sprintf( '0 0 %dpx', $margin['bottom'] )
			: sprintf( '%dpx %dpx %dpx %dpx', $margin['top'], $margin['right'], $margin['bottom'], $margin['left'] );

		return sprintf(
			'<p align="%1$s" style="margin:%2$s;font-family:%3$s;font-size:%4$dpx;line-height:%5$s;text-align:%1$s;color:%6$s">%7$s</p>',
			$attributes['align'],
			$margin_css,
			$font_family,
			$attributes['fontSize'],
			(string) $line_height,
			$text_color,
			Renderer_Support::html( $this->excerpt( $block, $context ) )
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
