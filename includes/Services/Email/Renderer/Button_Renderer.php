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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders a validated bulletproof call to action. */
final class Button_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/button';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'label', 'url', 'align', 'style', 'backgroundColor', 'textColor' );
	}

	/** {@inheritDoc} */
	public function block_style_names(): array {
		return array( 'primary', 'outline', 'ghost' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = $block->attributes();
		$styles     = array( 'primary', 'outline', 'ghost' );

		return $block->with_attributes(
			array(
				'label'           => trim( Renderer_Support::string_attribute( $attributes, 'label', 'Learn more' ) ),
				'url'             => trim( Renderer_Support::string_attribute( $attributes, 'url', '' ) ),
				'align'           => Renderer_Support::alignment_attribute( $attributes, 'align' ),
				'style'           => Renderer_Support::choice_attribute( $attributes, 'style', 'primary', $styles ),
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
	public function validate( Block_Node $block, Render_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$attributes = $block->attributes();
		if ( '' === $attributes['label'] ) {
			return array( Compile_Diagnostic::error( 'button.label.empty', $block->path(), 'Email buttons require a label.' ) );
		}

		if ( 80 < strlen( $attributes['label'] ) ) {
			return array( Compile_Diagnostic::error( 'button.label.too_long', $block->path(), 'Email button labels cannot exceed 80 bytes.' ) );
		}

		if ( null === Renderer_Support::safe_url( $attributes['url'] ) ) {
			return array( Compile_Diagnostic::error( 'button.url.invalid', $block->path(), 'Email buttons require an absolute URL.' ) );
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
		$variant    = $attributes['style'];

		// Outline and ghost variants are monochromatic: the chosen background
		// drives the border, the visible text, and the Outlook VML paint, so a
		// separate text slot is ignored for them.
		if ( 'primary' !== $variant ) {
			$text = $background;
		}

		return Button_Markup::html(
			$attributes['url'],
			$attributes['label'],
			$background,
			$text,
			$attributes['align'],
			200,
			$variant
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
			return $this->resolve_color( $value, $kit );
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
			return $this->resolve_color( $value, $kit );
		}

		return $kit->color( Brand_Kit::SLOT_ON_BRAND ) ?? '#ffffff';
	}

	/**
	 * Resolve a single colour value against the active Brand Kit.
	 *
	 * Accepts, in order:
	 *  1. A portable hex (returned as-is, normalised to lower case).
	 *  2. A design-system preset reference (`var:preset|color|<slug>`), expanded
	 *     through the Style_Resolver.
	 *  3. A bare Brand Kit slot slug (e.g. `brand`), expanded directly against
	 *     the kit so editor palettes and saved blocks keep working.
	 *
	 * Anything else degrades to the kit's brand slot so a single bad colour
	 * cannot break an entire send.
	 *
	 * @param string    $value Raw attribute value (hex, preset ref, or slug).
	 * @param Brand_Kit $kit   Active Brand Kit.
	 * @return string Portable hex color.
	 */
	private function resolve_color( string $value, Brand_Kit $kit ): string {
		$normalized = trim( $value );

		// 1. Portable hex passes straight through.
		if ( 1 === preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $normalized ) ) {
			return Brand_Kit::normalize_hex( $normalized ) ?? $normalized;
		}

		// 2. Design-system preset reference (var:preset|color|<slug>).
		if ( str_starts_with( $normalized, 'var:preset|color|' ) ) {
			$slug = substr( $normalized, strlen( 'var:preset|color|' ) );
			$hex  = $kit->color( $slug );

			if ( null !== $hex ) {
				return $hex;
			}

			return $kit->color( Brand_Kit::SLOT_BRAND ) ?? '#1a6dcc';
		}

		// 3. Bare Brand Kit slot slug.
		if ( null !== $kit->color( $normalized ) ) {
			return $kit->color( $normalized );
		}

		// 4. Unknown value: degrade to the brand slot.
		return $kit->color( Brand_Kit::SLOT_BRAND ) ?? '#1a6dcc';
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
