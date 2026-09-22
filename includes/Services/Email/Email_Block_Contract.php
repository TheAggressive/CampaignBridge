<?php
/**
 * Authoritative email authoring block contract.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email;

use CampaignBridge\Domain\Email\Post_Snapshot;
use JsonException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the one supported-block grammar shared by PHP and the editor.
 *
 * `includes/Email_Blocks/email-blocks.json` names every authoring block, its
 * origin (a WordPress Core block or a CampaignBridge block), the
 * CampaignBridge email semantics it compiles through, and its permitted
 * children. The editor allowlist, renderer nesting, and Core normalization
 * derive from this contract; the compiler registry is parity-tested against it.
 */
final class Email_Block_Contract {
	public const SOURCE_CORE           = 'core';
	public const SOURCE_CAMPAIGNBRIDGE = 'campaignbridge';

	private const MAX_BYTES = 65536;

	/**
	 * Decoded block entries keyed by block name.
	 *
	 * @var array<string, array{source: string, semantics: string, children: array<int, string>}>|null
	 */
	private static ?array $blocks = null;

	/**
	 * Decoded read-only post binding contract.
	 *
	 * @var array{source: string, attributes: array<string, array<string, array{projection: string, fields: array<string, array{reads: string, link: string|null}>, args: array<string, array{type: string, min: int, max: int, default: int}>}>>}|null
	 */
	private static ?array $post_bindings = null;

	/**
	 * Decoded read-only Brand Kit binding contract.
	 *
	 * @var array{source: string, attributes: array<string, array<string, array{field: string, target: string}>>}|null
	 */
	private static ?array $brand_bindings = null;

	/**
	 * Decoded contract document.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $document = null;

	/**
	 * Every supported authoring block name in contract order.
	 *
	 * @return array<int, string>
	 */
	public static function names(): array {
		return array_keys( self::blocks() );
	}

	/**
	 * Supported WordPress Core authoring block names.
	 *
	 * @return array<int, string>
	 */
	public static function core_names(): array {
		return array_keys( array_filter( self::blocks(), static fn ( array $block ): bool => self::SOURCE_CORE === $block['source'] ) );
	}

	/**
	 * Whether a block belongs to the supported authoring grammar.
	 *
	 * @param string $name Block name.
	 */
	public static function has( string $name ): bool {
		return isset( self::blocks()[ $name ] );
	}

	/**
	 * Whether a supported block is a WordPress Core authoring block.
	 *
	 * @param string $name Block name.
	 */
	public static function is_core( string $name ): bool {
		return self::SOURCE_CORE === ( self::blocks()[ $name ]['source'] ?? null );
	}

	/**
	 * The CampaignBridge email semantics a block compiles through.
	 *
	 * @param string $name Block name.
	 */
	public static function semantics( string $name ): ?string {
		return self::blocks()[ $name ]['semantics'] ?? null;
	}

	/**
	 * Permitted child block names for one block.
	 *
	 * @param string $name Parent block name.
	 * @return array<int, string>
	 */
	public static function children( string $name ): array {
		return self::blocks()[ $name ]['children'] ?? array();
	}

	/**
	 * Core Social Icon service slugs accepted by the email compiler.
	 *
	 * @return array<string, string> Accessible service names keyed by Core slug.
	 * @throws \DomainException When the packaged service catalogue is malformed.
	 */
	public static function social_services(): array {
		$services = self::document()['socialServices'] ?? null;
		if ( ! is_array( $services ) || array() === $services || array_is_list( $services ) ) {
			throw new \DomainException( 'Email block contract declares no social services.' );
		}

		foreach ( $services as $slug => $label ) {
			if ( ! is_string( $slug ) || 1 !== preg_match( '/^[a-z0-9-]+$/', $slug ) || ! is_string( $label ) || '' === trim( $label ) ) {
				throw new \DomainException( 'Email block contract contains a malformed social service.' );
			}
		}

		return $services;
	}

	/** The one supported read-only post binding source name. */
	public static function binding_source(): string {
		return self::post_bindings()['source'];
	}

