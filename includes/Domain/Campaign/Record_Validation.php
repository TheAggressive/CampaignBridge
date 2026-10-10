<?php
/**
 * Shared validation for persisted campaign records.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps persisted identifiers and timestamps bounded and portable. */
final class Record_Validation {
	/**
	 * Validate and return one opaque local identifier.
	 *
	 * @param mixed  $value Value to validate.
	 * @param string $label Field name for error messages.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function identifier( mixed $value, string $label ): string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $value ) ) {
			throw new \InvalidArgumentException( $label . ' must be a lowercase opaque identifier of at most 64 characters.' );
		}

		return $value;
	}

	/**
	 * Validate and return one bounded string.
	 *
	 * @param mixed  $value       Value to validate.
	 * @param string $label       Field name for error messages.
	 * @param int    $maximum     Maximum length in bytes.
	 * @param bool   $allow_empty Whether an empty string is valid.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function string( mixed $value, string $label, int $maximum, bool $allow_empty = false ): string {
		if ( ! is_string( $value ) || ( ! $allow_empty && '' === $value ) || strlen( $value ) > $maximum ) {
			throw new \InvalidArgumentException( sprintf( '%s must be a string of at most %d bytes.', $label, $maximum ) );
		}

		return $value;
	}

	/**
	 * Validate and return one canonical UTC timestamp.
	 *
	 * @param mixed  $value Value to validate.
	 * @param string $label Field name for error messages.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function timestamp( mixed $value, string $label ): string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value ) ) {
			throw new \InvalidArgumentException( $label . ' must be a UTC ISO-8601 timestamp.' );
		}

		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone( 'UTC' ) );
		if ( false === $date || $date->format( 'Y-m-d\TH:i:s\Z' ) !== $value ) {
			throw new \InvalidArgumentException( $label . ' must be a valid UTC ISO-8601 timestamp.' );
		}

		return $value;
	}

	/**
	 * Validate and return one compiler fingerprint.
	 *
	 * @param mixed  $value Value to validate.
	 * @param string $label Field name for error messages.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function fingerprint( mixed $value, string $label ): string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^sha256:[0-9a-f]{64}$/', $value ) ) {
			throw new \InvalidArgumentException( $label . ' must be a SHA-256 fingerprint.' );
		}

		return $value;
	}

	/**
	 * Reject unknown serialized keys.
	 *
	 * @param array<string, mixed> $data    Serialized record.
	 * @param array<int, string>   $allowed Allowed keys.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function known_keys( array $data, array $allowed ): void {
		foreach ( array_keys( $data ) as $key ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				throw new \InvalidArgumentException( sprintf( 'Unknown persisted field "%s".', (string) $key ) );
			}
		}
	}
}
