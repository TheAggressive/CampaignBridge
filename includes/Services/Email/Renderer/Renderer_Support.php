<?php
/**
 * Shared pure helpers for email renderers.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Invalid_Block_Attribute;
use CampaignBridge\Domain\Email\Render_Context;
use CampaignBridge\Domain\Email\Style_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Provides bounded normalization and final-boundary escaping. */
final class Renderer_Support {
	/**
	 * Escape a value for HTML text or attribute output.
	 *
	 * @param string $value Untrusted value.
	 */
	public static function html( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Read a string attribute, defaulting only when it is omitted.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param string               $name       Attribute name.
	 * @param string               $fallback   Documented default.
	 * @throws Invalid_Block_Attribute When an explicit value is not a string.
	 */
	public static function string_attribute( array $attributes, string $name, string $fallback ): string {
		if ( ! array_key_exists( $name, $attributes ) ) {
			return $fallback;
		}

		if ( ! is_string( $attributes[ $name ] ) ) {
			throw new Invalid_Block_Attribute( $name, 'must be a string.' );
		}

		return $attributes[ $name ];
	}

	/**
	 * Read a boolean attribute, defaulting only when it is omitted.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param string               $name       Attribute name.
	 * @param bool                 $fallback   Documented default.
	 * @throws Invalid_Block_Attribute When an explicit value is not a boolean.
	 */
	public static function boolean_attribute( array $attributes, string $name, bool $fallback ): bool {
		if ( ! array_key_exists( $name, $attributes ) ) {
			return $fallback;
		}

		if ( ! is_bool( $attributes[ $name ] ) ) {
			throw new Invalid_Block_Attribute( $name, 'must be a boolean.' );
		}

		return $attributes[ $name ];
	}

	/**
	 * Read an object attribute, defaulting only when it is omitted.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param string               $name       Attribute name.
	 * @param array<string, mixed> $fallback   Documented default.
	 * @param string|null          $path       Optional nested diagnostic path.
	 * @return array<string, mixed>
	 * @throws Invalid_Block_Attribute When an explicit value is not a named object.
	 */
	public static function object_attribute( array $attributes, string $name, array $fallback, ?string $path = null ): array {
		if ( ! array_key_exists( $name, $attributes ) ) {
			return $fallback;
		}

		$value = $attributes[ $name ];
		if ( ! is_array( $value ) ) {
			throw new Invalid_Block_Attribute( $path ?? $name, 'must be an object.' );
		}

		foreach ( array_keys( $value ) as $key ) {
			if ( ! is_string( $key ) ) {
				throw new Invalid_Block_Attribute( $path ?? $name, 'must use named object properties.' );
			}
		}

		/**
		 * Validated named attributes.
		 *
		 * @var array<string, mixed> $value
		 */
		return $value;
	}

	/**
	 * Read an allowlisted string attribute.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param string               $name       Attribute name.
	 * @param string               $fallback   Documented default.
	 * @param array<int, string>   $choices    Accepted values.
	 * @param string|null          $path       Optional nested diagnostic path.
	 * @throws Invalid_Block_Attribute When an explicit value is not allowlisted.
	 */
	public static function choice_attribute( array $attributes, string $name, string $fallback, array $choices, ?string $path = null ): string {
		if ( ! array_key_exists( $name, $attributes ) ) {
			return $fallback;
		}

		$value = $attributes[ $name ];
		if ( ! is_string( $value ) || ! in_array( $value, $choices, true ) ) {
			throw new Invalid_Block_Attribute(
				$path ?? $name,
				sprintf( 'must be one of: %s.', implode( ', ', $choices ) )
			);
		}

		return $value;
	}

	/**
	 * Read a six-digit portable color attribute.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param string               $name       Attribute name.
	 * @param string               $fallback   Valid documented default.
	 * @param string|null          $path       Optional nested diagnostic path.
	 * @throws Invalid_Block_Attribute When an explicit value is not a portable color.
	 */
	public static function color_attribute( array $attributes, string $name, string $fallback, ?string $path = null ): string {
		if ( ! array_key_exists( $name, $attributes ) ) {
			return $fallback;
		}

		$value = $attributes[ $name ];
		if ( ! is_string( $value ) || ! preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) {
			throw new Invalid_Block_Attribute( $path ?? $name, 'must be a six-digit hexadecimal color.' );
		}

		return strtolower( $value );
	}

	/**
	 * Return a portable six-digit color or null.
	 *
	 * This is used for non-block document metadata. Persisted block attributes
	 * must use color_attribute() so malformed explicit values are diagnosed.
	 *
	 * @param mixed $value Candidate color.
	 */
	public static function portable_color( mixed $value ): ?string {
		if ( ! is_string( $value ) || ! preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) {
			return null;
		}

		return strtolower( $value );
	}

	/**
	 * Resolve a single raw colour value against the active brand kit.
	 *
	 * @param string    $value Raw colour (hex, preset reference, or slot slug).
	 * @param Brand_Kit $kit   Active brand kit.
	 * @return string Portable six-digit hex colour.
	 * @throws Invalid_Block_Attribute When the colour cannot be resolved.
	 */
	public static function resolve_color( string $value, Brand_Kit $kit ): string {
		$normalized = trim( $value );

		// 1. Portable hex passes straight through.
		if ( 1 === preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $normalized ) ) {
			return Brand_Kit::normalize_hex( $normalized ) ?? $normalized;
		}

		// 2. Design-system preset reference (var:preset|color|<slug>).
		if ( str_starts_with( $normalized, 'var:preset|color|' ) ) {
			$slug = substr( $normalized, strlen( 'var:preset|color|' ) );
			$hex  = $kit->color( $slug );

			if ( null !== $hex ) {
				return $hex;
			}
		}

		// 3. Bare brand kit slot slug.
		if ( '' !== $normalized && null !== $kit->color( $normalized ) ) {
			return $kit->color( $normalized );
		}

		throw new Invalid_Block_Attribute( 'color', 'must be a hexadecimal colour or a known colour preset.' );
	}

