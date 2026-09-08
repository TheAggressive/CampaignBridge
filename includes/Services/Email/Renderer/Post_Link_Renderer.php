<?php
/**
 * Bulletproof post link renderer.
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

/** Renders an inline text link to the selected post. */
final class Post_Link_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/post-link';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'label', 'destination', 'customUrl', 'linkColor', 'align', 'style', 'backgroundColor', 'textColor' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = Native_Style_Support::attributes( $block );

		// Style is optional and may be injected by WordPress supports as a
		// non-string value (e.g. a CSS object). Only coerce when it's a string.
		$style = '';
		if ( isset( $attributes['style'] ) && is_string( $attributes['style'] ) ) {
			$style = $attributes['style'];
		}

		return $block->with_attributes(
			array(
				'hoverColor'      => $attributes['hoverColor'] ?? null,
				'label'           => trim( Renderer_Support::string_attribute( $attributes, 'label', 'Read more' ) ),
				'destination'     => Renderer_Support::choice_attribute( $attributes, 'destination', 'article', array( 'article', 'postParent', 'postTypeArchive', 'custom' ) ),
				'customUrl'       => trim( Renderer_Support::string_attribute( $attributes, 'customUrl', '' ) ),
				'linkColor'       => Renderer_Support::string_attribute( $attributes, 'linkColor', '#111111' ),
				'align'           => Renderer_Support::alignment_attribute( $attributes, 'align' ),
				'style'           => $style,
				'backgroundColor' => (string) Renderer_Support::string_attribute( $attributes, 'backgroundColor', '' ),
				'textColor'       => (string) Renderer_Support::string_attribute( $attributes, 'textColor', '' ),
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
				Compile_Diagnostic::error( 'post.link.label_empty', $block->path(), 'The post link requires a label.' ),
			);
		}

		if ( null === Renderer_Support::post_destination_url( $block->attributes(), $context ) ) {
			list( $code, $message ) = Renderer_Support::missing_destination_diagnostics( 'post.link', (string) $block->attributes()['destination'] );
			return array(
				Compile_Diagnostic::error( $code, $block->path(), $message ),
			);
		}

		if ( 80 < strlen( $block->attributes()['label'] ) ) {
			return array(
				Compile_Diagnostic::error( 'post.link.label_too_long', $block->path(), 'The post link label cannot exceed 80 bytes.' ),
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
		$url        = (string) Renderer_Support::post_destination_url( $attributes, $context );
		$kit        = Renderer_Support::brand_kit( $context );

		return Native_Style_Support::link_output(
			sprintf(
				'<p align="%1$s" style="margin:0 0 16px;font-family:Arial,sans-serif;font-size:16px;line-height:1.6;text-align:%1$s"><a href="%2$s" style="color:%3$s;text-decoration:underline">%4$s</a></p>',
				$attributes['align'],
				Renderer_Support::html( $url ),
				Renderer_Support::resolve_color( $attributes['linkColor'], $kit ),
				Renderer_Support::html( $attributes['label'] )
			),
			$block,
			$context
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
		return $block->attributes()['label'] . ': ' . (string) Renderer_Support::post_destination_url( $block->attributes(), $context ) . "\n";
	}
}
