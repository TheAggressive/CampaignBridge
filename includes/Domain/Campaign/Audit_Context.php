<?php
/**
 * Bounded and redacted audit context.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Normalizes safe structured context before it reaches persistence. */
final class Audit_Context {
	public const MAX_ENCODED_BYTES = 4096;
	private const MAX_DEPTH        = 3;
	private const MAX_ENTRIES      = 32;
	private const MAX_STRING_BYTES = 512;
	private const REDACTED         = '[redacted]';

	/**
	 * Values normalized so far, counted against MAX_ENTRIES.
	 *
	 * @var int
	 */
	private int $entries = 0;

	/**
	 * Build the audit context.
	 *
	 * @param array<string, mixed> $values Safe normalized values.
	 */
	private function __construct( private readonly array $values ) {}

	/**
	 * Create bounded context, replacing sensitive values with a marker.
	 *
	 * @param array<string, mixed> $values Candidate context.
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	public static function from_array( array $values ): self {
		$normalizer = new self( array() );
		$normalized = $normalizer->normalize_map( $values, 0 );
		$json       = json_encode( $normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure domain validation requires throwing, deterministic JSON.
		if ( strlen( $json ) > self::MAX_ENCODED_BYTES ) {
			throw new \InvalidArgumentException( 'Audit context exceeds the 4096-byte limit.' );
		}

		return new self( $normalized );
	}

	/**
	 * The context's values.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->values;
	}

	/**
	 * Normalize one level of context, redacting sensitive values and enforcing the bounds.
	 *
	 * @param array<string, mixed> $values Candidate map.
	 * @param int                  $depth  Current nesting depth.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	private function normalize_map( array $values, int $depth ): array {
		if ( $depth > self::MAX_DEPTH || array_is_list( $values ) ) {
			throw new \InvalidArgumentException( 'Audit context must use bounded named maps.' );
		}

		$normalized = array();
		foreach ( $values as $key => $value ) {
			if ( ! is_string( $key ) || 1 !== preg_match( '/^[a-zA-Z][a-zA-Z0-9_.-]{0,63}$/', $key ) ) {
				throw new \InvalidArgumentException( 'Audit context keys must be bounded names.' );
			}
			++$this->entries;
			if ( $this->entries > self::MAX_ENTRIES ) {
				throw new \InvalidArgumentException( 'Audit context contains too many fields.' );
			}
			if ( $this->is_sensitive( $key ) ) {
				$normalized[ $key ] = self::REDACTED;
				continue;
			}
			if ( is_array( $value ) ) {
				$normalized[ $key ] = $this->normalize_map( $value, $depth + 1 );
				continue;
			}
			if ( is_string( $value ) ) {
				$normalized[ $key ] = Record_Validation::string( $value, 'Audit context value', self::MAX_STRING_BYTES, true );
				continue;
			}
			if ( is_float( $value ) && ! is_finite( $value ) ) {
				throw new \InvalidArgumentException( 'Audit context numbers must be finite.' );
			}
			if ( null === $value || is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$normalized[ $key ] = $value;
				continue;
			}
			throw new \InvalidArgumentException( 'Audit context values must be scalar, null, or named maps.' );
		}

		ksort( $normalized, SORT_STRING );
		return $normalized;
	}

	/**
	 * Whether a context key names a secret or personal data.
	 *
	 * @param string $key Context key.
	 */
	private function is_sensitive( string $key ): bool {
		$key = strtolower( $key );
		foreach ( array( 'authorization', 'credential', 'password', 'secret', 'token', 'api_key', 'subscriber', 'recipient', 'email', 'phone', 'first_name', 'last_name', 'address', 'request_body', 'response_body', 'raw_request', 'raw_response', 'provider_payload', 'stack_trace', 'exception' ) as $fragment ) {
			if ( str_contains( $key, $fragment ) ) {
				return true;
			}
		}
		return false;
	}
}
