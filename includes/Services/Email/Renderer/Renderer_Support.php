<?php
/**
 * Shared pure helpers for email renderers.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email\Renderer;

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
	 * Normalize a six-digit portable color.
	 *
	 * @param mixed  $value    Candidate color.
	 * @param string $fallback Valid fallback color.
	 */
	public static function color( mixed $value, string $fallback ): string {
		if ( is_string( $value ) && preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) {
			return strtolower( $value );
		}

		return $fallback;
	}

	/**
	 * Normalize an integer into inclusive bounds.
	 *
	 * @param mixed $value    Candidate number.
	 * @param int   $fallback Default number.
	 * @param int   $minimum  Inclusive minimum.
	 * @param int   $maximum  Inclusive maximum.
	 */
	public static function integer( mixed $value, int $fallback, int $minimum, int $maximum ): int {
		if ( ! is_int( $value ) && ! is_numeric( $value ) ) {
			return $fallback;
		}

		return max( $minimum, min( $maximum, (int) $value ) );
	}

	/**
	 * Normalize portable horizontal alignment.
	 *
	 * @param mixed  $value    Candidate alignment.
	 * @param string $fallback Default alignment.
	 */
	public static function alignment( mixed $value, string $fallback = 'left' ): string {
		return is_string( $value ) && in_array( $value, array( 'left', 'center', 'right' ), true )
			? $value
			: $fallback;
	}

	/**
	 * Normalize four-sided pixel spacing.
	 *
	 * @param mixed                                               $value    Candidate spacing.
	 * @param array{top: int, right: int, bottom: int, left: int} $fallback Default spacing.
	 * @return array{top: int, right: int, bottom: int, left: int}
	 */
	public static function spacing( mixed $value, array $fallback ): array {
		$value = is_array( $value ) ? $value : array();

		return array(
			'top'    => self::integer( $value['top'] ?? null, $fallback['top'], 0, 96 ),
			'right'  => self::integer( $value['right'] ?? null, $fallback['right'], 0, 96 ),
			'bottom' => self::integer( $value['bottom'] ?? null, $fallback['bottom'], 0, 96 ),
			'left'   => self::integer( $value['left'] ?? null, $fallback['left'], 0, 96 ),
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
				$label = html_entity_decode( strip_tags( $matches[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure renderer removes the validated inline markup.
				$url   = html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				return $label . ' (' . $url . ')';
			},
			$html
		);
		if ( null === $html ) {
			return null;
		}

		$html = preg_replace( '/<br>/i', "\n", $html );
		$text = html_entity_decode( strip_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure renderer removes the validated inline markup.
		$text = preg_replace( '/[ \t]+/u', ' ', $text );
		$text = preg_replace( '/\s*\n\s*/u', "\n", (string) $text );

		return trim( (string) $text );
	}
}