	/**
	 * Read the active brand kit from the context, defaulting to the kit
	 * defaults when none is supplied.
	 *
	 * @param Render_Context $context Immutable scoped context.
	 * @return Brand_Kit Resolved brand kit.
	 */
	public static function brand_kit( Render_Context $context ): Brand_Kit {
		$kit = $context->metadata( 'brandKit' );

		return $kit instanceof Brand_Kit ? $kit : Brand_Kit::defaults();
	}

	/**
	 * Resolve the font a block should render with.
	 *
	 * Delegates to Style_Resolver so the compiler has a single resolution
	 * path: native preset reference, a bare known slug, then the active brand
	 * kit slot, then the safe default. Unknown slugs and custom stacks degrade
	 * to a safe face rather than failing the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param Brand_Kit            $kit        Active brand kit, for the slot default.
	 * @param string               $slot       Semantic typography slot.
	 * @return array<string, mixed>
	 */
	public static function resolve_font( array $attributes, Brand_Kit $kit, string $slot = 'body' ): array {
		return Style_Resolver::resolve_font( $attributes, $kit, $slot );
	}

	/**
	 * Read an integer attribute within inclusive bounds.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param string               $name       Attribute name.
	 * @param int                  $fallback   Documented default.
	 * @param int                  $minimum    Inclusive minimum.
	 * @param int                  $maximum    Inclusive maximum.
	 * @param string|null          $path       Optional nested diagnostic path.
	 * @throws Invalid_Block_Attribute When an explicit value is not a bounded integer.
	 */
	public static function integer_attribute( array $attributes, string $name, int $fallback, int $minimum, int $maximum, ?string $path = null ): int {
		if ( ! array_key_exists( $name, $attributes ) ) {
			return $fallback;
		}

		$value = $attributes[ $name ];
		if ( ! is_int( $value ) || $minimum > $value || $maximum < $value ) {
			throw new Invalid_Block_Attribute(
				$path ?? $name,
				sprintf( 'must be an integer from %d through %d.', $minimum, $maximum )
			);
		}

		return $value;
	}

