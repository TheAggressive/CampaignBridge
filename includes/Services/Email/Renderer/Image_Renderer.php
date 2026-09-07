<?php
/**
 * Native email image renderer.
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

/** Renders a fully dimensioned, accessible email image. */
final class Image_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/image';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'url', 'alt', 'decorative', 'width', 'height', 'linkUrl', 'style' );
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
				'url'        => trim( Renderer_Support::string_attribute( $attributes, 'url', '' ) ),
				'alt'        => trim( Renderer_Support::string_attribute( $attributes, 'alt', '' ) ),
				'decorative' => Renderer_Support::boolean_attribute( $attributes, 'decorative', false ),
				'width'      => Renderer_Support::integer_attribute( $attributes, 'width', 600, 1, 1200 ),
				'height'     => Renderer_Support::integer_attribute( $attributes, 'height', 400, 1, 1200 ),
				'linkUrl'    => trim( Renderer_Support::string_attribute( $attributes, 'linkUrl', '' ) ),
				'style'      => is_array( $attributes['style'] ?? null ) ? $attributes['style'] : array(),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public function validate( Block_Node $block, Render_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$attributes = $block->attributes();
		if ( null === Renderer_Support::https_url( $attributes['url'] ) ) {
			return array( Compile_Diagnostic::error( 'image.url.invalid', $block->path(), 'Email images require an absolute URL.' ) );
		}

		if ( ! $attributes['decorative'] && '' === $attributes['alt'] ) {
			return array( Compile_Diagnostic::error( 'image.alt.missing', $block->path(), 'Non-decorative email images require alternative text.' ) );
		}

		if ( '' !== $attributes['linkUrl'] && null === Renderer_Support::https_url( $attributes['linkUrl'] ) ) {
			return array( Compile_Diagnostic::error( 'image.link.invalid', $block->path(), 'Linked email images require an absolute URL.' ) );
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
		$style_tree = is_array( $attributes['style'] ?? null ) ? $attributes['style'] : array();

		// Base image style.
		$image_style = 'display:block;width:100%;max-width:' . (int) $attributes['width'] . 'px;height:auto;border:0';

		// Margin: design-system shape first.
		if ( isset( $style_tree['spacing']['margin'] ) ) {
			$margin       = Style_Resolver::spacing(
				array( 'style' => $style_tree ),
				'margin',
				array(
					'top'    => 0,
					'right'  => 0,
					'bottom' => 0,
					'left'   => 0,
				)
			);
			$image_style .= sprintf(
				';margin:%dpx %dpx %dpx %dpx',
				$margin['top'],
				$margin['right'],
				$margin['bottom'],
				$margin['left']
			);
		}

		$image = sprintf(
			'<img src="%1$s" width="%2$d" height="%3$d" alt="%4$s"%5$s border="0" style="%6$s">',
			Renderer_Support::html( $attributes['url'] ),
			$attributes['width'],
			$attributes['height'],
			Renderer_Support::html( $attributes['decorative'] ? '' : $attributes['alt'] ),
			$attributes['decorative'] ? ' role="presentation"' : '',
			$image_style
		);

		return '' === $attributes['linkUrl']
			? $image
			: '<a href="' . Renderer_Support::html( $attributes['linkUrl'] ) . '" style="text-decoration:none">' . $image . '</a>';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child text.
	 * @param Render_Context $context  Immutable scoped context.
	 */
	public function render_text( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$attributes = $block->attributes();

		return '' === $attributes['linkUrl'] ? '' : $attributes['linkUrl'] . "\n";
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public function referenced_assets( Block_Node $block, Render_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$attributes = $block->attributes();

		return array(
			array(
				'type'   => 'image',
				'url'    => $attributes['url'],
				'width'  => $attributes['width'],
				'height' => $attributes['height'],
				'alt'    => $attributes['decorative'] ? '' : $attributes['alt'],
			),
		);
	}
}
