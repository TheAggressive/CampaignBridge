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
}