	/**
	 * Whether a block accepts a post binding on one attribute.
	 *
	 * @param string $name      Block name.
	 * @param string $attribute Bindable attribute name.
	 */
	public static function has_binding( string $name, string $attribute ): bool {
		return isset( self::post_bindings()['attributes'][ $name ][ $attribute ] );
	}

	/**
	 * The documented binding rule for one block attribute.
	 *
	 * @param string $name      Block name.
	 * @param string $attribute Bindable attribute name.
	 * @return array{projection: string, fields: array<string, array{reads: string, link: string|null}>, args: array<string, array{type: string, min: int, max: int, default: int}>}|null
	 */
	public static function binding( string $name, string $attribute ): ?array {
		return self::post_bindings()['attributes'][ $name ][ $attribute ] ?? null;
	}

	/**
	 * The documented rule for one bound field.
	 *
	 * `reads` names the immutable snapshot field supplying the value. `link`,
	 * when present, names the snapshot field the renderer links that value to.
	 *
	 * @param string $name      Block name.
	 * @param string $attribute Bindable attribute name.
	 * @param string $field     Binding field name.
	 * @return array{reads: string, link: string|null}|null
	 */
	public static function binding_field( string $name, string $attribute, string $field ): ?array {
		return self::post_bindings()['attributes'][ $name ][ $attribute ]['fields'][ $field ] ?? null;
	}

	/**
	 * Every block name that accepts a post binding, in contract order.
	 *
	 * @return array<int, string>
	 */
	public static function binding_block_names(): array {
		return array_keys( self::post_bindings()['attributes'] );
	}

	/**
	 * The bindable attribute names of one block, in contract order.
	 *
	 * @param string $name Block name.
	 * @return array<int, string>
	 */
	public static function binding_attribute_names( string $name ): array {
		return array_keys( self::post_bindings()['attributes'][ $name ] ?? array() );
	}

	/** The supported read-only Brand Kit binding source name. */
	public static function brand_binding_source(): string {
		return self::brand_bindings()['source'];
	}

	/**
	 * The Brand Kit binding rule for one authored Core attribute.
	 *
	 * @param string $name      Core block name.
	 * @param string $attribute Authored attribute name.
	 * @return array{field: string, target: string}|null
	 */
	public static function brand_binding( string $name, string $attribute ): ?array {
		return self::brand_bindings()['attributes'][ $name ][ $attribute ] ?? null;
	}

	/**
	 * Core blocks with Brand Kit bindings.
	 *
	 * @return array<int, string>
	 */
	public static function brand_binding_block_names(): array {
		return array_keys( self::brand_bindings()['attributes'] );
	}

	/**
	 * Authored Core attributes with Brand Kit bindings.
	 *
	 * @param string $name Core block name.
	 * @return array<int, string>
	 */
	public static function brand_binding_attribute_names( string $name ): array {
		return array_keys( self::brand_bindings()['attributes'][ $name ] ?? array() );
	}

	/**
	 * Decode and validate the Brand Kit binding contract once per request.
	 *
	 * @return array{source: string, attributes: array<string, array<string, array{field: string, target: string}>>}
	 * @throws \DomainException When the packaged Brand Kit binding contract is malformed.
	 */
	private static function brand_bindings(): array {
		if ( null !== self::$brand_bindings ) {
			return self::$brand_bindings;
		}

		$section = self::document()['brandBindings'] ?? null;
		$source  = is_array( $section ) ? ( $section['source'] ?? null ) : null;
		$entries = is_array( $section ) ? ( $section['attributes'] ?? null ) : null;
		if ( ! is_string( $source ) || '' === $source || ! is_array( $entries ) || array() === $entries ) {
			throw new \DomainException( 'Email block contract declares no Brand Kit binding source.' );
		}

		$attributes = array();
		foreach ( $entries as $block => $rules ) {
			if ( ! is_string( $block ) || ! self::is_core( $block ) || ! is_array( $rules ) || array() === $rules ) {
				throw new \DomainException( 'Email block contract binds an unsupported Brand Kit block.' );
			}
			foreach ( $rules as $attribute => $rule ) {
				$field  = is_array( $rule ) ? ( $rule['field'] ?? null ) : null;
				$target = is_array( $rule ) ? ( $rule['target'] ?? null ) : null;
				if ( ! is_string( $attribute ) || ! is_string( $field ) || '' === $field || ! is_string( $target ) || '' === $target || array( 'field', 'target' ) !== array_keys( $rule ) ) {
					throw new \DomainException( 'Email block contract contains a malformed Brand Kit binding.' );
				}
				$attributes[ $block ][ $attribute ] = array(
					'field'  => $field,
					'target' => $target,
				);
			}
		}

		self::$brand_bindings = array(
			'source'     => $source,
			'attributes' => $attributes,
		);

		return self::$brand_bindings;
	}

