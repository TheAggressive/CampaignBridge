<?php
/**
 * Allowlist-driven, deterministic, fail-closed token parser.
 *
 * @package CampaignBridge
 * @since   1.0.0
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email\Token;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses token expressions from a string against a registered token registry.
 *
 * Token syntax: `{{cb:category.name}}`
 *
 * Rules:
 * - Only `{{cb:...}}` sequences are recognized as token expressions.
 * - Any `{{` not immediately followed by `cb:` is literal text.
 * - Inner content must match the strict token ID pattern to be valid.
 * - Valid-format IDs not in the registry produce an "unknown token" error.
 * - Invalid-format IDs produce a "malformed token" error.
 * - Nested braces within a token expression produce a "nested token" error.
 * - Parse is fail-closed: any error-level diagnostic makes the result a failure.
 *
 * This class performs no WordPress function calls, no provider API calls,
 * and no I/O. It is a pure, deterministic transformation.
 */
final class Token_Parser {

	/**
	 * Strict pattern for a valid token ID (the content between `{{` and `}}`).
	 *
	 * Matches: cb:category.name or cb:category.sub.name
	 * Where category = [a-z]+, name = [a-z][a-z0-9_]*
	 */
	const TOKEN_ID_PATTERN = '/^cb:[a-z]+(?:\.[a-z][a-z0-9_]*)+$/';

	/**
	 * Maximum length of a single token expression (including braces).
	 *
	 * Prevents pathological inputs from causing excessive processing.
	 */
	const MAX_TOKEN_LENGTH = 256;

	/**
	 * Maximum number of tokens allowed in a single parse operation.
	 *
	 * Bounded to prevent resource exhaustion.
	 */
	const MAX_TOKEN_COUNT = 100;

	/**
	 * Parse token expressions from content.
	 *
	 * @param string         $content  The string to parse.
	 * @param Token_Registry $registry The registry of valid tokens.
	 *
	 * @return Token_Parse_Result
	 */
	public function parse( string $content, Token_Registry $registry ): Token_Parse_Result {
		$tokens      = array();
		$diagnostics = array();
		$length      = strlen( $content );
		$position    = 0;

		while ( $position < $length ) {
			$open = strpos( $content, '{{', $position );

			if ( false === $open ) {
				break;
			}

			// Check if this is a token attempt: must be immediately followed by `cb:`.
			if ( strlen( $content ) - ( $open + 2 ) >= 3 && substr( $content, $open + 2, 3 ) === 'cb:' ) {
				$position = $this->parse_token_at( $content, $open, $registry, $tokens, $diagnostics );
				continue;
			}

			// Not a token expression; skip past the `{{`.
			$position = $open + 2;
		}

		// Fail-closed: if any error-level diagnostics exist, the parse failed.
		foreach ( $diagnostics as $diagnostic ) {
			if ( $diagnostic->is_error() ) {
				return Token_Parse_Result::failure( $diagnostics );
			}
		}

		return Token_Parse_Result::with_diagnostics( $tokens, $diagnostics );
	}

	/**
	 * Attempt to parse a single token expression starting at the given position.
	 *
	 * @param string             $content     The full content string.
	 * @param int                $open        Position of the opening `{{`.
	 * @param Token_Registry     $registry    The token registry.
	 * @param Token_Definition[] $tokens  Accumulated valid tokens (by reference).
	 * @param Token_Diagnostic[] $diagnostics Accumulated diagnostics (by reference).
	 *
	 * @return int Next position to continue scanning.
	 */
	private function parse_token_at(
		string $content,
		int $open,
		Token_Registry $registry,
		array &$tokens,
		array &$diagnostics
	): int {
		// Find the closing `}}`.
		$close = strpos( $content, '}}', $open + 2 );

		if ( false === $close ) {
			// No closing brace found: malformed token.
			$diagnostics[] = Token_Diagnostic::malformed_token( $open );
			// Skip past the opening `{{` and continue scanning.
			return $open + 2;
		}

		// Extract the inner content (between `{{` and `}}`).
		$inner_length = $close - $open - 2;
		$inner        = substr( $content, $open + 2, $inner_length );

		// Bounded input check.
		if ( $inner_length > self::MAX_TOKEN_LENGTH ) {
			$diagnostics[] = Token_Diagnostic::malformed_token( $open );
			return $close + 2;
		}

		// Nested brace detection.
		if ( false !== strpos( $inner, '{{' ) || false !== strpos( $inner, '}}' ) ) {
			$diagnostics[] = Token_Diagnostic::nested_token( $open );
			return $close + 2;
		}

		// The inner content IS the token ID (e.g., "cb:subscriber.first_name").
		$token_id = $inner;

		// Validate format.
		if ( ! preg_match( self::TOKEN_ID_PATTERN, $token_id ) ) {
			$diagnostics[] = Token_Diagnostic::malformed_token( $open );
			return $close + 2;
		}

		// Token count bound.
		if ( count( $tokens ) >= self::MAX_TOKEN_COUNT ) {
			$diagnostics[] = Token_Diagnostic::malformed_token( $open );
			return $close + 2;
		}

		// Look up in registry.
		$definition = $registry->get( $token_id );

		if ( null === $definition ) {
			// Valid format but not registered: unknown token.
			$diagnostics[] = Token_Diagnostic::unknown_token( $token_id, $open );
			return $close + 2;
		}

		$tokens[] = $definition;
		return $close + 2;
	}
}
