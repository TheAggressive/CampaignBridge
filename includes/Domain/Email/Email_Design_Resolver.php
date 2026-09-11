<?php
/**
 * Effective email design resolution.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Domain\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Builds the one immutable design consumed by authoring and compilation. */
final class Email_Design_Resolver {
	/**
	 * Create a resolver from its validation and normalization stages.
	 *
	 * @param Email_Design_Validator  $validator  Contract validator.
	 * @param Email_Design_Normalizer $normalizer Canonical normalizer.
	 */
	public function __construct(
		private readonly Email_Design_Validator $validator,
		private readonly Email_Design_Normalizer $normalizer = new Email_Design_Normalizer()
	) {}

	/**
	 * Resolve raw input and identity into the runtime design truth.
	 *
	 * @param array<string, mixed> $manifest  Raw decoded manifest.
	 * @param Brand_Kit|null       $brand_kit Active identity or safe defaults.
	 */
	public function resolve( array $manifest, ?Brand_Kit $brand_kit = null ): Resolved_Email_Design {
		$validated = $this->validator->validate( $manifest );
		return $this->resolved( $validated, $brand_kit );
	}

	/**
	 * Resolve ordered manifests, validating every cumulative source layer.
	 *
	 * @param array<int, array<string, mixed>> $layers    Raw manifests from low to high precedence.
	 * @param Brand_Kit|null                   $brand_kit Active identity or safe defaults.
	 * @throws Email_Design_Error When a source layer violates the contract.
	 */
	public function resolve_layers( array $layers, ?Brand_Kit $brand_kit = null ): Resolved_Email_Design {
		$manifest = array_shift( $layers );
		if ( ! is_array( $manifest ) ) {
			throw new Email_Design_Error( 'design.invalid_property', '$', 'Email design requires a packaged base manifest.' );
		}
		$manifest = $this->validator->validate( $manifest );
		foreach ( $layers as $overlay ) {
			$manifest = $this->validator->validate( $this->merge( $manifest, $overlay ) );
		}
		return $this->resolved( $manifest, $brand_kit );
	}

	/**
	 * Normalize one fully validated effective manifest.
	 *
	 * @param array<string, mixed> $validated Validated effective manifest.
	 * @param Brand_Kit|null       $brand_kit Active identity or safe defaults.
	 */
	private function resolved( array $validated, ?Brand_Kit $brand_kit ): Resolved_Email_Design {
		$design  = $this->normalizer->normalize( $validated, $brand_kit ?? Brand_Kit::defaults() );
		$payload = $this->canonicalize( $design );
		$json    = json_encode( $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure deterministic domain hashing.

		return new Resolved_Email_Design( $design, 'sha256:' . hash( 'sha256', $json ) );
	}

	/**
	 * Merge one higher-priority layer, retaining presets not overridden by slug.
	 *
	 * @param array<string, mixed> $base    Lower-priority design.
	 * @param array<string, mixed> $overlay Higher-priority design.
	 * @return array<string, mixed>
	 */
	private function merge( array $base, array $overlay ): array {
		foreach ( $overlay as $key => $value ) {
			if ( isset( $base[ $key ] ) && is_array( $base[ $key ] ) && is_array( $value ) ) {
				$base[ $key ] = array_is_list( $base[ $key ] ) && array_is_list( $value )
					? $this->merge_presets( $base[ $key ], $value )
					: $this->merge( $base[ $key ], $value );
				continue;
			}
			$base[ $key ] = $value;
		}
		return $base;
	}

	/**
	 * Merge preset lists by stable slug; replace other lists as leaf values.
	 *
	 * @param array<int, mixed> $base    Lower-priority list.
	 * @param array<int, mixed> $overlay Higher-priority list.
	 * @return array<int, mixed>
	 */
	private function merge_presets( array $base, array $overlay ): array {
		$slugs = array_column( $base, 'slug' );
		if ( count( $slugs ) !== count( $base ) ) {
			return $overlay;
		}
		$indexes = array_flip( $slugs );
		foreach ( $overlay as $preset ) {
			if ( ! is_array( $preset ) || ! isset( $preset['slug'] ) || ! is_string( $preset['slug'] ) ) {
				return $overlay;
			}
			if ( isset( $indexes[ $preset['slug'] ] ) ) {
				$base[ $indexes[ $preset['slug'] ] ] = $preset;
				continue;
			}
			$indexes[ $preset['slug'] ] = count( $base );
			$base[]                     = $preset;
		}
		return $base;
	}

	/**
	 * Recursively order associative maps for deterministic hashing.
	 *
	 * @param mixed $value Value to canonicalize.
	 */
	private function canonicalize( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( ! array_is_list( $value ) ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = $this->canonicalize( $item );
		}
		return $value;
	}
}