	/**
	 * Decode and validate the read-only post binding contract once per request.
	 *
	 * @return array{source: string, attributes: array<string, array<string, array{projection: string, fields: array<string, array{reads: string, link: string|null}>, args: array<string, array{type: string, min: int, max: int, default: int}>}>>}
	 * @throws \DomainException When the packaged binding contract is malformed.
	 */
	private static function post_bindings(): array {
		if ( null !== self::$post_bindings ) {
			return self::$post_bindings;
		}

		$document = self::document();
		$section  = is_array( $document['postBindings'] ?? null ) ? $document['postBindings'] : array();
		$source   = $section['source'] ?? null;
		$entries  = is_array( $section['attributes'] ?? null ) ? $section['attributes'] : null;
		if ( ! is_string( $source ) || '' === $source || null === $entries ) {
			throw new \DomainException( 'Email block contract declares no post binding source.' );
		}

		$attributes = array();
		foreach ( $entries as $block => $rules ) {
			if ( ! is_string( $block ) || ! self::has( $block ) || ! self::is_core( $block ) || ! is_array( $rules ) || array() === $rules ) {
				throw new \DomainException( 'Email block contract binds an unsupported block.' );
			}

			$attributes[ $block ] = array();
			foreach ( $rules as $attribute => $rule ) {
				$attributes[ $block ][ (string) $attribute ] = self::binding_rule( $rule );
			}
		}

		self::$post_bindings = array(
			'source'     => $source,
			'attributes' => $attributes,
		);

		return self::$post_bindings;
	}

	/**
	 * Validate one block attribute's binding rule.
	 *
	 * @param mixed $rule Decoded rule.
	 * @return array{projection: string, fields: array<string, array{reads: string, link: string|null}>, args: array<string, array{type: string, min: int, max: int, default: int}>}
	 * @throws \DomainException When the rule is malformed.
	 */
	private static function binding_rule( mixed $rule ): array {
		$projection = is_array( $rule ) ? ( $rule['projection'] ?? null ) : null;
		$fields     = is_array( $rule ) ? ( $rule['fields'] ?? null ) : null;
		$args       = is_array( $rule ) ? ( $rule['args'] ?? null ) : null;
		if (
			! in_array( $projection, array( 'rich-text', 'url' ), true )
			|| ! is_array( $fields )
			|| array() === $fields
			|| array_is_list( $fields )
			|| ! is_array( $args )
		) {
			throw new \DomainException( 'Email block contract contains a malformed post binding rule.' );
		}

		$resolved = array();
		foreach ( $fields as $name => $field ) {
			$reads = is_array( $field ) ? ( $field['reads'] ?? null ) : null;
			$link  = is_array( $field ) ? ( $field['link'] ?? null ) : null;
			if (
				! is_string( $name )
				|| '' === $name
				|| ! is_string( $reads )
				|| ! in_array( $reads, Post_Snapshot::supported_fields(), true )
				|| ( null !== $link && ! in_array( $link, Post_Snapshot::supported_fields(), true ) )
				|| array() !== array_diff( array_keys( $field ), array( 'reads', 'link' ) )
			) {
				throw new \DomainException( 'Email block contract contains a malformed post binding field.' );
			}

			$resolved[ $name ] = array(
				'reads' => $reads,
				'link'  => is_string( $link ) ? $link : null,
			);
		}

		$bounded = array();
		foreach ( $args as $name => $schema ) {
			$min     = is_array( $schema ) ? ( $schema['min'] ?? null ) : null;
			$max     = is_array( $schema ) ? ( $schema['max'] ?? null ) : null;
			$default = is_array( $schema ) ? ( $schema['default'] ?? null ) : null;
			if ( ! is_string( $name ) || 'integer' !== ( $schema['type'] ?? null ) || ! is_int( $min ) || ! is_int( $max ) || ! is_int( $default ) || $min > $max || $default < $min || $default > $max ) {
				throw new \DomainException( 'Email block contract contains a malformed post binding argument.' );
			}

			$bounded[ $name ] = array(
				'type'    => 'integer',
				'min'     => $min,
				'max'     => $max,
				'default' => $default,
			);
		}

		return array(
			'projection' => $projection,
			'fields'     => $resolved,
			'args'       => $bounded,
		);
	}

