<?php
/**
 * Native email compliance footer renderer.
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

/**
 * Renders the required sender identity and unsubscribe controls.
 *
 * The sender name and postal address are authored content and live on the
 * block. The unsubscribe destination is per-send and is read from immutable
 * context metadata, so no provider merge syntax reaches this renderer.
 */
final class Compliance_Footer_Renderer extends Abstract_Renderer {
	public const MAX_ADDRESS_LENGTH = 300;

	private const DEFAULT_PADDING = array(
		'top'    => 24,
		'right'  => 0,
		'bottom' => 24,
		'left'   => 0,
	);

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/compliance-footer';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'businessName', 'address', 'unsubscribeLabel', 'padding', 'textColor' );
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
				'businessName'     => trim( Renderer_Support::string_attribute( $attributes, 'businessName', '' ) ),
				'address'          => trim( preg_replace( '/\s+/u', ' ', Renderer_Support::string_attribute( $attributes, 'address', '' ) ) ?? '' ),
				'unsubscribeLabel' => trim( Renderer_Support::string_attribute( $attributes, 'unsubscribeLabel', 'Unsubscribe' ) ),
				'padding'          => Renderer_Support::spacing_attribute( $attributes, 'padding', self::DEFAULT_PADDING ),
				'textColor'        => Renderer_Support::color_attribute( $attributes, 'textColor', '#666666' ),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Compliance data is required before a campaign can be approved, so every
	 * missing part is an error rather than a warning.
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 * @return array<int, Compile_Diagnostic>
	 */
	public function validate( Block_Node $block, Render_Context $context ): array {
		$attributes  = $block->attributes();
		$diagnostics = array();

		if ( '' === $attributes['address'] ) {
			$diagnostics[] = Compile_Diagnostic::error(
				'compliance.address.missing',
				$block->path(),
				'A physical postal address is required in the compliance footer.'
			);
		} elseif ( self::MAX_ADDRESS_LENGTH < mb_strlen( (string) $attributes['address'] ) ) {
			$diagnostics[] = Compile_Diagnostic::error(
				'compliance.address.too_long',
				$block->path(),
				sprintf( 'The postal address cannot exceed %d characters.', self::MAX_ADDRESS_LENGTH )
			);
		}

		if ( '' === $attributes['unsubscribeLabel'] ) {
			$diagnostics[] = Compile_Diagnostic::error(
				'compliance.unsubscribe.label_missing',
				$block->path(),
				'The unsubscribe link needs visible link text.'
			);
		}

		if ( null === $this->unsubscribe_url( $context ) ) {
			$diagnostics[] = Compile_Diagnostic::error(
				'compliance.unsubscribe.missing',
				$block->path(),
				'This template has no HTTPS unsubscribe URL. Set one on the email template before approval.'
			);
		}

		return $diagnostics;
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
		$padding    = $attributes['padding'];
		$lines      = array();

		if ( '' !== $attributes['businessName'] ) {
			$lines[] = Renderer_Support::html( (string) $attributes['businessName'] );
		}

		$lines[] = Renderer_Support::html( (string) $attributes['address'] );
		$lines[] = sprintf(
			'<a href="%1$s" style="color:%2$s;text-decoration:underline">%3$s</a>',
			Renderer_Support::html( (string) $this->unsubscribe_url( $context ) ),
			$attributes['textColor'],
			Renderer_Support::html( (string) $attributes['unsubscribeLabel'] )
		);

		return sprintf(
			'<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" style="width:100%%;border-collapse:collapse"><tr><td align="center" style="padding:%1$dpx %2$dpx %3$dpx %4$dpx;color:%5$s;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;text-align:center">%6$s</td></tr></table>',
			$padding['top'],
			$padding['right'],
			$padding['bottom'],
			$padding['left'],
			$attributes['textColor'],
			implode( '<br>', $lines )
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
		$attributes = $block->attributes();
		$lines      = array();

		if ( '' !== $attributes['businessName'] ) {
			$lines[] = (string) $attributes['businessName'];
		}

		$lines[] = (string) $attributes['address'];
		$lines[] = sprintf(
			'%1$s: %2$s',
			(string) $attributes['unsubscribeLabel'],
			(string) $this->unsubscribe_url( $context )
		);

		return implode( "\n", $lines ) . "\n\n";
	}

	/**
	 * Read the validated per-send unsubscribe destination.
	 *
	 * @param Render_Context $context Immutable scoped context.
	 */
	private function unsubscribe_url( Render_Context $context ): ?string {
		return Renderer_Support::https_url( $context->metadata( 'unsubscribe_url' ) );
	}
}
