<?php
/**
 * Post image renderer.
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

/** Renders a fully dimensioned image from a post snapshot. */
final class Post_Image_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/post-image';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public function validate( Block_Node $block, Render_Context $context ): array {
		$post  = $context->binding( 'post' );
		$image = is_array( $post ) && is_array( $post['image'] ?? null ) ? $post['image'] : null;

		if ( null === $image ) {
			return array(
				Compile_Diagnostic::warning( 'post.image.missing', $block->path(), 'The post has no image in its snapshot.' ),
			);
		}

		if (
			null === Renderer_Support::https_url( $image['url'] ?? null )
			|| ! is_string( $image['alt'] ?? null )
			|| ! is_int( $image['width'] ?? null )
			|| ! is_int( $image['height'] ?? null )
			|| 1 > $image['width']
			|| 1 > $image['height']
		) {
			return array(
				Compile_Diagnostic::error(
					'post.image.invalid',
					$block->path(),
					'Post images require an HTTPS URL, alt decision, and positive integer dimensions.'
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
		$post  = $context->binding( 'post' );
		$image = is_array( $post ) && is_array( $post['image'] ?? null ) ? $post['image'] : null;
		if ( null === $image ) {
			return '';
		}

		return sprintf(
			'<img src="%1$s" width="%2$d" height="%3$d" alt="%4$s" border="0" style="display:block;width:100%%;max-width:%2$dpx;height:auto;border:0">',
			Renderer_Support::html( (string) $image['url'] ),
			$image['width'],
			$image['height'],
			Renderer_Support::html( $image['alt'] )
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
		return '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public function referenced_assets( Block_Node $block, Render_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$post  = $context->binding( 'post' );
		$image = is_array( $post ) && is_array( $post['image'] ?? null ) ? $post['image'] : null;
		if ( null === $image ) {
			return array();
		}

		return array(
			array(
				'type'   => 'image',
				'url'    => $image['url'],
				'width'  => $image['width'],
				'height' => $image['height'],
				'alt'    => $image['alt'],
			),
		);
	}
}
