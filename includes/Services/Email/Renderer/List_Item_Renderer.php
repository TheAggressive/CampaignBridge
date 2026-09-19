<?php
/**
 * Native Core list item renderer.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Abstract_Renderer;
use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Compile_Diagnostic;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Domain\Email\Token\Token_Resolver;
use CampaignBridge\Services\Email\Email_Block_Contract;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders one safe Core list item. */
final class List_Item_Renderer extends Abstract_Renderer {
	/** {@inheritDoc} */
	public function block_name(): string {
		return 'core/list-item';
	}

	/** {@inheritDoc} */
	public function attribute_names(): array {
		return array( 'content' );
	}

	/** {@inheritDoc} */
	public function token_attributes(): array {
		return array( 'content' => Token_Resolver::CONTEXT_RICH_TEXT );
	}

	/** {@inheritDoc} */
	public function allowed_children(): array {
		return Email_Block_Contract::children( $this->block_name() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node $block Source block.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		return $block->with_attributes( array( 'content' => Renderer_Support::string_attribute( $block->attributes(), 'content', '' ) ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public function validate( Block_Node $block, Render_Context $context ): array {
		$content = $block->attributes()['content'];
		if ( '' === trim( wp_strip_all_tags( $content ) ) || null === Renderer_Support::rich_text( $content ) ) {
			return array( Compile_Diagnostic::error( 'list-item.content.invalid', $block->path(), 'Core list items require safe, visible rich text.' ) );
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
		return '<li style="margin:0 0 8px 0">' . (string) Renderer_Support::rich_text( $block->attributes()['content'] ) . '</li>';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child text.
	 * @param Render_Context $context  Immutable scoped context.
	 */
	public function render_text( Block_Node $block, string $children, Render_Context $context ): string {
		// One item is one plain-text line; the list renderer adds its marker.
		return str_replace( "\n", ' ', (string) Renderer_Support::rich_text_to_plain( $block->attributes()['content'] ) ) . "\n";
	}
}
