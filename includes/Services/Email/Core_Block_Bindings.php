<?php
/**
 * Contract validation and normalization of Core block bindings.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email;

use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Invalid_Block_Attribute;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reads and applies the bounded post and Brand Kit binding contracts. */
final class Core_Block_Bindings {
	/**
	 * Read the bounded CampaignBridge bindings off one Core block.
	 *
	 * Only the source, block, attribute, field, and argument combinations the
	 * contract documents are accepted. Every other binding source, attribute,
	 * field, or argument fails closed with a stable diagnostic; this is a
	 * bounded email grammar, not a general Block Bindings interpreter.
	 *
	 * @param Block_Node $block Source block.
	 * @return array{post: array<string, array<string, int|string>>, brand: array<string, array{field: string}>} Canonical bindings keyed by target attribute.
	 * @throws Invalid_Block_Attribute When the binding metadata is unsupported.
	 */
	public function read( Block_Node $block ): array {
		$metadata = $block->attributes()['metadata'] ?? null;
		$raw      = is_array( $metadata ) ? ( $metadata['bindings'] ?? null ) : null;
		if ( null === $raw ) {
			return array(
				'post'  => array(),
				'brand' => array(),
			);
		}
		if ( ! is_array( $raw ) || array() === $raw || array_is_list( $raw ) ) {
			throw new Invalid_Block_Attribute( 'metadata.bindings', 'must map bound attribute names to one binding each.' );
		}

		$bindings = array(
			'post'  => array(),
			'brand' => array(),
		);
		foreach ( $raw as $attribute => $binding ) {
			$attribute = (string) $attribute;
			if ( ! is_array( $binding ) || array() !== array_diff( array_keys( $binding ), array( 'source', 'args' ) ) ) {
				throw new Invalid_Block_Attribute( 'metadata.bindings.' . $attribute, 'must declare only a binding source and its arguments.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
			}
			$source = $binding['source'] ?? null;
			if ( Email_Block_Contract::binding_source() === $source ) {
				$bindings['post'][ $attribute ] = $this->post_binding( $block->name(), $attribute, $binding );
				continue;
			}
			if ( Email_Block_Contract::brand_binding_source() === $source ) {
				$brand = $this->brand_binding( $block->name(), $attribute, $binding );
				if ( isset( $bindings['brand'][ $brand['target'] ] ) ) {
					throw new Invalid_Block_Attribute( 'metadata.bindings.' . $attribute, 'duplicates a bound email attribute.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
				}
				$bindings['brand'][ $brand['target'] ] = array( 'field' => $brand['field'] );
				continue;
			}
			throw new Invalid_Block_Attribute( 'metadata.bindings.' . $attribute . '.source', 'must be a supported CampaignBridge read-only binding source.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
		}
		ksort( $bindings['post'], SORT_STRING );
		ksort( $bindings['brand'], SORT_STRING );

		return $bindings;
	}

	/**
	 * Validate one frozen Brand Kit binding against the contract.
	 *
	 * @param string $block_name Core block name.
	 * @param string $attribute  Bound attribute name.
	 * @param mixed  $binding    Raw binding metadata.
	 * @return array{field: string, target: string}
	 * @throws Invalid_Block_Attribute When the binding is unsupported.
	 */
	private function brand_binding( string $block_name, string $attribute, mixed $binding ): array {
		$path = 'metadata.bindings.' . $attribute;
		if ( ! is_array( $binding ) || array() !== array_diff( array_keys( $binding ), array( 'source', 'args' ) ) ) {
			throw new Invalid_Block_Attribute( $path, 'must declare only a binding source and its arguments.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
		}

		$rule = Email_Block_Contract::brand_binding( $block_name, $attribute );
		if ( null === $rule ) {
			throw new Invalid_Block_Attribute( $path, 'is not a Brand Kit bindable email attribute for this block.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
		}

		$args = $binding['args'] ?? null;
		if ( ! is_array( $args ) || array( 'field' ) !== array_keys( $args ) || $rule['field'] !== $args['field'] ) {
			throw new Invalid_Block_Attribute( $path . '.args.field', 'must name the documented Brand Kit field.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
		}

		return $rule;
	}

	/**
	 * Validate one binding against the contract.
	 *
	 * @param string $block_name Core block name.
	 * @param string $attribute  Bound attribute name.
	 * @param mixed  $binding    Raw binding metadata.
	 * @return array<string, int|string> Canonical binding.
	 * @throws Invalid_Block_Attribute When the binding is unsupported.
	 */
	private function post_binding( string $block_name, string $attribute, mixed $binding ): array {
		$path = 'metadata.bindings.' . $attribute;
		if ( ! is_array( $binding ) || array() !== array_diff( array_keys( $binding ), array( 'source', 'args' ) ) ) {
			throw new Invalid_Block_Attribute( $path, 'must declare only a binding source and its arguments.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
		}
		if ( Email_Block_Contract::binding_source() !== ( $binding['source'] ?? null ) ) {
			throw new Invalid_Block_Attribute( $path . '.source', 'must be the CampaignBridge read-only post binding source.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
		}

		$rule = Email_Block_Contract::binding( $block_name, $attribute );
		if ( null === $rule ) {
			throw new Invalid_Block_Attribute( $path, 'is not a bindable email attribute for this block.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
		}

		$args = $binding['args'] ?? null;
		if ( ! is_array( $args ) || ( array() !== $args && array_is_list( $args ) ) ) {
			throw new Invalid_Block_Attribute( $path . '.args', 'must be a binding argument object.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
		}

		$field = $args['field'] ?? null;
		if ( ! is_string( $field ) || ! isset( $rule['fields'][ $field ] ) ) {
			throw new Invalid_Block_Attribute( $path . '.args.field', 'must name a supported post snapshot field.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
		}

		$unknown = array_diff( array_keys( $args ), array_merge( array( 'field' ), array_keys( $rule['args'] ) ) );
		if ( array() !== $unknown ) {
			throw new Invalid_Block_Attribute( $path . '.args.' . (string) reset( $unknown ), 'is not a supported binding argument.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
		}

		$canonical = array( 'field' => $field );
		foreach ( $rule['args'] as $name => $schema ) {
			$value = $args[ $name ] ?? $schema['default'];
			if ( ! is_int( $value ) || $schema['min'] > $value || $schema['max'] < $value ) {
				throw new Invalid_Block_Attribute( $path . '.args.' . $name, 'must be an integer within its documented range.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
			}
			$canonical[ $name ] = $value;
		}

		return $canonical;
	}

	/**
	 * Replace bound attributes with canonical compiler binding semantics.
	 *
	 * A bound attribute carries no literal value: the compiler substitutes the
	 * immutable snapshot value. An attribute that holds both fails closed.
	 *
	 * @param Block_Node                                                                                        $block    Canonical Core block.
	 * @param array{post: array<string, array<string, int|string>>, brand: array<string, array{field: string}>} $bindings Canonical bindings.
	 * @return Block_Node Block carrying its post bindings.
	 * @throws Invalid_Block_Attribute When a bound attribute also holds a literal value.
	 */
	public function apply( Block_Node $block, array $bindings ): Block_Node {
		$attributes = $block->attributes();
		foreach ( $bindings as $kind => $group ) {
			foreach ( array_keys( $group ) as $attribute ) {
				$literal = $attributes[ $attribute ] ?? '';
				if ( ! is_string( $literal ) || '' !== trim( $literal ) ) {
					throw new Invalid_Block_Attribute( $attribute, 'cannot hold a literal value and a binding at the same time.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
				}
				$attributes[ $attribute ] = '';
			}
			if ( array() !== $group ) {
				$attributes[ $kind . 'Bindings' ] = $group;
			}
		}

		return $block->with_attributes( $attributes );
	}
}
