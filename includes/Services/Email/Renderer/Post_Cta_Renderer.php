<?php
/**
 * Bulletproof post call-to-action renderer.
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

/** Renders an accessible CTA with a desktop Outlook VML fallback. */
final class Post_Cta_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/post-cta';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'label', 'destination', 'customUrl', 'backgroundColor', 'textColor' );
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
				'label'           => trim( Renderer_Support::string_attribute( $attributes, 'label', 'Read more' ) ),
				'destination'     => Renderer_Support::choice_attribute( $attributes, 'destination', 'article', array( 'article', 'postParent', 'postTypeArchive', 'custom' ) ),
				'customUrl'       => trim( Renderer_Support::string_attribute( $attributes, 'customUrl', '' ) ),
				'backgroundColor' => Renderer_Support::color_attribute( $attributes, 'backgroundColor', '#111111' ),
				'textColor'       => Renderer_Support::color_attribute( $attributes, 'textColor', '#ffffff' ),
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
		if ( '' === $block->attributes()['label'] ) {
			return array(
				Compile_Diagnostic::error( 'post.cta.label_empty', $block->path(), 'The post CTA requires a label.' ),
			);
		}

		$url = $this->destination_url( $block, $context );

		if ( null === $url ) {
			$destination = $block->attributes()['destination'];
			$diagnostics = array(
				'article'         => array( 'post.cta.url_missing', 'The post CTA requires a snapshot HTTPS article URL.' ),
				'postParent'      => array( 'post.cta.post_parent_url_missing', 'The post CTA requires the post parent HTTPS URL in its snapshot.' ),
				'postTypeArchive' => array( 'post.cta.post_type_archive_url_missing', 'The post CTA requires the post type archive HTTPS URL in its snapshot.' ),
				'custom'          => array( 'post.cta.custom_url_invalid', 'The post CTA custom destination must be an absolute HTTPS URL.' ),
			);
			return array(
				Compile_Diagnostic::error(
					$diagnostics[ $destination ][0],
					$block->path(),
					$diagnostics[ $destination ][1]
				),
			);
		}

		if ( 80 < strlen( $block->attributes()['label'] ) ) {
			return array(
				Compile_Diagnostic::error( 'post.cta.label_too_long', $block->path(), 'The post CTA label cannot exceed 80 bytes.' ),
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

		return Button_Markup::html(
			(string) $this->destination_url( $block, $context ),
			$attributes['label'],
			$attributes['backgroundColor'],
			$attributes['textColor'],
			null,
			160
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
		return $block->attributes()['label'] . ': ' . (string) $this->destination_url( $block, $context ) . "\n";
	}

	/**
	 * Resolve the selected immutable CTA destination.
	 *
	 * @param Block_Node     $block   Normalized CTA block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	private function destination_url( Block_Node $block, Render_Context $context ): ?string {
		$destination = $block->attributes()['destination'];
		if ( 'custom' === $destination ) {
			return Renderer_Support::https_url( $block->attributes()['customUrl'] );
		}

		$post = $context->binding( 'post' );
		if ( ! is_array( $post ) ) {
			return null;
		}

		$field = match ( $destination ) {
			'postParent'      => 'postParentUrl',
			'postTypeArchive' => 'postTypeArchiveUrl',
			default           => 'url',
		};

		return Renderer_Support::https_url( $post[ $field ] ?? null );
	}
}
