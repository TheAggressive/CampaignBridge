<?php
/**
 * Closed JSON Schema validation for email design manifests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Validates the bounded subset of JSON Schema used by the v1 contract. */
final class Email_Design_Validator {
	/**
	 * Create a validator.
	 *
	 * @param array<string, mixed> $schema Canonical repository-owned schema.
	 */
	public function __construct( private readonly array $schema ) {}

	/**
	 * Validate and return a manifest unchanged.
	 *
	 * @param array<string, mixed> $manifest Decoded manifest.
	 * @return array<string, mixed>
	 * @throws Email_Design_Error When the manifest violates the contract.
	 */
	public function validate( array $manifest ): array {
		$version = $manifest['version'] ?? null;
		if ( is_int( $version ) && 1 !== $version ) {
			throw new Email_Design_Error( 'design.unsupported_version', '$.version', 'Email design version is not supported.' );
		}

		$this->validate_value( $manifest, $this->schema, '$' );
		return $manifest;
	}

	/**
	 * Validate one value against one schema rule.
	 *
	 * @param mixed                $value Candidate value.
	 * @param array<string, mixed> $rule  Schema rule.
	 * @param string               $path  Safe manifest path.
	 */
	private function validate_value( mixed $value, array $rule, string $path ): void {
		if ( isset( $rule['$ref'] ) && is_string( $rule['$ref'] ) ) {
			$this->validate_value( $value, $this->reference( $rule['$ref'] ), $path );
			return;
		}

		if ( isset( $rule['oneOf'] ) && is_array( $rule['oneOf'] ) ) {
			$this->validate_one_of( $value, $rule['oneOf'], $path );
			return;
		}

		if ( array_key_exists( 'const', $rule ) && $value !== $rule['const'] ) {
			$this->fail( $path, 'must equal the contract value.' );
		}

		if ( isset( $rule['enum'] ) && is_array( $rule['enum'] ) && ! in_array( $value, $rule['enum'], true ) ) {
			$this->fail( $path, 'is not an allowed value.' );
		}

		if ( isset( $rule['type'] ) && is_string( $rule['type'] ) && ! $this->matches_type( $value, $rule['type'] ) ) {
			$this->fail( $path, 'has an invalid type.' );
		}

		if ( is_string( $value ) ) {
			$this->validate_string( $value, $rule, $path );
		} elseif ( is_int( $value ) || is_float( $value ) ) {
			$this->validate_number( $value, $rule, $path );
		} elseif ( is_array( $value ) && array_is_list( $value ) ) {
			$this->validate_list( $value, $rule, $path );
		} elseif ( is_array( $value ) ) {
			$this->validate_object( $value, $rule, $path );
		}
	}

