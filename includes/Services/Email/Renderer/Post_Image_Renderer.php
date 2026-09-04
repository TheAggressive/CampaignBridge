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

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'width', 'align', 'linkToPost', 'decorative' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Every default reproduces the previous output, so existing templates
	 * compile to the same bytes after this block gained a control surface.
	 *
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = $block->attributes();

		return $block->with_attributes(
			array(
				// Omitted width keeps the snapshot's own width.
				'width'      => array_key_exists( 'width', $attributes )
					? Renderer_Support::integer_attribute( $attributes, 'width', 600, 1, 1200 )
					: null,
				'align'      => Renderer_Support::alignment_attribute( $attributes, 'align' ),
				'linkToPost' => Renderer_Support::boolean_attribute( $attributes, 'linkToPost', false ),
				'decorative' => Renderer_Support::boolean_attribute( $attributes, 'decorative', false ),
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

		$attributes = $block->attributes();
		$width      = $attributes['width'] ?? $image['width'];
		$decorative = $attributes['decorative'];

		$markup = sprintf(
			'<img src="%1$s" width="%2$d" height="%3$d" alt="%4$s"%5$s border="0" style="display:block;width:100%%;max-width:%2$dpx;height:auto;border:0">',
			Renderer_Support::html( (string) $image['url'] ),
			$width,
			$this->scaled_height( $image, $width ),
			Renderer_Support::html( $decorative ? '' : (string) $image['alt'] ),
			$decorative ? ' role="presentation"' : ''
		);

		$url = $attributes['linkToPost'] ? Renderer_Support::https_url( $post['url'] ?? null ) : null;
		if ( null !== $url ) {
			$markup = '<a href="' . Renderer_Support::html( $url ) . '" style="text-decoration:none">' . $markup . '</a>';
		}

		// A block-level image already sits left, so only a real change needs the
		// alignment wrapper desktop Outlook requires.
		if ( 'left' === $attributes['align'] ) {
			return $markup;
		}

		return sprintf(
			'<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" style="width:100%%;border-collapse:collapse"><tr><td align="%1$s">%2$s</td></tr></table>',
			$attributes['align'],
			$markup
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
	 * Keep the snapshot aspect ratio when the author overrides the width.
	 *
	 * @param array<string, mixed> $image Snapshot image record.
	 * @param int                  $width Rendered width.
	 */
	private function scaled_height( array $image, int $width ): int {
		$source_width  = (int) $image['width'];
		$source_height = (int) $image['height'];

		if ( $source_width === $width || 1 > $source_width ) {
			return $source_height;
		}

		return max( 1, (int) round( $source_height * ( $width / $source_width ) ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public function referenced_assets( Block_Node $block, Render_Context $context ): array {
		$post  = $context->binding( 'post' );
		$image = is_array( $post ) && is_array( $post['image'] ?? null ) ? $post['image'] : null;
		if ( null === $image ) {
			return array();
		}

		$attributes = $block->attributes();
		$width      = $attributes['width'] ?? $image['width'];

		return array(
			array(
				'type'   => 'image',
				'url'    => $image['url'],
				'width'  => $width,
				'height' => $this->scaled_height( $image, $width ),
				'alt'    => $attributes['decorative'] ? '' : $image['alt'],
			),
		);
	}
}
