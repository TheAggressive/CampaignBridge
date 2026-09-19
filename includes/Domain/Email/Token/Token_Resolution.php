<?php
/**
 * Result of resolving tokens in one compiler value.
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
 * Immutable token resolution outcome.
 *
 * A successful result carries the value with CampaignBridge-owned tokens
 * replaced and provider-resolved tokens preserved canonically. A failed result
 * carries only error diagnostics and no value, so callers cannot emit a
 * partially resolved string.
 */
final class Token_Resolution {

	/**
	 * Construct a resolution.
	 *
	 * @param string|null        $value       Resolved value, or null on failure.
	 * @param Token_Diagnostic[] $diagnostics Error diagnostics.
	 */
	private function __construct(
		private readonly ?string $value,
		private readonly array $diagnostics
	) {}

	/**
	 * Create a successful resolution.
	 *
	 * @param string $value Resolved value.
	 *
	 * @return static
	 */
	public static function success( string $value ): self {
		return new self( $value, array() );
	}

	/**
	 * Create a failed resolution.
	 *
	 * @param Token_Diagnostic[] $diagnostics Error diagnostics.
	 *
	 * @return static
	 *
	 * @throws \InvalidArgumentException When no diagnostic explains the failure.
	 */
	public static function failure( array $diagnostics ): self {
		if ( array() === $diagnostics ) {
			throw new \InvalidArgumentException( 'A failed token resolution requires a diagnostic.' );
		}

		return new self( null, array_values( $diagnostics ) );
	}

	/**
	 * Check whether resolution succeeded.
	 *
	 * @return bool
	 */
	public function is_successful(): bool {
		return null !== $this->value;
	}

	/**
	 * Get the resolved value, or null when resolution failed.
	 *
	 * @return string|null
	 */
	public function value(): ?string {
		return $this->value;
	}

	/**
	 * Get the error diagnostics.
	 *
	 * @return Token_Diagnostic[]
	 */
	public function diagnostics(): array {
		return $this->diagnostics;
	}
}
