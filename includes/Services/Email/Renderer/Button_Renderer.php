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
use CampaignBridge\Domain\Email\Compile_Diagnostic;
use CampaignBridge\Domain\Email\Render_Context;

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
		return array( 'label', 'url', 'align', 'backgroundColor', 'textColor' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$attributes = $block->attributes();
		$label      = is_string( $attributes['label'] ?? null ) ? trim( $attributes['label'] ) : '';

		return $block->with_attributes(
			array(
				'label'           => $label,
				'url'             => is_string( $attributes['url'] ?? null ) ? trim( $attributes['url'] ) : '',
				'align'           => Renderer_Support::alignment( $attributes['align'] ?? null ),
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
	public function validate( Block_Node $block, Render_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$attributes = $block->attributes();
		if ( '' === $attributes['label'] ) {
			return array( Compile_Diagnostic::error( 'button.label.empty', $block->path(), 'Email buttons require a label.' ) );
		}

		if ( 80 < strlen( $attributes['label'] ) ) {
			return array( Compile_Diagnostic::error( 'button.label.too_long', $block->path(), 'Email button labels cannot exceed 80 bytes.' ) );
		}

		if ( null === Renderer_Support::https_url( $attributes['url'] ) ) {
			return array( Compile_Diagnostic::error( 'button.url.invalid', $block->path(), 'Email buttons require an absolute HTTPS URL.' ) );
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
			$attributes['url'],
			$attributes['label'],
			$attributes['backgroundColor'],
			$attributes['textColor'],
			$attributes['align']
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
}
