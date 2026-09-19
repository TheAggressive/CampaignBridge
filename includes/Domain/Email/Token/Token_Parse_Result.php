<?php
/**
 * Result of parsing a token-bearing string.
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
 * Immutable result of a token parse operation.
 *
 * Contains the ordered list of resolved token definitions and all diagnostics.
 * A result is considered successful when it contains no error-level diagnostics.
 */
final class Token_Parse_Result {

	/**
	 * Ordered list of resolved token definitions.
	 *
	 * @var Token_Definition[]
	 */
	private array $tokens;

	/**
	 * All diagnostics produced during parsing.
	 *
	 * @var Token_Diagnostic[]
	 */
	private array $diagnostics;

	/**
	 * Construct a parse result.
	 *
	 * @param Token_Definition[] $tokens      Ordered token definitions.
	 * @param Token_Diagnostic[] $diagnostics All diagnostics.
	 */
	private function __construct( array $tokens, array $diagnostics ) {
		$this->tokens      = array_values( $tokens );
		$this->diagnostics = array_values( $diagnostics );
	}

	/**
	 * Create a successful parse result.
	 *
	 * @param Token_Definition[] $tokens Ordered token definitions.
	 *
	 * @return static
	 */
	public static function success( array $tokens ): self {
		return new self( $tokens, array() );
	}

	/**
	 * Create a failed parse result.
	 *
	 * @param Token_Diagnostic[] $diagnostics Error diagnostics.
	 *
	 * @return static
	 */
	public static function failure( array $diagnostics ): self {
		return new self( array(), $diagnostics );
	}

	/**
	 * Create a result with both tokens and diagnostics.
	 *
	 * @param Token_Definition[] $tokens      Ordered token definitions.
	 * @param Token_Diagnostic[] $diagnostics All diagnostics.
	 *
	 * @return static
	 */
	public static function with_diagnostics( array $tokens, array $diagnostics ): self {
		return new self( $tokens, $diagnostics );
	}

	/**
	 * Check whether the parse succeeded (no error-level diagnostics).
	 *
	 * @return bool
	 */
	public function is_successful(): bool {
		foreach ( $this->diagnostics as $diagnostic ) {
			if ( $diagnostic->is_error() ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get the ordered list of resolved token definitions.
	 *
	 * @return Token_Definition[]
	 */
	public function get_tokens(): array {
		return $this->tokens;
	}

	/**
	 * Get all diagnostics.
	 *
	 * @return Token_Diagnostic[]
	 */
	public function get_diagnostics(): array {
		return $this->diagnostics;
	}

	/**
	 * Get only error-level diagnostics.
	 *
	 * @return Token_Diagnostic[]
	 */
	public function get_errors(): array {
		return array_values(
			array_filter(
				$this->diagnostics,
				static function ( Token_Diagnostic $d ): bool {
					return $d->is_error();
				}
			)
		);
	}

	/**
	 * Get the count of resolved tokens.
	 *
	 * @return int
	 */
	public function token_count(): int {
		return count( $this->tokens );
	}
}
