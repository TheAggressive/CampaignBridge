<?php // phpcs:disable Squiz.Commenting.FunctionComment,Generic.Commenting.DocComment.MissingShort -- Shared fail-closed validators use explicit signatures.
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag -- Validation exceptions are part of this value contract.
/**
 * Shared validation for discovered provider references.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fail-closed checks shared by every discovered reference value.
 *
 * Remote values are untrusted. Identifiers must be opaque and bounded, and
 * display text is normalized to a single safe line before it can reach UI.
 */
final class Discovery_Values {
	/** Maximum characters for any display label. */
	public const MAX_LABEL_LENGTH = 255;

	/** Opaque remote identifier, e.g. a Mailchimp list or segment ID. */
	public static function remote_id( mixed $value, string $label ): string {
		if ( is_int( $value ) && 0 <= $value ) {
			$value = (string) $value;
		}
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $value ) ) {
			throw new \InvalidArgumentException( $label . ' must be an opaque identifier of at most 64 characters.' );
		}

		return $value;
	}

	/** Single-line display text without control characters. */
	public static function label( mixed $value, string $label ): string {
		if ( ! is_string( $value ) ) {
			throw new \InvalidArgumentException( $label . ' must be a string.' );
		}
		$clean = preg_replace( '/[\p{Cc}\p{Cf}]+/u', ' ', $value );
		$clean = is_string( $clean ) ? trim( (string) preg_replace( '/\s+/u', ' ', $clean ) ) : '';
		if ( '' === $clean || self::MAX_LABEL_LENGTH < mb_strlen( $clean ) ) {
			throw new \InvalidArgumentException( sprintf( '%s must be non-empty display text of at most %d characters.', $label, self::MAX_LABEL_LENGTH ) );
		}

		return $clean;
	}

	/** Non-negative aggregate count, or null when the provider omits it. */
	public static function count( mixed $value, string $label ): ?int {
		if ( null === $value ) {
			return null;
		}
		if ( ! is_int( $value ) || 0 > $value ) {
			throw new \InvalidArgumentException( $label . ' must be a non-negative integer.' );
		}

		return $value;
	}

	/** Scope of a discovery: '' for account-wide or one audience ID. */
	public static function scope( mixed $value ): string {
		return '' === $value ? '' : self::remote_id( $value, 'Discovery scope' );
	}
}
