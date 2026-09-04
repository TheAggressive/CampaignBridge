<?php
/**
 * Native email preheader renderer.
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
 * Renders hidden inbox preview text.
 *
 * Clients show the first readable text after the subject line. Without an
 * explicit preheader they scrape whatever the layout happens to start with, so
 * this block owns that text and then pads it with zero-width joiners to stop
 * body copy leaking into the preview.
 */
final class Preheader_Renderer extends Abstract_Renderer {
	public const MAX_LENGTH = 150;

	private const HIDDEN_STYLE = 'display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all';

	private const SPACER_ENTITY = '&#847;&zwnj;&nbsp;';

	private const SPACER_REPEAT = 30;

	/** {@inheritDoc} */
	public function block_name(): string {
		return 'campaignbridge/preheader';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'content' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		$content = Renderer_Support::string_attribute( $block->attributes(), 'content', '' );

		return $block->with_attributes(
			array(
				'content' => trim( preg_replace( '/\s+/u', ' ', $content ) ?? '' ),
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 * @return array<int, Compile_Diagnostic>
	 */
	public function validate( Block_Node $block, Render_Context $context ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$content = (string) $block->attributes()['content'];

		if ( '' === $content ) {
			return array(
				Compile_Diagnostic::error(
					'preheader.content.missing',
					$block->path(),
					'Preview text is required. Remove the preheader block or enter the text inbox previews should show.'
				),
			);
		}

		$length = mb_strlen( $content );
		if ( self::MAX_LENGTH < $length ) {
			return array(
				Compile_Diagnostic::error(
					'preheader.content.too_long',
					$block->path(),
					sprintf( 'Preview text cannot exceed %1$d characters; found %2$d.', self::MAX_LENGTH, $length )
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
		return sprintf(
			'<div style="%1$s">%2$s%3$s</div>',
			self::HIDDEN_STYLE,
			Renderer_Support::html( (string) $block->attributes()['content'] ),
			str_repeat( self::SPACER_ENTITY, self::SPACER_REPEAT )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * The preview text is not part of the readable message body, so it is
	 * omitted from the plain-text artifact rather than duplicated at the top.
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child text.
	 * @param Render_Context $context  Immutable scoped context.
	 */
	public function render_text( Block_Node $block, string $children, Render_Context $context ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return '';
	}
}
