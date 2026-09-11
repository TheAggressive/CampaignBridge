<?php
/**
 * Post title renderer.
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

/** Renders a snapshot post title with portable typography. */
final class Post_Title_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/post-title';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'level', 'align', 'textColor', 'linkToPost', 'fontSize', 'fontFamily', 'style' );
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
				'level'      => Renderer_Support::integer_attribute( $attributes, 'level', 2, 1, 4 ),
				'align'      => Renderer_Support::alignment_attribute( $attributes, 'align' ),
				'textColor'  => Renderer_Support::string_attribute( $attributes, 'textColor', '#111111' ),
				'linkToPost' => Renderer_Support::boolean_attribute( $attributes, 'linkToPost', false ),
				'fontSize'   => Renderer_Support::integer_attribute( $attributes, 'fontSize', 24, 10, 72 ),
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
		if ( ! is_array( $post ) || ! is_string( $post['title'] ?? null ) || '' === trim( $post['title'] ) ) {
			return array(
				Compile_Diagnostic::error( 'post.title.missing', $block->path(), 'The post snapshot requires a non-empty title.' ),
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
		$post       = $context->binding( 'post' );
		$title      = Renderer_Support::html( (string) ( $post['title'] ?? '' ) );
		$attributes = $block->attributes();
		$text_color = Renderer_Support::resolve_color( $attributes['textColor'], Renderer_Support::brand_kit( $context ) );
		$url        = $attributes['linkToPost'] ? Renderer_Support::https_url( $post['url'] ?? null ) : null;

		if ( null !== $url ) {
			$title = sprintf(
				'<a href="%1$s" style="color:%2$s;text-decoration:none">%3$s</a>',
				Renderer_Support::html( $url ),
				$text_color,
				$title
			);
		}

		$font_size   = $attributes['fontSize'];
		$font_family = Renderer_Support::resolve_font( $attributes, Renderer_Support::brand_kit( $context ), 'heading' )['family'];
		$line_height = Style_Resolver::line_height( $attributes, 1.25 );
		$font_weight = Style_Resolver::font_weight( $attributes, 700 );
		$margin      = Style_Resolver::spacing(
			$attributes,
			'margin',
			array(
				'top'    => 0,
				'right'  => 0,
				'bottom' => 12,
				'left'   => 0,
			)
		);
		$margin_css  = 0 === $margin['top'] && 0 === $margin['right'] && 0 === $margin['left']
			? sprintf( '0 0 %dpx', $margin['bottom'] )
			: sprintf( '%dpx %dpx %dpx %dpx', $margin['top'], $margin['right'], $margin['bottom'], $margin['left'] );

		return sprintf(
			'<h%1$d align="%2$s" style="margin:%3$s;font-family:%4$s;font-size:%5$dpx;font-weight:%6$d;line-height:%7$s;text-align:%2$s;color:%8$s">%9$s</h%1$d>',
			$attributes['level'],
			$attributes['align'],
			$margin_css,
			$font_family,
			$font_size,
			$font_weight,
			(string) $line_height,
			$text_color,
			$title
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
		$font = Renderer_Support::resolve_font( $block->attributes(), $kit, 'heading' );

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
		$post = $context->binding( 'post' );

		return trim( (string) ( $post['title'] ?? '' ) ) . "\n";
	}
}