	/**
	 * Decode and validate the contract once per request.
	 *
	 * @return array<string, array{source: string, semantics: string, children: array<int, string>}>
	 * @throws \DomainException When the packaged contract is missing or malformed.
	 */
	private static function blocks(): array {
		if ( null !== self::$blocks ) {
			return self::$blocks;
		}

		$entries = is_array( self::document()['blocks'] ?? null ) ? self::document()['blocks'] : array();
		$blocks  = array();
		foreach ( $entries as $name => $entry ) {
			$source    = is_array( $entry ) ? ( $entry['source'] ?? null ) : null;
			$semantics = is_array( $entry ) ? ( $entry['semantics'] ?? null ) : null;
			$children  = is_array( $entry ) ? ( $entry['children'] ?? null ) : null;
			if (
				! is_string( $name )
				|| ! in_array( $source, array( self::SOURCE_CORE, self::SOURCE_CAMPAIGNBRIDGE ), true )
				|| ! str_starts_with( $name, $source . '/' )
				|| ! is_string( $semantics )
				|| ! is_array( $children )
				|| ! array_is_list( $children )
				|| array() !== array_filter( $children, static fn ( mixed $child ): bool => ! is_string( $child ) )
			) {
				throw new \DomainException( 'Email block contract contains a malformed block entry.' );
			}

			$blocks[ $name ] = array(
				'source'    => $source,
				'semantics' => $semantics,
				'children'  => $children,
			);
		}

		foreach ( $blocks as $block ) {
			foreach ( $block['children'] as $child ) {
				if ( ! isset( $blocks[ $child ] ) ) {
					throw new \DomainException( 'Email block contract names an unsupported child block.' );
				}
			}
		}

		if ( array() === $blocks ) {
			throw new \DomainException( 'Email block contract declares no blocks.' );
		}

		self::$blocks = $blocks;

		return $blocks;
	}

	/**
	 * Read and decode the packaged contract document once per request.
	 *
	 * @return array<string, mixed>
	 * @throws \DomainException When the packaged contract is missing or malformed.
	 */
	private static function document(): array {
		if ( null !== self::$document ) {
			return self::$document;
		}

		$path = dirname( __DIR__, 2 ) . '/Email_Blocks/email-blocks.json';
		$size = is_file( $path ) ? filesize( $path ) : false;
		if ( false === $size || 0 === $size || self::MAX_BYTES < $size ) {
			throw new \DomainException( 'Email block contract is missing or exceeds its size limit.' );
		}

		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown, CampaignBridge.Standard.Sniffs.Http.DirectHttpRequest.DirectHttpFunction -- Fixed repository-owned local file.
		try {
			$document = json_decode( (string) $content, true, 16, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new \DomainException( 'Email block contract contains invalid JSON.' );
		}

		self::$document = is_array( $document ) ? $document : array();

		return self::$document;
	}
}
