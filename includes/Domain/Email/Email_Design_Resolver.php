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
		$design    = $this->normalizer->normalize( $validated, $brand_kit ?? Brand_Kit::defaults() );
		$payload   = $this->canonicalize( $design );
		$json      = json_encode( $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure deterministic domain hashing.

		return new Resolved_Email_Design( $design, 'sha256:' . hash( 'sha256', $json ) );
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
