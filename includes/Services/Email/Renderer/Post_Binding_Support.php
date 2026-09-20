<?php
/**
 * Immutable post snapshot resolution for bound Core blocks.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Compile_Diagnostic;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Services\Email\Email_Block_Contract;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compiles a Core block's post bindings from the immutable snapshot.
 *
 * Block Bindings are an authoring concern: the saved `metadata.bindings` only
 * records which semantic post value a Core attribute stands for. The binding
 * source is never executed here and no WordPress post is read. The compile-time
 * value always comes from the Post Card's frozen {@see \CampaignBridge\Domain\Email\Post_Snapshot}.
 */
final class Post_Binding_Support {
	/** Canonical attribute holding a block's validated post bindings. */
	public const ATTRIBUTE = 'postBindings';

	/**
	 * Read one block's canonical bindings.
	 *
	 * @param Block_Node $block Normalized block.
	 * @return array<string, array<string, int|string>> Bindings keyed by bound attribute.
	 */
	public static function bindings( Block_Node $block ): array {
		$bindings = $block->attributes()[ self::ATTRIBUTE ] ?? array();

		return is_array( $bindings ) ? $bindings : array();
	}

	/**
	 * Carry validated bindings through a renderer's attribute canonicalization.
	 *
	 * @param Block_Node $block Source block.
	 * @return array<string, array<string, array<string, int|string>>> Empty when the block is unbound.
	 */
	public static function carry( Block_Node $block ): array {
		$bindings = self::bindings( $block );

		return array() === $bindings ? array() : array( self::ATTRIBUTE => $bindings );
	}

	/**
	 * Name the snapshot fields a block reads, for token provenance checks.
	 *
	 * A linked field reads two snapshot fields — the value and its
	 * destination — and both must clear the provenance check.
	 *
	 * @param Block_Node $block Normalized block.
	 * @return array<int, string>
	 */
	public static function fields( Block_Node $block ): array {
		$fields = array();
		foreach ( self::bindings( $block ) as $attribute => $binding ) {
			$rule = self::field_rule( $block, (string) $attribute, $binding );
			if ( null === $rule ) {
				continue;
			}

			$fields[] = $rule['reads'];
			if ( null !== $rule['link'] ) {
				$fields[] = $rule['link'];
			}
		}

		return array_values( array_unique( $fields ) );
	}

	/**
	 * Resolve the destination a bound value links to, if any.
	 *
	 * Presentation stays with the renderer: the binding only says which
	 * immutable snapshot field supplies the destination.
	 *
	 * @param Block_Node     $block     Normalized block.
	 * @param string         $attribute Bound attribute name.
	 * @param Render_Context $context   Immutable scoped context.
	 * @return string|null Absolute URL, or null when the value is not linked.
	 */
	public static function link_url( Block_Node $block, string $attribute, Render_Context $context ): ?string {
		$binding = self::bindings( $block )[ $attribute ] ?? null;
		$rule    = null === $binding ? null : self::field_rule( $block, $attribute, $binding );
		if ( null === $rule || null === $rule['link'] ) {
			return null;
		}

		return Renderer_Support::https_url( $context->post_binding()?->get( $rule['link'] ) );
	}

	/**
	 * Read the contract rule behind one canonical binding.
	 *
	 * @param Block_Node                $block     Normalized block.
	 * @param string                    $attribute Bound attribute name.
	 * @param array<string, int|string> $binding   Canonical binding.
	 * @return array{reads: string, link: string|null}|null
	 */
	private static function field_rule( Block_Node $block, string $attribute, array $binding ): ?array {
		return Email_Block_Contract::binding_field( $block->name(), $attribute, (string) $binding['field'] );
	}

	/**
	 * Substitute immutable snapshot values into the bound attributes.
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 * @return Block_Node Block whose bound attributes carry snapshot values.
	 */
	public static function resolve( Block_Node $block, Render_Context $context ): Block_Node {
		$bindings = self::bindings( $block );
		if ( array() === $bindings ) {
			return $block;
		}

		$post       = $context->post_binding();
		$attributes = $block->attributes();
		foreach ( $bindings as $attribute => $binding ) {
			$rule  = Email_Block_Contract::binding( $block->name(), (string) $attribute );
			$field = self::field_rule( $block, (string) $attribute, $binding );
			$value = null === $post || null === $field ? null : $post->get( $field['reads'] );

			if ( ! is_string( $value ) || null === $rule ) {
				$attributes[ $attribute ] = '';
				continue;
			}

			$attributes[ $attribute ] = 'rich-text' === $rule['projection']
				? Renderer_Support::html( Renderer_Support::truncate_words( $value, self::max_words( $binding ) ) )
				: trim( $value );
		}

		return $block->with_attributes( $attributes );
	}

	/**
	 * Report a binding that no immutable snapshot can satisfy.
	 *
	 * @param Block_Node     $block   Normalized block.
	 * @param Render_Context $context Immutable scoped context.
	 * @return array<int, Compile_Diagnostic>
	 */
	public static function validate( Block_Node $block, Render_Context $context ): array {
		$bindings = self::bindings( $block );
		if ( array() === $bindings ) {
			return array();
		}

		$post = $context->post_binding();
		if ( null === $post ) {
			return array(
				Compile_Diagnostic::error(
					'post.binding.unbound',
					$block->path(),
					'A post-bound block must sit inside a Post Card whose snapshot resolved.'
				),
			);
		}

		foreach ( $bindings as $attribute => $binding ) {
			$rule = self::field_rule( $block, (string) $attribute, $binding );
			if ( null === $rule ) {
				continue;
			}

			$required = null === $rule['link'] ? array( $rule['reads'] ) : array( $rule['reads'], $rule['link'] );
			foreach ( $required as $field ) {
				$value = $post->get( $field );
				if ( is_string( $value ) && '' !== trim( $value ) ) {
					continue;
				}

				return array(
					Compile_Diagnostic::error(
						'post.binding.missing',
						$block->path() . '.attrs.' . $attribute,
						sprintf( 'The post snapshot carries no "%s" value for this binding.', $field )
					),
				);
			}
		}

		return array();
	}

	/**
	 * Read the validated word cap of one binding.
	 *
	 * @param array<string, int|string> $binding Canonical binding.
	 */
	private static function max_words( array $binding ): int {
		$value = $binding['maxWords'] ?? null;

		return is_int( $value ) ? $value : PHP_INT_MAX;
	}
}
