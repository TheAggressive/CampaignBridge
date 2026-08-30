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
		return array( 'label', 'backgroundColor', 'textColor' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = $block->attributes();
		$label      = is_string( $attributes['label'] ?? null ) ? trim( $attributes['label'] ) : 'Read more';

		return $block->with_attributes(
			array(
				'label'           => '' === $label ? 'Read more' : $label,
				'backgroundColor' => Renderer_Support::color( $attributes['backgroundColor'] ?? null, '#111111' ),
				'textColor'       => Renderer_Support::color( $attributes['textColor'] ?? null, '#ffffff' ),
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
		$url  = is_array( $post ) ? Renderer_Support::https_url( $post['url'] ?? null ) : null;

		if ( null === $url ) {
			return array(
				Compile_Diagnostic::error( 'post.cta.url_missing', $block->path(), 'The post CTA requires a snapshot HTTPS URL.' ),
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
		$post       = $context->binding( 'post' );
		$attributes = $block->attributes();
		$url        = Renderer_Support::html( (string) ( $post['url'] ?? '' ) );
		$label      = Renderer_Support::html( $attributes['label'] );
		$background = $attributes['backgroundColor'];
		$text       = $attributes['textColor'];

		return '<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="'
			. $url . '" style="height:44px;v-text-anchor:middle;width:160px" arcsize="9%" stroke="f" fillcolor="' . $background
			. '"><w:anchorlock/><center style="color:' . $text . ';font-family:Arial,sans-serif;font-size:16px;font-weight:bold">'
			. $label . '</center></v:roundrect><![endif]--><!--[if !mso]><!--><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-radius:4px;background-color:'
			. $background . '"><a href="' . $url . '" style="display:inline-block;padding:12px 24px;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;line-height:20px;color:'
			. $text . ';text-decoration:none">' . $label . '</a></td></tr></table><!--<![endif]-->';
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

		return $block->attributes()['label'] . ': ' . (string) ( $post['url'] ?? '' ) . "\n";
	}
}
