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
	 * Get accepted block style slugs.
	 *
	 * A non-empty list opts the renderer in to the compiler folding the
	 * selected `is-style-{slug}` class from `className` into the `style`
	 * attribute before whitelist validation.
	 *
	 * @return array<int, string>
	 */
	public function block_style_names(): array;

	/**
	 * Get normalized attributes that accept canonical email tokens.
	 *
	 * Only author-visible text and link destinations belong here. The compiler
	 * resolves these attributes after `normalize()` and before `validate()`,
	 * and rejects token syntax in every other string attribute.
	 *
	 * @return array<string, string> Attribute name => Token_Resolver::CONTEXT_* value.
	 */
	public function token_attributes(): array;

	/**
	 * Get the bound post snapshot fields this block emits into the artifact.
	 *
	 * Snapshot content is never a token context. The compiler rejects
	 * canonical token syntax in every declared field so each `{{cb:...}}` in
	 * a successful artifact originates from a `token_attributes()` attribute.
	 *
	 * @param Block_Node $block Normalized block.
	 * @return array<int, string> Snapshot field names; `image.alt` addresses a sub-field.
	 */
	public function snapshot_fields( Block_Node $block ): array;

	/**
	 * Substitute immutable post snapshot values into bound attributes.
	 *
	 * Called after token resolution and snapshot token rejection, and before
	 * validation, so a bound value is validated exactly like an authored one
	 * and can never be mistaken for author-owned token syntax. Renderers never
	 * execute an editor binding source and never read a live WordPress post.
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 */
	public function resolve_post_bindings( Block_Node $block, Render_Context $context ): Block_Node;

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
