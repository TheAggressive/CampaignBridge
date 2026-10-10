<?php
/**
 * Truthful provider capability advertisement.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable set of the operations one provider actually implements.
 *
 * Every known operation is reported as explicitly supported or unsupported.
 * Unknown operation names and non-boolean flags are rejected so a typo can
 * never advertise a feature that does not exist.
 */
final class Provider_Capabilities {
	/**
	 * Supported operations.
	 *
	 * @param string             $provider  Provider slug.
	 * @param array<int, string> $supported Supported operations.
	 */
	private function __construct(
		private readonly string $provider,
		private readonly array $supported
	) {}

	/**
	 * Build capabilities from an operation => bool map.
	 *
	 * Operations missing from the map are unsupported.
	 *
	 * @param string              $provider Provider slug.
	 * @param array<string, bool> $flags    Operation flags.
	 * @throws \InvalidArgumentException When a flag names an unknown operation or is not boolean.
	 */
	public static function from_flags( string $provider, array $flags ): self {
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $provider ) ) {
			throw new \InvalidArgumentException( 'Provider slug is invalid.' );
		}

		$supported = array();
		foreach ( $flags as $operation => $enabled ) {
			if ( ! is_string( $operation ) || ! Provider_Operation::is_valid( $operation ) ) {
				throw new \InvalidArgumentException( sprintf( 'Unknown provider operation: %s.', (string) $operation ) );
			}
			if ( ! is_bool( $enabled ) ) {
				throw new \InvalidArgumentException( sprintf( 'Provider operation flag must be boolean: %s.', $operation ) );
			}
			if ( $enabled ) {
				$supported[] = $operation;
			}
		}

		return new self( $provider, array_values( array_intersect( Provider_Operation::all(), $supported ) ) );
	}

	/**
	 * The capabilities's provider.
	 */
	public function provider(): string {
		return $this->provider;
	}

	/**
	 * Whether the provider implements one operation.
	 *
	 * @param string $operation Operation name.
	 */
	public function supports( string $operation ): bool {
		return in_array( $operation, $this->supported, true );
	}

	/**
	 * Supported operations in stable order.
	 *
	 * @return array<int, string>
	 */
	public function supported(): array {
		return $this->supported;
	}

	/**
	 * Every known operation with an explicit flag.
	 *
	 * @return array<string, bool>
	 */
	public function to_array(): array {
		$flags = array();
		foreach ( Provider_Operation::all() as $operation ) {
			$flags[ $operation ] = in_array( $operation, $this->supported, true );
		}

		return $flags;
	}
}
