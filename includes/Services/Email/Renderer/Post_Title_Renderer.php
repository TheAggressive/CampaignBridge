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
		return array( 'level', 'align', 'textColor', 'linkToPost' );
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
				'level'      => Renderer_Support::integer_attribute( $attributes, 'level', 2, 1, 4 ),
				'align'      => Renderer_Support::alignment_attribute( $attributes, 'align' ),
				'textColor'  => Renderer_Support::color_attribute( $attributes, 'textColor', '#111111' ),
				'linkToPost' => Renderer_Support::boolean_attribute( $attributes, 'linkToPost', false ),
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
	public function render_html( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$post       = $context->binding( 'post' );
		$title      = Renderer_Support::html( (string) ( $post['title'] ?? '' ) );
		$attributes = $block->attributes();
		$url        = $attributes['linkToPost'] ? Renderer_Support::https_url( $post['url'] ?? null ) : null;

		if ( null !== $url ) {
			$title = sprintf(
				'<a href="%1$s" style="color:%2$s;text-decoration:none">%3$s</a>',
				Renderer_Support::html( $url ),
				$attributes['textColor'],
				$title
			);
		}

		return sprintf(
			'<h%1$d align="%2$s" style="margin:0 0 12px;font-family:Arial,sans-serif;font-size:24px;line-height:1.25;text-align:%2$s;color:%3$s">%4$s</h%1$d>',
			$attributes['level'],
			$attributes['align'],
			$attributes['textColor'],
			$title
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
		$post = $context->binding( 'post' );

		return trim( (string) ( $post['title'] ?? '' ) ) . "\n";
	}
}
