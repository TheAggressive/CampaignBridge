<?php
/**
 * Native email heading renderer.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Abstract_Renderer;
use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Compile_Diagnostic;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Domain\Email\Style_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders a bounded heading with explicit hierarchy and alignment. */
final class Heading_Renderer extends Abstract_Renderer {
	/**
	 * Portable sizes by heading level.
	 *
	 * @var array<int, int>
	 */
	private const SIZE_BY_LEVEL = array(
		1 => 32,
		2 => 28,
		3 => 24,
		4 => 20,
	);

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/heading';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'content', 'level', 'align', 'textColor', 'style', 'fontSize', 'fontFamily' );
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
				'content'    => Renderer_Support::string_attribute( $attributes, 'content', '' ),
				'level'      => Renderer_Support::integer_attribute( $attributes, 'level', 2, 1, 4 ),
				'align'      => Renderer_Support::alignment_attribute( $attributes, 'align' ),
				'textColor'  => Renderer_Support::string_attribute( $attributes, 'textColor', '#111111' ),
				'fontFamily' => Renderer_Support::string_attribute( $attributes, 'fontFamily', '' ),
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
		if ( '' === trim( wp_strip_all_tags( $block->attributes()['content'] ) ) ) {
			return array(
				Compile_Diagnostic::error(
					'heading.content.empty',
					$block->path(),
					'Email headings require visible text.'
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
		$attributes = $block->attributes();
		$style      = $this->build_style( $attributes, $context );
		$heading    = 'h' . $attributes['level'];

		return sprintf(
			'<%1$s align="%2$s" style="%3$s">%4$s</%1$s>',
			$heading,
			$attributes['align'],
			$style,
			Renderer_Support::html( html_entity_decode( $attributes['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) )
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
		return trim( html_entity_decode( $block->attributes()['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) . "\n";
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
	 * Build the portable inline style string for the heading block.
	 *
	 * Resolves the design-system style tree (color, typography) through the
	 * Style_Resolver, falling back to the legacy per-block attributes when no
	 * style choice was made.
	 *
	 * @param array<string, mixed> $attributes Normalized attributes.
	 * @param Render_Context       $context    Immutable scoped context.
	 * @return string Portable CSS declarations, semicolon-separated.
	 */
	private function build_style( array $attributes, Render_Context $context ): string {
		$style_tree = is_array( $attributes['style'] ?? null ) ? $attributes['style'] : array();
		$wrapper    = array( 'style' => $style_tree );
		$kit        = $context->metadata( 'brandKit' );
		$kit        = $kit instanceof Brand_Kit ? $kit : Brand_Kit::defaults();

		$font_size = null;
		if ( isset( $style_tree['typography']['fontSize'] ) ) {
			$font_size = Style_Resolver::font_size( $wrapper, null, 10, 72 );
		}
		if ( null === $font_size ) {
			$font_size = self::SIZE_BY_LEVEL[ $attributes['level'] ] ?? 24;
		}

		$line_height = null;
		if ( isset( $style_tree['typography']['lineHeight'] ) ) {
			$line_height = Style_Resolver::line_height( $wrapper, null );
		}
		if ( null === $line_height ) {
			$line_height = 1.25;
		}

		$color = null;
		if ( isset( $style_tree['color']['text'] ) ) {
			$color = Style_Resolver::color( $wrapper, 'text', null, $kit );
		}
		if ( null === $color ) {
			$color = Renderer_Support::resolve_color( (string) $attributes['textColor'], $kit );
		}

		$font_family = Renderer_Support::resolve_font( $attributes, $kit, 'heading' )['family'];

		return 'margin:0 0 16px;font-family:' . $font_family . ';font-size:' . (int) $font_size . 'px;line-height:' . (string) $line_height . ';text-align:' . $attributes['align'] . ';color:' . $color;
	}
}
