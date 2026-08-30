<?php
/**
 * Deterministic email artifact fingerprinting.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Workflow\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Creates stable hashes independent of associative-map insertion order. */
final class Artifact_Fingerprinter {
	/**
	 * Fingerprint canonicalized compiler input and output.
	 *
	 * @param array<string, mixed> $payload Fingerprint input.
	 */
	public function fingerprint( array $payload ): string {
		$normalized = $this->normalize( $payload );
		$json       = json_encode( $normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure deterministic workflow does not depend on WordPress.

		return 'sha256:' . hash( 'sha256', $json );
	}

	/**
	 * Recursively sort associative maps while preserving list order.
	 *
	 * @param mixed $value Value to normalize.
	 * @return mixed
	 */
	private function normalize( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( ! array_is_list( $value ) ) {
			ksort( $value, SORT_STRING );
		}

		foreach ( $value as $key => $item ) {
			$value[ $key ] = $this->normalize( $item );
		}

		return $value;
	}
}
