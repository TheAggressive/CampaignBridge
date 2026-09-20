<?php
/**
 * Normalizes the bounded WordPress Core authoring block subset.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email;

use CampaignBridge\Domain\Email\Authoring_Block_Normalizer;
use CampaignBridge\Domain\Email\Block_Node;
use CampaignBridge\Domain\Email\Invalid_Block_Attribute;
use CampaignBridge\Domain\Email\Style_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts known Core block serialization into CampaignBridge email semantics.
 *
 * Each supported Core block is read through its own serialization contract:
 * comment attributes plus, where Core sources a value from saved markup, only
 * the exact wrapper that Core's `save()` produces. Unknown attributes, block
 * styles, custom classes, and markup shapes fail closed with a stable
 * `block.attribute.invalid` diagnostic. Core frontend rendering is never used.
 * Non-Core blocks pass through unchanged.
 */
final class Core_Block_Normalizer implements Authoring_Block_Normalizer {
	/**
	 * Email semantics this normalizer can produce from Core serialization.
	 *
	 * @var array<int, string>
	 */
	public const SEMANTICS = array( 'text', 'heading', 'image', 'button-group', 'button', 'list', 'list-item', 'divider', 'spacer' );

	/**
	 * Editor-only attributes that carry no email meaning and are dropped.
	 *
	 * @var array<int, string>
	 */
	private const EDITOR_ONLY = array( 'lock', 'placeholder' );

	/** Core's serialized spacer height when the attribute is omitted. */
	private const CORE_SPACER_HEIGHT = 100;

	/**
	 * Normalize one supported Core node while retaining its Core name.
	 *
	 * Known Core serialization that is malformed or unsupported raises
	 * Invalid_Block_Attribute from the block-specific readers.
	 *
	 * @param Block_Node $block Parsed Core or CampaignBridge node.
	 * @return Block_Node Canonical semantic node.
	 * @throws \DomainException When the contract names a Core block without a normalizer.
	 */
	public function normalize( Block_Node $block ): Block_Node {
		if ( ! Email_Block_Contract::is_core( $block->name() ) ) {
			return $block;
		}

		$bindings  = $this->post_bindings( $block );
		$canonical = match ( Email_Block_Contract::semantics( $block->name() ) ) {
			'text'         => $this->paragraph( $block ),
			'heading'      => $this->heading( $block ),
			'image'        => $this->image( $block ),
			'button-group' => $this->buttons( $block ),
			'button'       => $this->button( $block ),
			'list'         => $this->list( $block ),
			'list-item'    => $this->list_item( $block ),
			'divider'      => $this->separator( $block ),
			'spacer'       => $this->spacer( $block ),
			default        => throw new \DomainException( 'The email block contract names a Core block without a normalizer.' ),
		};

		return array() === $bindings ? $canonical : $this->with_post_bindings( $canonical, $bindings );
	}

