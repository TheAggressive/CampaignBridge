<?php
/**
 * Shared pure helpers for email renderers.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

use CampaignBridge\Domain\Email\Invalid_Block_Attribute;

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
	 * Return an absolute HTTPS URL or null.
	 *
	 * @param mixed $value Candidate URL.
	 */
	public static function https_url( mixed $value ): ?string {
		if ( ! is_string( $value ) || false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return null;
		}

		$scheme = parse_url( $value, PHP_URL_SCHEME ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Renderer normalization is intentionally WordPress-independent.

		return 'https' === strtolower( is_string( $scheme ) ? $scheme : '' ) ? $value : null;
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
