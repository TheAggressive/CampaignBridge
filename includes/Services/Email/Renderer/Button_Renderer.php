<?php
/**
 * Native email button renderer.
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
use CampaignBridge\Domain\Email\Token\Token_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders one Core button as a validated bulletproof call to action aligned by its `core/buttons` group. */
final class Button_Renderer extends Abstract_Renderer {
	/** Label size when neither the author nor the design chose one. */
	private const FONT_SIZE = 16;

	/** Padding when neither the author nor the design chose one. */
	private const PADDING = array(
		'top'    => 12,
		'right'  => 24,
		'bottom' => 12,
		'left'   => 24,
	);

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'core/button';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'label', 'url', 'style', 'backgroundColor', 'textColor', 'borderColor', 'fontSize', 'fontFamily', 'variant', Post_Binding_Support::ATTRIBUTE );
	}

	/** {@inheritDoc} */
	public function token_attributes(): array {
		return array(
			'label' => Token_Resolver::CONTEXT_TEXT,
			'url'   => Token_Resolver::CONTEXT_URL,
		);
	}

	/** {@inheritDoc} */
	public function block_style_names(): array {
		return array( 'primary', 'outline', 'ghost' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Normalized block.
	 */
	public function snapshot_fields( Block_Node $block ): array {
		return Post_Binding_Support::fields( $block );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public function resolve_post_bindings( Block_Node $block, Render_Context $context ): Block_Node {
		return Post_Binding_Support::resolve( $block, $context );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = Native_Style_Support::attributes( $block );
		$styles     = array( 'primary', 'outline', 'ghost' );

		return $block->with_attributes(
			array(
				'label'           => trim( Renderer_Support::string_attribute( $attributes, 'label', 'Learn more' ) ),
				'url'             => trim( Renderer_Support::string_attribute( $attributes, 'url', '' ) ),
				'variant'         => Renderer_Support::choice_attribute( $attributes, 'variant', 'primary', $styles ),
				'backgroundColor' => (string) Renderer_Support::string_attribute( $attributes, 'backgroundColor', '' ),
				'textColor'       => (string) Renderer_Support::string_attribute( $attributes, 'textColor', '' ),
				'fontFamily'      => Renderer_Support::string_attribute( $attributes, 'fontFamily', '' ),
				'fontSize'        => Renderer_Support::integer_attribute( $attributes, 'fontSize', self::FONT_SIZE, 10, 72 ),
				'style'           => is_array( $attributes['style'] ?? null ) ? $attributes['style'] : array(),
			) + Post_Binding_Support::carry( $block )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public function validate( Block_Node $block, Render_Context $context ): array {
		$bound = Post_Binding_Support::validate( $block, $context );
		if ( array() !== $bound ) {
			return $bound;
		}

		$attributes = $block->attributes();
		if ( '' === $attributes['label'] ) {
			return array( Compile_Diagnostic::error( 'button.label.empty', $block->path(), 'Email buttons require a label.' ) );
		}

		if ( 80 < strlen( $attributes['label'] ) ) {
			return array( Compile_Diagnostic::error( 'button.label.too_long', $block->path(), 'Email button labels cannot exceed 80 bytes.' ) );
		}

		if ( null === Renderer_Support::link_url( $attributes['url'] ) ) {
			return array( Compile_Diagnostic::error( 'button.url.invalid', $block->path(), 'Email buttons require an absolute URL.' ) );
		}

		$borders = Box_Style::borders( $attributes['style'], $this->brand_kit( $context ) );
		if ( array() !== $borders && ! Box_Style::is_uniform( $borders ) ) {
			return array( Compile_Diagnostic::error( 'button.border.sides', $block->path(), 'Email buttons need the same border on every side, because Outlook draws one outline for the whole button.' ) );
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
		$background = $this->resolve_background( $block, $context );
		$text       = $this->resolve_text( $block, $context );
		$variant    = $attributes['variant'];
		$align      = $context->binding( 'button_align' )['align'] ?? 'left';

		// Outline and ghost variants are monochromatic: the chosen background
		// drives the border, the visible text, and the Outlook VML paint, so a
		// separate text slot is ignored for them.
		if ( 'primary' !== $variant ) {
			$text = $background;
		}

		$font_family = Renderer_Support::resolve_font( $attributes, $context, 'button' )['family'];

		return Button_Markup::html(
			$attributes['url'],
			$attributes['label'],
			$background,
			$text,
			$align,
			$variant,
			$font_family,
			$this->box( $attributes, $variant, $background, $text, $context )
		);
	}

	/**
	 * Resolve the button's padding, border, corner radius, and type.
	 *
	 * The outline variant draws Core's 2px outline in the button colour unless
	 * a border was authored. A border without a colour uses the label colour.
	 *
	 * @param array<string, mixed> $attributes Normalized attributes.
	 * @param string               $variant    Button style variant.
	 * @param string               $background Resolved button colour.
	 * @param string               $text       Resolved label colour.
	 * @param Render_Context       $context    Immutable scoped context.
	 * @return array{padding: array<string, int>, radius: array{topLeft: int, topRight: int, bottomRight: int, bottomLeft: int}|null, border: array{width: int, style: string, color: string}|null, font_size: int, line_height: int, font_weight: int, italic: bool}
	 */
	private function box( array $attributes, string $variant, string $background, string $text, Render_Context $context ): array {
		$style   = $attributes['style'];
		$wrapper = array( 'style' => $style );
		$borders = Box_Style::borders( $style, $this->brand_kit( $context ) );
		$border  = $borders['top'] ?? null;
		if ( null !== $border ) {
			$border['color'] = $border['color'] ?? $text;
		} elseif ( 'outline' === $variant ) {
			$border = array(
				'width' => 2,
				'style' => 'solid',
				'color' => $background,
			);
		}

		return array(
			'padding'     => Style_Resolver::spacing( $wrapper, 'padding', self::PADDING ),
			'radius'      => Box_Style::radius( $style ),
			'border'      => $border,
			'font_size'   => $attributes['fontSize'],
			'line_height' => (int) round( $attributes['fontSize'] * Style_Resolver::line_height( $wrapper, 1.25 ) ),
			'font_weight' => Style_Resolver::font_weight( $wrapper, 700 ),
			'italic'      => 'italic' === Style_Resolver::font_style( $wrapper ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public function referenced_assets( Block_Node $block, Render_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$font = Renderer_Support::resolve_font( $block->attributes(), $context, 'button' );

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
		return $block->attributes()['label'] . ': ' . $block->attributes()['url'] . "\n";
	}

	/**
	 * Resolve the button background from the design system.
	 *
	 * The button's background is either a portable hex written by a raw colour
	 * picker, or a preset slug that the active Brand Kit resolves to a hex. A
	 * button that never chose a colour falls back to the kit's brand slot.
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 * @return string Portable hex color.
	 */
	private function resolve_background( Block_Node $block, Render_Context $context ): string {
		$kit   = $this->brand_kit( $context );
		$value = $block->attributes()['backgroundColor'];

		if ( '' !== $value ) {
			return Renderer_Support::resolve_color( $value, $kit, 'backgroundColor' );
		}

		return $kit->color( Brand_Kit::SLOT_BRAND ) ?? '#1a6dcc';
	}

	/**
	 * Resolve the button text colour from the design system.
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 * @return string Portable hex color.
	 */
	private function resolve_text( Block_Node $block, Render_Context $context ): string {
		$kit   = $this->brand_kit( $context );
		$value = $block->attributes()['textColor'];

		if ( '' !== $value ) {
			return Renderer_Support::resolve_color( $value, $kit, 'textColor' );
		}

		return $kit->color( Brand_Kit::SLOT_ON_BRAND ) ?? '#ffffff';
	}

	/**
	 * Read the active Brand Kit from the context, defaulting to the kit
	 * defaults when none is supplied.
	 *
	 * @param Render_Context $context Immutable scoped context.
	 * @return Brand_Kit Resolved Brand Kit.
	 */
	private function brand_kit( Render_Context $context ): Brand_Kit {
		$kit = $context->metadata( 'brandKit' );

		return $kit instanceof Brand_Kit ? $kit : Brand_Kit::defaults();
	}
}