	/**
	 * Collapse rich text into plain words and cap it at a word budget.
	 *
	 * Mirrors the editor preview so a block renders the same summary in the
	 * email as it shows in the editor, and appends an ellipsis when clipped.
	 *
	 * @param string $raw       Rich text or plain text source.
	 * @param int    $max_words Maximum number of words to keep.
	 */
	public static function truncate_words( string $raw, int $max_words ): string {
		$text  = trim( html_entity_decode( wp_strip_all_tags( $raw ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$text  = rtrim( $text, '…' );
		$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$words = is_array( $words ) ? $words : array();

		if ( count( $words ) <= $max_words ) {
			return implode( ' ', $words );
		}

		return implode( ' ', array_slice( $words, 0, $max_words ) ) . '…';
	}

	/**
	 * Read portable horizontal alignment.
	 *
	 * @param array<string, mixed> $attributes Source attributes.
	 * @param string               $name       Attribute name.
	 * @param string               $fallback   Documented default.
	 * @throws Invalid_Block_Attribute When an explicit value is not a portable alignment.
	 */
	public static function alignment_attribute( array $attributes, string $name, string $fallback = 'left' ): string {
		return self::choice_attribute( $attributes, $name, $fallback, array( 'left', 'center', 'right' ) );
	}

	/**
	 * Read complete four-sided pixel spacing.
	 *
	 * @param array<string, mixed>                                $attributes Source attributes.
	 * @param string                                              $name       Attribute name.
	 * @param array{top: int, right: int, bottom: int, left: int} $fallback Documented default.
	 * @return array{top: int, right: int, bottom: int, left: int}
	 * @throws Invalid_Block_Attribute When explicit spacing is incomplete or out of range.
	 */
	public static function spacing_attribute( array $attributes, string $name, array $fallback ): array {
		if ( ! array_key_exists( $name, $attributes ) ) {
			return $fallback;
		}

		$value = self::object_attribute( $attributes, $name, array() );
		$keys  = array_keys( $value );
		sort( $keys, SORT_STRING );
		if ( array( 'bottom', 'left', 'right', 'top' ) !== $keys ) {
			throw new Invalid_Block_Attribute( $name, 'must define exactly top, right, bottom, and left.' );
		}

		return array(
			'top'    => self::integer_attribute( $value, 'top', $fallback['top'], 0, 96, $name . '.top' ),
			'right'  => self::integer_attribute( $value, 'right', $fallback['right'], 0, 96, $name . '.right' ),
			'bottom' => self::integer_attribute( $value, 'bottom', $fallback['bottom'], 0, 96, $name . '.bottom' ),
			'left'   => self::integer_attribute( $value, 'left', $fallback['left'], 0, 96, $name . '.left' ),
		);
	}

	/**
	 * Resolve the post destination URL from the immutable context binding.
	 *
	 * Shared by the post button and post link renderers: both accept the same
	 * destination enum and read the same post snapshot fields.
	 *
	 * @param array<string, mixed> $attributes Normalized block attributes.
	 * @param Render_Context       $context    Immutable scoped context.
	 */
	public static function post_destination_url( array $attributes, Render_Context $context ): ?string {
		$destination = $attributes['destination'];
		if ( 'custom' === $destination ) {
			return self::https_url( $attributes['customUrl'] );
		}

		$post = $context->binding( 'post' );
		if ( ! is_array( $post ) ) {
			return null;
		}

		$field = match ( $destination ) {
			'postParent'      => 'postParentUrl',
			'postTypeArchive' => 'postTypeArchiveUrl',
			default           => 'url',
		};

		return self::https_url( $post[ $field ] ?? null );
	}

	/**
	 * Build the destination-specific missing-URL diagnostics.
	 *
	 * @param string $code_prefix Diagnostic prefix, e.g. 'post.button' or 'post.link'.
	 * @param string $destination Destination slug from the normalized block.
	 * @return array{0: string, 1: string} Code and message pair.
	 */
	public static function missing_destination_diagnostics( string $code_prefix, string $destination ): array {
		$diagnostics = array(
			'article'         => array( $code_prefix . '.url_missing', 'A snapshot HTTP or HTTPS article URL is required.' ),
			'postParent'      => array( $code_prefix . '.post_parent_url_missing', 'The post parent HTTP or HTTPS URL is required in the snapshot.' ),
			'postTypeArchive' => array( $code_prefix . '.post_type_archive_url_missing', 'The post type archive HTTP or HTTPS URL is required in the snapshot.' ),
			'custom'          => array( $code_prefix . '.custom_url_invalid', 'The custom destination must be an absolute HTTP or HTTPS URL.' ),
		);

		return $diagnostics[ $destination ] ?? $diagnostics['article'];
	}

	/**
	 * Return an absolute HTTP or HTTPS URL or null.
	 *
	 * Accepts either scheme so previews and sends work on HTTP development
	 * sites as well as HTTPS production sites, while still rejecting relative
	 * and non-HTTP(S) schemes (javascript:, data:, mailto:, …).
	 *
	 * @param mixed $value Candidate URL.
	 */
	public static function https_url( mixed $value ): ?string {
		if ( ! is_string( $value ) || false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return null;
		}

		$scheme = parse_url( $value, PHP_URL_SCHEME ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Renderer normalization is intentionally WordPress-independent.

		return in_array( strtolower( is_string( $scheme ) ? $scheme : '' ), array( 'http', 'https' ), true ) ? $value : null;
	}

	/**
	 * Normalize the supported rich-text subset or return null when unsafe.
	 *
	 * @param mixed $value Candidate rich text.
	 */
	public static function rich_text( mixed $value ): ?string {
		if ( ! is_string( $value ) || 20000 < strlen( $value ) ) {
			return null;
		}

		$tokens = preg_split( '/(<[^>]+>)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		if ( false === $tokens ) {
			return null;
		}

		$output = '';
		$stack  = array();
		foreach ( $tokens as $token ) {
			if ( ! str_starts_with( $token, '<' ) ) {
				$output .= self::html( html_entity_decode( $token, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				continue;
			}

			if ( preg_match( '/^<br\s*\/?\s*>$/i', $token ) ) {
				$output .= '<br>';
				continue;
			}

			if ( preg_match( '/^<(strong|em|u|s)>$/i', $token, $matches ) ) {
				$name    = strtolower( $matches[1] );
				$stack[] = $name;
				$output .= '<' . $name . '>';
				continue;
			}

			if ( preg_match( '/^<\/(strong|em|u|s|a)>$/i', $token, $matches ) ) {
				$name = strtolower( $matches[1] );
				if ( end( $stack ) !== $name ) {
					return null;
				}

				array_pop( $stack );
				$output .= '</' . $name . '>';
				continue;
			}

			if (
				preg_match(
					'/^<a\s+href=(["\'])([^"\']+)\1(?:\s+target=(["\'])_blank\3)?(?:\s+rel=(["\'])[^"\']*\4)?\s*>$/i',
					$token,
					$matches
				)
				&& ! in_array( 'a', $stack, true )
			) {
				$url = html_entity_decode( $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				if ( null === self::https_url( $url ) ) {
					return null;
				}

				$target  = isset( $matches[3] ) && '' !== $matches[3];
				$stack[] = 'a';
				$output .= '<a href="' . self::html( $url ) . '" style="color:inherit;text-decoration:underline"'
					. ( $target ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>';
				continue;
			}

			return null;
		}

		return array() === $stack ? $output : null;
	}

	/**
	 * Convert validated rich text into portable plain text.
	 *
	 * @param mixed $value Candidate rich text.
	 */
	public static function rich_text_to_plain( mixed $value ): ?string {
		$html = self::rich_text( $value );
		if ( null === $html ) {
			return null;
		}

		$html = preg_replace_callback(
			'/<a href="([^"]+)"[^>]*>(.*?)<\/a>/su',
			static function ( array $matches ): string {
				$label = html_entity_decode( wp_strip_all_tags( $matches[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$url   = html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				return $label . ' (' . $url . ')';
			},
			$html
		);
		if ( null === $html ) {
			return null;
		}

		$html = preg_replace( '/<br>/i', "\n", $html );
		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/[ \t]+/u', ' ', $text );
		$text = preg_replace( '/\s*\n\s*/u', "\n", (string) $text );

		return trim( (string) $text );
	}
}
