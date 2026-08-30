<?php
/**
 * Immutable WordPress post card binding renderer.
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

/** Resolves a post snapshot and scopes it to semantic child blocks. */
final class Post_Card_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/post-card';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'postId', 'postType' );
	}

	/** {@inheritDoc} */
	public function allowed_children(): array {
		return array(
			'campaignbridge/post-image',
			'campaignbridge/post-title',
			'campaignbridge/post-excerpt',
			'campaignbridge/post-cta',
		);
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
				'postId'   => Renderer_Support::integer( $attributes['postId'] ?? null, 0, 0, PHP_INT_MAX ),
				'postType' => is_string( $attributes['postType'] ?? null ) ? $attributes['postType'] : 'post',
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
		$post_id = (string) $block->attributes()['postId'];
		$post    = $context->snapshot( 'posts', $post_id );

		if ( null === $post ) {
			return array(
				Compile_Diagnostic::error(
					'post.snapshot.missing',
					$block->path(),
					'The selected post is missing from the immutable content snapshot.'
				),
			);
		}

		foreach ( array( 'title', 'excerpt', 'url' ) as $field ) {
			if ( ! isset( $post[ $field ] ) || ! is_string( $post[ $field ] ) ) {
				return array(
					Compile_Diagnostic::error(
						'post.snapshot.invalid',
						$block->path(),
						sprintf( 'The post snapshot requires a string %s field.', $field )
					),
				);
			}
		}

		if ( null === Renderer_Support::https_url( $post['url'] ) ) {
			return array(
				Compile_Diagnostic::error(
					'post.url.invalid',
					$block->path(),
					'The post snapshot URL must be an absolute HTTPS URL.'
				),
			);
		}

		return array();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized parent block.
	 * @param Render_Context $context Immutable parent context.
	 */
	public function context_for_children( Block_Node $block, Render_Context $context ): Render_Context {
		$post = $context->snapshot( 'posts', (string) $block->attributes()['postId'] );

		return null === $post ? $context : $context->with_binding( 'post', $post );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child HTML.
	 * @param Render_Context $context  Immutable scoped context.
	 */
	public function render_html( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse"><tr><td style="padding:20px 0">' . $children . '</td></tr></table>';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child text.
	 * @param Render_Context $context  Immutable scoped context.
	 */
	public function render_text( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return trim( $children ) . "\n\n";
	}
}
