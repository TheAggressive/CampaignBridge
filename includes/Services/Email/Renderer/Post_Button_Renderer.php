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

/** Renders an accessible post button with a desktop Outlook VML fallback. */
final class Post_Button_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/post-button';
	}

	/** {@inheritDoc} */
	public function block_style_names(): array {
		return array( 'button', 'link' ); }

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'label', 'destination', 'customUrl', 'backgroundColor', 'textColor', 'fontFamily', 'align', 'style', 'linkColor', 'variant' );
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
				'hoverColor'      => $attributes['hoverColor'] ?? null,
				'label'           => trim( Renderer_Support::string_attribute( $attributes, 'label', 'Read more' ) ),
				'destination'     => Renderer_Support::choice_attribute( $attributes, 'destination', 'article', array( 'article', 'postParent', 'postTypeArchive', 'custom' ) ),
				'customUrl'       => trim( Renderer_Support::string_attribute( $attributes, 'customUrl', '' ) ),
				'backgroundColor' => Renderer_Support::string_attribute( $attributes, 'backgroundColor', '#111111' ),
				'textColor'       => Renderer_Support::string_attribute( $attributes, 'linkColor', Renderer_Support::string_attribute( $attributes, 'textColor', '#ffffff' ) ),
				'fontFamily'      => Renderer_Support::string_attribute( $attributes, 'fontFamily', '' ),
				'align'           => Renderer_Support::alignment_attribute( $attributes, 'align' ),
				'style'           => Renderer_Support::choice_attribute( $attributes, 'variant', 'button', array( 'button', 'link' ) ),
				// Link style needs its own colour: textColor defaults to white
				// for legibility on the button fill and would vanish inline.
				'linkColor'       => Renderer_Support::string_attribute( $attributes, 'linkColor', '#111111' ),
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
				Compile_Diagnostic::error( 'post.button.label_empty', $block->path(), 'The post button requires a label.' ),
			);
		}

		if ( null === Renderer_Support::post_destination_url( $block->attributes(), $context ) ) {
			list( $code, $message ) = Renderer_Support::missing_destination_diagnostics( 'post.button', (string) $block->attributes()['destination'] );
			return array(
				Compile_Diagnostic::error( $code, $block->path(), $message ),
			);
		}

		if ( 80 < strlen( $block->attributes()['label'] ) ) {
			return array(
				Compile_Diagnostic::error( 'post.button.label_too_long', $block->path(), 'The post button label cannot exceed 80 bytes.' ),
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
		$attributes  = $block->attributes();
		$url         = (string) Renderer_Support::post_destination_url( $attributes, $context );
		$kit         = Renderer_Support::brand_kit( $context );
		$font_family = Renderer_Support::resolve_font( $attributes, $kit, 'button' )['family'];

		if ( 'link' === $attributes['style'] ) {
			// A text link sits in the surrounding copy and uses neither the
			// button fill nor its on-fill text colour.
			return Native_Style_Support::link_output(
				sprintf(
					'<p align="%1$s" style="margin:0 0 16px;font-family:' . $font_family . ';font-size:16px;line-height:1.6;text-align:%1$s"><a href="%2$s" style="color:%3$s;text-decoration:underline">%4$s</a></p>',
					$attributes['align'],
					Renderer_Support::html( $url ),
					Renderer_Support::resolve_color( $attributes['linkColor'], $kit ),
					Renderer_Support::html( $attributes['label'] )
				),
				$block,
				$context
			);
		}

		return Native_Style_Support::link_output(
			Button_Markup::html(
				$url,
				$attributes['label'],
				Renderer_Support::resolve_color( $attributes['backgroundColor'], $kit ),
				Renderer_Support::resolve_color( $attributes['textColor'], $kit ),
				'left' === $attributes['align'] ? null : $attributes['align'],
				160,
				'primary',
				$font_family
			),
			$block,
			$context
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
		$font = Renderer_Support::resolve_font( $block->attributes(), $kit, 'button' );

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
		return $block->attributes()['label'] . ': ' . (string) Renderer_Support::post_destination_url( $block->attributes(), $context ) . "\n";
	}
}