	/**
	 * Require exactly one candidate schema to match.
	 *
	 * @param mixed             $value Candidate value.
	 * @param array<int, mixed> $rules Candidate rules.
	 * @param string            $path  Safe manifest path.
	 */
	private function validate_one_of( mixed $value, array $rules, string $path ): void {
		$matches = 0;
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			try {
				$this->validate_value( $value, $rule, $path );
				++$matches;
			} catch ( Email_Design_Error $error ) {
				unset( $error ); // A candidate is expected to fail while alternatives are tried.
			}
		}
		if ( 1 !== $matches ) {
			$this->fail( $path, 'does not match exactly one allowed value shape.' );
		}
	}

	/**
	 * Validate string keywords.
	 *
	 * @param string               $value Candidate string.
	 * @param array<string, mixed> $rule  Schema rule.
	 * @param string               $path  Safe manifest path.
	 */
	private function validate_string( string $value, array $rule, string $path ): void {
		if ( isset( $rule['minLength'] ) && strlen( $value ) < (int) $rule['minLength'] ) {
			$this->fail( $path, 'is too short.' );
		}
		if ( isset( $rule['maxLength'] ) && strlen( $value ) > (int) $rule['maxLength'] ) {
			$this->fail( $path, 'is too long.' );
		}
		if ( isset( $rule['pattern'] ) && is_string( $rule['pattern'] ) && 1 !== preg_match( '~' . str_replace( '~', '\\~', $rule['pattern'] ) . '~D', $value ) ) {
			$this->fail( $path, 'has an invalid format.' );
		}
	}

	/**
	 * Validate numeric keywords.
	 *
	 * @param int|float            $value Candidate number.
	 * @param array<string, mixed> $rule  Schema rule.
	 * @param string               $path  Safe manifest path.
	 */
	private function validate_number( int|float $value, array $rule, string $path ): void {
		if ( isset( $rule['minimum'] ) && $value < $rule['minimum'] ) {
			$this->fail( $path, 'is below the allowed minimum.' );
		}
		if ( isset( $rule['maximum'] ) && $value > $rule['maximum'] ) {
			$this->fail( $path, 'is above the allowed maximum.' );
		}
	}

	/**
	 * Validate list keywords and items.
	 *
	 * @param array<int, mixed>    $value List value.
	 * @param array<string, mixed> $rule  Schema rule.
	 * @param string               $path  Safe manifest path.
	 */
	private function validate_list( array $value, array $rule, string $path ): void {
		if ( isset( $rule['minItems'] ) && count( $value ) < (int) $rule['minItems'] ) {
			$this->fail( $path, 'contains too few items.' );
		}
		if ( isset( $rule['maxItems'] ) && count( $value ) > (int) $rule['maxItems'] ) {
			$this->fail( $path, 'contains too many items.' );
		}
		if ( isset( $rule['items'] ) && is_array( $rule['items'] ) ) {
			foreach ( $value as $index => $item ) {
				$this->validate_value( $item, $rule['items'], $path . '[' . $index . ']' );
			}
		}
	}

	/**
	 * Validate required, known object properties.
	 *
	 * @param array<string, mixed> $value Object value.
	 * @param array<string, mixed> $rule  Schema rule.
	 * @param string               $path  Safe manifest path.
	 */
	private function validate_object( array $value, array $rule, string $path ): void {
		$properties = isset( $rule['properties'] ) && is_array( $rule['properties'] ) ? $rule['properties'] : array();
		$required   = isset( $rule['required'] ) && is_array( $rule['required'] ) ? $rule['required'] : array();

		foreach ( $required as $key ) {
			if ( is_string( $key ) && ! array_key_exists( $key, $value ) ) {
				$this->fail( $path . '.' . $key, 'is required.' );
			}
		}
		if ( isset( $rule['minProperties'] ) && count( $value ) < (int) $rule['minProperties'] ) {
			$this->fail( $path, 'contains too few properties.' );
		}

		foreach ( $value as $key => $item ) {
			if ( ! is_string( $key ) || ! isset( $properties[ $key ] ) || ! is_array( $properties[ $key ] ) ) {
				if ( false === ( $rule['additionalProperties'] ?? true ) ) {
					$this->fail( $path . '.' . (string) $key, 'is not a supported property.' );
				}
				continue;
			}
			$this->validate_value( $item, $properties[ $key ], $path . '.' . $key );
		}
	}

	/**
	 * Determine whether a PHP value represents a JSON Schema type.
	 *
	 * @param mixed  $value Candidate value.
	 * @param string $type  JSON Schema type.
	 */
	private function matches_type( mixed $value, string $type ): bool {
		return match ( $type ) {
			'object'  => is_array( $value ) && ! array_is_list( $value ),
			'array'   => is_array( $value ) && array_is_list( $value ),
			'string'  => is_string( $value ),
			'integer' => is_int( $value ),
			'number'  => is_int( $value ) || is_float( $value ),
			'boolean' => is_bool( $value ),
			default   => false,
		};
	}

	/**
	 * Resolve one local schema definition.
	 *
	 * @param string $reference Local reference.
	 * @return array<string, mixed>
	 * @throws Email_Design_Error When the schema reference is unsupported.
	 */
	private function reference( string $reference ): array {
		if ( ! str_starts_with( $reference, '#/$defs/' ) ) {
			throw new Email_Design_Error( 'design.invalid_property', '$schema', 'Email design schema contains an unsupported reference.' );
		}
		$name = substr( $reference, strlen( '#/$defs/' ) );
		$rule = $this->schema['$defs'][ $name ] ?? null;
		if ( ! is_array( $rule ) ) {
			throw new Email_Design_Error( 'design.invalid_property', '$schema', 'Email design schema contains an unknown reference.' );
		}
		return $rule;
	}

	/**
	 * Throw a stable validation error for one manifest path.
	 *
	 * @param string $path   Safe manifest path.
	 * @param string $reason Validation failure.
	 * @throws Email_Design_Error Always.
	 */
	private function fail( string $path, string $reason ): never {
		$code = match ( true ) {
			str_starts_with( $path, '$.styles.blocks.' ) && ! str_contains( substr( $path, strlen( '$.styles.blocks.' ) ), '.' ) => 'design.invalid_block',
			str_starts_with( $path, '$.styles.blocks.' ) => 'design.unsupported_block_style',
			str_contains( $path, '.color' )       => 'design.invalid_color',
			str_contains( $path, '.font' )        => 'design.invalid_font',
			str_contains( $path, '.spacing' ),
			str_contains( $path, '.dimensions' )  => 'design.invalid_spacing',
			default => 'design.invalid_property',
		};
		throw new Email_Design_Error( $code, $path, 'Email design value at ' . $path . ' ' . $reason );
	}
}
