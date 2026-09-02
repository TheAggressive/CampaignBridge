<?php
/**
 * Email block renderer contract.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Renderer_Interface {
	/** Get the one stable block name handled by this renderer. */
	public function block_name(): string;

	/**
	 * Get accepted semantic attribute names.
	 *
	 * @return array<int, string>
	 */
	public function attribute_names(): array;

	/**
	 * Get accepted child block names.
	 *
	 * @return array<int, string>|null Null permits any registered child.
	 */
	public function allowed_children(): ?array;

	/**
	 * Supply omitted defaults and perform lossless canonicalization.
	 *
	 * @param Block_Node $block Source block.
	 * @throws Invalid_Block_Attribute When an explicit persisted value is malformed.
	 */
	public function normalize( Block_Node $block ): Block_Node;

	/**
	 * Validate normalized values against context.
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 * @return array<int, Compile_Diagnostic>
	 */
	public function validate( Block_Node $block, Render_Context $context ): array;

	/**
	 * Derive the immutable context passed to child blocks.
	 *
	 * @param Block_Node     $block   Normalized parent block.
	 * @param Render_Context $context Immutable parent context.
	 */
	public function context_for_children( Block_Node $block, Render_Context $context ): Render_Context;

	/**
	 * Return external assets referenced by this block.
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 * @return array<int, array<string, mixed>>
	 */
	public function referenced_assets( Block_Node $block, Render_Context $context ): array;

	/**
	 * Render canonical email HTML.
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child HTML.
	 * @param Render_Context $context  Immutable scoped context.
	 */
	public function render_html( Block_Node $block, string $children, Render_Context $context ): string;

	/**
	 * Render canonical plain text.
	 *
	 * @param Block_Node     $block    Normalized block.
	 * @param string         $children Compiled child text.
	 * @param Render_Context $context  Immutable scoped context.
	 */
	public function render_text( Block_Node $block, string $children, Render_Context $context ): string;
}