	/**
	 * Read the bounded CampaignBridge post bindings off one Core block.
	 *
	 * Only the source, block, attribute, field, and argument combinations the
	 * contract documents are accepted. Every other binding source, attribute,
	 * field, or argument fails closed with a stable diagnostic; this is a
	 * bounded email grammar, not a general Block Bindings interpreter.
	 *
	 * @param Block_Node $block Source block.
	 * @return array<string, array<string, int|string>> Canonical bindings keyed by attribute.
	 * @throws Invalid_Block_Attribute When the binding metadata is unsupported.
	 */
	private function post_bindings( Block_Node $block ): array {
		$metadata = $block->attributes()['metadata'] ?? null;
		$raw      = is_array( $metadata ) ? ( $metadata['bindings'] ?? null ) : null;
		if ( null === $raw ) {
			return array();
		}
		if ( ! is_array( $raw ) || array() === $raw || array_is_list( $raw ) ) {
			throw new Invalid_Block_Attribute( 'metadata.bindings', 'must map bound attribute names to one binding each.' );
		}

		$bindings = array();
		foreach ( $raw as $attribute => $binding ) {
			$attribute              = (string) $attribute;
			$bindings[ $attribute ] = $this->post_binding( $block->name(), $attribute, $binding );
		}
		ksort( $bindings, SORT_STRING );

		return $bindings;
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
	 * Replace bound attributes with the canonical `postBindings` semantics.
	 *
	 * A bound attribute carries no literal value: the compiler substitutes the
	 * immutable snapshot value. An attribute that holds both fails closed.
	 *
	 * @param Block_Node                               $block    Canonical Core block.
	 * @param array<string, array<string, int|string>> $bindings Canonical bindings.
	 * @return Block_Node Block carrying its post bindings.
	 * @throws Invalid_Block_Attribute When a bound attribute also holds a literal value.
	 */
	private function with_post_bindings( Block_Node $block, array $bindings ): Block_Node {
		$attributes = $block->attributes();
		foreach ( array_keys( $bindings ) as $attribute ) {
			$literal = $attributes[ $attribute ] ?? '';
			if ( ! is_string( $literal ) || '' !== trim( $literal ) ) {
				throw new Invalid_Block_Attribute( $attribute, 'cannot hold a literal value and a post binding at the same time.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
			}
			$attributes[ $attribute ] = '';
		}
		$attributes['postBindings'] = $bindings;

		return $block->with_attributes( $attributes );
	}

	/**
	 * Normalize `core/paragraph`.
	 *
	 * @param Block_Node $block Source block.
	 * @return Block_Node Normalized block.
	 * @throws Invalid_Block_Attribute When Core serialization is unsupported.
	 */
	private function paragraph( Block_Node $block ): Block_Node {
		$attributes = $this->attributes( $block, array( 'content', 'dropCap', 'style', 'textColor', 'backgroundColor', 'fontSize', 'fontFamily' ) );
		if ( true === ( $attributes['dropCap'] ?? false ) ) {
			throw new Invalid_Block_Attribute( 'dropCap', 'is not supported by email text.' );
		}
		unset( $attributes['dropCap'] );

		$attributes            = $this->font_weight( $this->text_alignment( $attributes ) );
		$attributes['content'] = $this->rich_text( $attributes, 'content', $block->inner_html(), 'p' );

		return $block->with_attributes( $attributes );
	}

	/**
	 * Normalize `core/heading`.
	 *
	 * @param Block_Node $block Source block.
	 * @return Block_Node Normalized block.
	 * @throws Invalid_Block_Attribute When Core serialization is unsupported.
	 */
	private function heading( Block_Node $block ): Block_Node {
		$attributes = $this->attributes( $block, array( 'content', 'level', 'levelOptions', 'style', 'textColor', 'fontSize', 'fontFamily' ) );
		unset( $attributes['levelOptions'] );

		$level = $attributes['level'] ?? 2;
		if ( ! is_int( $level ) || 1 > $level || 6 < $level ) {
			throw new Invalid_Block_Attribute( 'level', 'must be a heading level from 1 through 6.' );
		}

		$html = trim( $block->inner_html() );
		if ( '' !== $html && 1 !== preg_match( '/^<h' . $level . '\b/i', $html ) ) {
			throw new Invalid_Block_Attribute( 'level', 'does not match the serialized heading element.' );
		}

		$attributes            = $this->font_weight( $this->text_alignment( $attributes ) );
		$attributes['content'] = $this->rich_text( $attributes, 'content', $html, 'h' . $level );
		$attributes['level']   = $level;

		return $block->with_attributes( $attributes );
	}

	/**
	 * Normalize `core/image`, including Media Library and external images.
	 *
	 * @param Block_Node $block Source block.
	 * @return Block_Node Normalized block.
	 * @throws Invalid_Block_Attribute When Core serialization is unsupported.
	 */
	private function image( Block_Node $block ): Block_Node {
		$attributes = $this->attributes(
			$block,
			array( 'url', 'alt', 'id', 'sizeSlug', 'linkDestination', 'linkTarget', 'width', 'height', 'aspectRatio', 'isDecorative', 'align', 'lightbox', 'style', 'className' )
		);
		$this->block_style( $attributes, array( 'default' => 'default' ), 'default' );
		if ( 'auto' !== ( $attributes['aspectRatio'] ?? 'auto' ) ) {
			throw new Invalid_Block_Attribute( 'aspectRatio', 'cannot be represented in email; resize the image instead.' );
		}
		$markup = $this->image_markup( $block->inner_html() );
		$align  = $attributes['align'] ?? '';
		if ( ! in_array( $align, array( '', 'left', 'center', 'right' ), true ) ) {
			throw new Invalid_Block_Attribute( 'align', 'must be left, center, or right in email.' );
		}

		$decorative = $attributes['isDecorative'] ?? false;
		if ( ! is_bool( $decorative ) ) {
			throw new Invalid_Block_Attribute( 'isDecorative', 'must be a boolean.' );
		}

		$canonical = array(
			'url'        => $this->string_value( $attributes['url'] ?? $markup['url'] ?? '', 'url' ),
			'alt'        => $this->string_value( $attributes['alt'] ?? $markup['alt'] ?? '', 'alt' ),
			'decorative' => $decorative,
			'width'      => $this->pixels( $attributes['width'] ?? null, 'width' ) ?? 600,
			'height'     => $this->pixels( $attributes['height'] ?? null, 'height' ),
			'linkUrl'    => 'none' === ( $attributes['linkDestination'] ?? '' ) ? '' : ( $markup['href'] ?? '' ),
			'align'      => $align,
		);
		if ( isset( $attributes['style'] ) ) {
			$canonical['style'] = $attributes['style'];
		}

		return $block->with_attributes( $canonical );
	}

	/**
	 * Normalize the `core/buttons` wrapper into one shared alignment.
	 *
	 * @param Block_Node $block Source block.
	 * @return Block_Node Normalized block.
	 * @throws Invalid_Block_Attribute When Core serialization is unsupported.
	 */
	private function buttons( Block_Node $block ): Block_Node {
		$attributes = $this->attributes( $block, array( 'layout' ) );
		$layout     = $attributes['layout'] ?? array();
		if ( ! is_array( $layout ) ) {
			throw new Invalid_Block_Attribute( 'layout', 'must be a Core flex layout.' );
		}
		foreach ( array_keys( $layout ) as $key ) {
			if ( ! in_array( $key, array( 'type', 'justifyContent', 'orientation', 'flexWrap' ), true ) ) {
				throw new Invalid_Block_Attribute( 'layout.' . $key, 'is not supported by email buttons.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
			}
		}
		if ( 'flex' !== ( $layout['type'] ?? 'flex' ) ) {
			throw new Invalid_Block_Attribute( 'layout.type', 'must be flex.' );
		}

		$justify = $layout['justifyContent'] ?? 'left';
		if ( ! in_array( $justify, array( 'left', 'center', 'right' ), true ) ) {
			throw new Invalid_Block_Attribute( 'layout.justifyContent', 'must be left, center, or right in email.' );
		}

		return $block->with_attributes( array( 'align' => $justify ) );
	}

	/**
	 * Normalize one `core/button` into the bulletproof button semantics.
	 *
	 * @param Block_Node $block Source block.
	 * @return Block_Node Normalized block.
	 * @throws Invalid_Block_Attribute When Core serialization is unsupported.
	 */
	private function button( Block_Node $block ): Block_Node {
		$attributes = $this->attributes(
			$block,
			array( 'url', 'text', 'tagName', 'type', 'title', 'linkTarget', 'rel', 'backgroundColor', 'textColor', 'fontFamily', 'style', 'className' )
		);
		if ( 'a' !== ( $block->attributes()['tagName'] ?? 'a' ) ) {
			throw new Invalid_Block_Attribute( 'tagName', 'must be a link in email.' );
		}

		$markup = $this->button_markup( $block->inner_html() );
		$text   = $attributes['text'] ?? $markup['text'] ?? '';
		$label  = Renderer\Renderer_Support::rich_text_to_plain( $this->canonical_links( $this->string_value( $text, 'text' ) ) );
		if ( null === $label ) {
			throw new Invalid_Block_Attribute( 'text', 'permits only safe inline rich text.' );
		}

		$canonical = array(
			'label'   => $label,
			'url'     => $this->string_value( $attributes['url'] ?? $markup['href'] ?? '', 'url' ),
			'variant' => $this->block_style(
				$attributes,
				array(
					'fill'    => 'primary',
					'outline' => 'outline',
					'ghost'   => 'ghost',
				),
				'primary' 
			),
		);
		foreach ( array( 'backgroundColor', 'textColor', 'fontFamily', 'style' ) as $key ) {
			if ( array_key_exists( $key, $attributes ) ) {
				$canonical[ $key ] = $attributes[ $key ];
			}
		}

		return $block->with_attributes( $canonical );
	}

	/**
	 * Normalize `core/list` into an ordered or unordered list.
	 *
	 * @param Block_Node $block Source block.
	 * @return Block_Node Normalized block.
	 * @throws Invalid_Block_Attribute When Core serialization is unsupported.
	 */
	private function list( Block_Node $block ): Block_Node {
		$attributes = $this->attributes( $block, array( 'ordered', 'values' ) );
		$ordered    = $attributes['ordered'] ?? false;
		if ( ! is_bool( $ordered ) ) {
			throw new Invalid_Block_Attribute( 'ordered', 'must be a boolean.' );
		}

		$html = trim( $block->inner_html() );
		if ( '' !== $html && 1 !== preg_match( $ordered ? '/^<ol\b[^>]*>\s*<\/ol>$/i' : '/^<ul\b[^>]*>\s*<\/ul>$/i', $html ) ) {
			throw new Invalid_Block_Attribute( 'ordered', 'does not match the serialized list element.' );
		}

		return $block->with_attributes( array( 'ordered' => $ordered ) );
	}

	/**
	 * Normalize one `core/list-item`.
	 *
	 * @param Block_Node $block Source block.
	 * @return Block_Node Normalized block.
	 * @throws Invalid_Block_Attribute When Core serialization is unsupported.
	 */
	private function list_item( Block_Node $block ): Block_Node {
		$attributes = $this->attributes( $block, array( 'content' ) );

		return $block->with_attributes( array( 'content' => $this->rich_text( $attributes, 'content', $block->inner_html(), 'li' ) ) );
	}

	/**
	 * Normalize `core/separator` into a full-width email divider.
	 *
	 * @param Block_Node $block Source block.
	 * @return Block_Node Normalized block.
	 * @throws Invalid_Block_Attribute When Core serialization is unsupported.
	 */
	private function separator( Block_Node $block ): Block_Node {
		$attributes = $this->attributes( $block, array( 'opacity', 'tagName', 'backgroundColor', 'align', 'style', 'className' ) );
		if ( ! in_array( $attributes['align'] ?? 'full', array( 'center', 'wide', 'full' ), true ) ) {
			throw new Invalid_Block_Attribute( 'align', 'must be center, wide, or full.' );
		}
		$this->block_style(
			$attributes,
			array(
				'default' => 'default',
				'wide'    => 'wide',
			),
			'default' 
		);

		$style = $attributes['style'] ?? array();
		if ( ! is_array( $style ) ) {
			throw new Invalid_Block_Attribute( 'style', 'must be a native WordPress style object.' );
		}
		$color = $style['color']['background'] ?? $attributes['backgroundColor'] ?? null;
		unset( $style['color']['background'] );
		if ( array() === ( $style['color'] ?? null ) ) {
			unset( $style['color'] );
		}
		if ( array() !== $style ) {
			throw new Invalid_Block_Attribute( 'style.' . (string) array_key_first( $style ), 'is not supported by the email divider.' );
		}

		return $block->with_attributes( null === $color ? array() : array( 'color' => $color ) );
	}

	/**
	 * Normalize `core/spacer` into a bounded vertical spacer.
	 *
	 * @param Block_Node $block Source block.
	 * @return Block_Node Normalized block.
	 * @throws Invalid_Block_Attribute When Core serialization is unsupported.
	 */
	private function spacer( Block_Node $block ): Block_Node {
		$attributes = $this->attributes( $block, array( 'height' ) );
		$height     = $attributes['height'] ?? self::CORE_SPACER_HEIGHT;

		return $block->with_attributes( array( 'height' => Style_Resolver::length( $height, 'height', 0, 600 ) ) );
	}

	/**
	 * Return known attributes after dropping editor-only values.
	 *
	 * @param Block_Node         $block   Source block.
	 * @param array<int, string> $allowed Attributes with email meaning for this block.
	 * @return array<string, mixed> Known attributes.
	 * @throws Invalid_Block_Attribute When an attribute is unsupported.
	 */
	private function attributes( Block_Node $block, array $allowed ): array {
		$attributes = $block->attributes();
		foreach ( self::EDITOR_ONLY as $key ) {
			unset( $attributes[ $key ] );
		}
		if ( isset( $attributes['metadata'] ) ) {
			if ( ! is_array( $attributes['metadata'] ) || array() !== array_diff( array_keys( $attributes['metadata'] ), array( 'name', 'bindings' ) ) ) {
				throw new Invalid_Block_Attribute( 'metadata', 'supports only a List View name and CampaignBridge post bindings in email.' );
			}
			unset( $attributes['metadata'] );
		}

		foreach ( array_keys( $attributes ) as $key ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				throw new Invalid_Block_Attribute( (string) $key, 'is not supported by CampaignBridge email authoring.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
			}
		}

		// Frontend-only link and media metadata has no email equivalent.
		unset( $attributes['id'], $attributes['sizeSlug'], $attributes['linkTarget'], $attributes['rel'], $attributes['title'], $attributes['type'], $attributes['tagName'], $attributes['opacity'], $attributes['lightbox'], $attributes['values'] );

		return $attributes;
	}

	/**
	 * Move Core's `typography.textAlign` style into the renderer alignment.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @return array<string, mixed> Attributes with a portable alignment.
	 * @throws Invalid_Block_Attribute When the style or alignment is unsupported.
	 */
	private function text_alignment( array $attributes ): array {
		if ( ! isset( $attributes['style'] ) ) {
			return $attributes;
		}
		if ( ! is_array( $attributes['style'] ) ) {
			throw new Invalid_Block_Attribute( 'style', 'must be a native WordPress style object.' );
		}
		if ( ! isset( $attributes['style']['typography']['textAlign'] ) ) {
			return $attributes;
		}

		$align = $attributes['style']['typography']['textAlign'];
		if ( ! in_array( $align, array( 'left', 'center', 'right' ), true ) ) {
			throw new Invalid_Block_Attribute( 'style.typography.textAlign', 'must be left, center, or right in email.' );
		}

		unset( $attributes['style']['typography']['textAlign'] );
		if ( array() === $attributes['style']['typography'] ) {
			unset( $attributes['style']['typography'] );
		}
		$attributes['align'] = $align;

		return $attributes;
	}

	/**
	 * Convert Core's string font weight (`"600"`) into the portable integer.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @return array<string, mixed> Attributes with an integer weight when one was chosen.
	 */
	private function font_weight( array $attributes ): array {
		$weight = $attributes['style']['typography']['fontWeight'] ?? null;
		if ( is_string( $weight ) && 1 === preg_match( '/^[1-9]00$/', $weight ) ) {
			$attributes['style']['typography']['fontWeight'] = (int) $weight;
		}

		return $attributes;
	}

	/**
	 * Read rich text from an attribute or from Core's exact wrapper element.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param string               $name       Attribute name.
	 * @param string               $html       Serialized block markup, excluding inner blocks.
	 * @param string               $tag        Core wrapper tag.
	 * @return string Rich text with Core link attributes canonicalized.
	 * @throws Invalid_Block_Attribute When the content is malformed.
	 */
	private function rich_text( array $attributes, string $name, string $html, string $tag ): string {
		if ( array_key_exists( $name, $attributes ) ) {
			return $this->canonical_links( $this->string_value( $attributes[ $name ], $name ) );
		}

		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}

		$quoted = preg_quote( $tag, '/' );
		if ( 1 !== preg_match( '/^<' . $quoted . '(?:\s[^>]*)?>(.*)<\/' . $quoted . '>$/is', $html, $matches ) ) {
			throw new Invalid_Block_Attribute( $name, 'must use the known Core serialization contract.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
		}

		return $this->canonical_links( $matches[1] );
	}

	/**
	 * Rewrite Core link-format anchors to the portable rich-text link shape.
	 *
	 * Core's link format may add `data-type`, `data-id`, and a `rel` value to
	 * anchors. Those are dropped here; any other attribute leaves the anchor
	 * untouched so the renderer's rich-text allowlist rejects it.
	 *
	 * @param string $html Rich text.
	 */
	private function canonical_links( string $html ): string {
		return (string) preg_replace_callback(
			'/<a\s([^>]*)>/i',
			static function ( array $matches ): string {
				if ( false === preg_match_all( '/([a-z][a-z0-9-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $matches[1], $pairs, PREG_SET_ORDER ) ) {
					return $matches[0];
				}

				$values = array();
				foreach ( $pairs as $pair ) {
					$values[ strtolower( $pair[1] ) ] = ( $pair[2] ?? '' ) . ( $pair[3] ?? '' );
				}
				$residue = trim( (string) preg_replace( '/([a-z][a-z0-9-]*)\s*=\s*(?:"[^"]*"|\'[^\']*\')/i', '', $matches[1] ) );
				if ( '' !== $residue || ! isset( $values['href'] ) || array() !== array_diff( array_keys( $values ), array( 'href', 'target', 'rel', 'data-type', 'data-id' ) ) ) {
					return $matches[0];
				}
				if ( isset( $values['target'] ) && '_blank' !== $values['target'] ) {
					return $matches[0];
				}

				return '<a href="' . $values['href'] . '"' . ( isset( $values['target'] ) ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>';
			},
			$html
		);
	}

	/**
	 * Resolve a Core block style class into renderer semantics.
	 *
	 * @param array<string, mixed>  $attributes Source attributes.
	 * @param array<string, string> $styles     Registered style name => semantic value.
	 * @param string                $fallback   Semantic value without a style class.
	 * @throws Invalid_Block_Attribute When the class list contains anything else.
	 */
	private function block_style( array $attributes, array $styles, string $fallback ): string {
		$class_name = $attributes['className'] ?? '';
		if ( ! is_string( $class_name ) ) {
			throw new Invalid_Block_Attribute( 'className', 'must be a block style class.' );
		}

		$tokens = preg_split( '/\s+/', trim( $class_name ), -1, PREG_SPLIT_NO_EMPTY );
		$tokens = false === $tokens ? array() : $tokens;
		if ( array() === $tokens ) {
			return $fallback;
		}
		if ( 1 !== count( $tokens ) || 1 !== preg_match( '/^is-style-([a-z0-9-]+)$/', $tokens[0], $matches ) || ! isset( $styles[ $matches[1] ] ) ) {
			throw new Invalid_Block_Attribute( 'className', 'must name one supported block style; custom CSS classes are not email semantics.' );
		}

		return $styles[ $matches[1] ];
	}

	/**
	 * Parse Core's saved image figure.
	 *
	 * @param string $html Serialized block markup.
	 * @return array{url?: string, alt?: string, href?: string}
	 * @throws Invalid_Block_Attribute When the markup is not Core's image contract.
	 */
	private function image_markup( string $html ): array {
		$html = trim( $html );
		if ( '' === $html ) {
			return array();
		}

		$pattern = '/^<figure\b[^>]*>\s*(?:<a\b([^>]*)>\s*)?<img\b([^>]*?)\s*\/?>\s*(?:<\/a>\s*)?(?:<figcaption\b[^>]*>(.*?)<\/figcaption>\s*)?<\/figure>$/is';
		if ( 1 !== preg_match( $pattern, $html, $matches ) ) {
			throw new Invalid_Block_Attribute( 'url', 'must use the known Core image serialization contract.' );
		}
		if ( '' !== trim( wp_strip_all_tags( $matches[3] ?? '' ) ) ) {
			throw new Invalid_Block_Attribute( 'caption', 'is not supported by email images.' );
		}

		$result = array();
		foreach ( array(
			'src' => 'url',
			'alt' => 'alt',
		) as $source => $target ) {
			$value = $this->html_attribute( $matches[2], $source );
			if ( null !== $value ) {
				$result[ $target ] = $value;
			}
		}
		$href = $this->html_attribute( $matches[1], 'href' );
		if ( null !== $href ) {
			$result['href'] = $href;
		}

		return $result;
	}

	/**
	 * Parse Core's saved button wrapper.
	 *
	 * @param string $html Serialized block markup.
	 * @return array{text?: string, href?: string}
	 * @throws Invalid_Block_Attribute When the markup is not Core's button contract.
	 */
	private function button_markup( string $html ): array {
		$html = trim( $html );
		if ( '' === $html ) {
			return array();
		}
		if ( 1 !== preg_match( '/^<div\b[^>]*>\s*<a\b([^>]*)>(.*)<\/a>\s*<\/div>$/is', $html, $matches ) ) {
			throw new Invalid_Block_Attribute( 'text', 'must use the known Core button serialization contract.' );
		}

		$result = array( 'text' => $matches[2] );
		$href   = $this->html_attribute( $matches[1], 'href' );
		if ( null !== $href ) {
			$result['href'] = $href;
		}

		return $result;
	}

	/**
	 * Read one double- or single-quoted attribute from a tag's attribute text.
	 *
	 * @param string $attributes Raw attribute text.
	 * @param string $name       Attribute name.
	 */
	private function html_attribute( string $attributes, string $name ): ?string {
		if ( 1 !== preg_match( '/(?:^|\s)' . preg_quote( $name, '/' ) . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $attributes, $matches ) ) {
			return null;
		}

		return trim( html_entity_decode( $matches[1] . ( $matches[2] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Convert a Core pixel dimension into an integer.
	 *
	 * @param mixed  $value Candidate dimension (`320`, `"320"`, `"320px"`, or `"auto"`).
	 * @param string $name  Attribute name.
	 * @return int|null Pixels, or null when the dimension is automatic.
	 * @throws Invalid_Block_Attribute When the dimension is not portable.
	 */
	private function pixels( mixed $value, string $name ): ?int {
		if ( null === $value || 'auto' === $value ) {
			return null;
		}
		if ( is_int( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) && 1 === preg_match( '/^(\d+)(?:px)?$/', $value, $matches ) ) {
			return (int) $matches[1];
		}

		throw new Invalid_Block_Attribute( $name, 'must be a pixel dimension.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
	}

	/**
	 * Require a string value.
	 *
	 * @param mixed  $value Candidate value.
	 * @param string $name  Attribute name.
	 * @throws Invalid_Block_Attribute When the value is not a string.
	 */
	private function string_value( mixed $value, string $name ): string {
		if ( ! is_string( $value ) ) {
			throw new Invalid_Block_Attribute( $name, 'must be a string.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Diagnostic attribute names are internal.
		}

		return trim( $value );
	}
}
