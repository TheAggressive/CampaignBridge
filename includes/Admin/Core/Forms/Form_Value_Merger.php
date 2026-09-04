<?php
/**
 * Field-aware merging for partial form submissions.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Core\Encryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preserves values that were intentionally omitted from a partial request. */
final class Form_Value_Merger {
	/**
	 * Merge configured fields using type-specific behavior.
	 *
	 * @param array<string, mixed> $submitted Submitted values.
	 * @param array<string, mixed> $existing Existing values.
	 * @param array<string, mixed> $fields Field configuration.
	 * @return array<string, mixed>
	 */
	public static function merge( array $submitted, array $existing, array $fields ): array {
		$merged              = array();
		$processed_repeaters = array();

		foreach ( $fields as $field_id => $field_config ) {
			if ( str_contains( $field_id, '___' ) ) {
				list( $base_name ) = explode( '___', $field_id, 2 );
				if ( isset( $submitted[ $base_name ] ) && ! isset( $processed_repeaters[ $base_name ] ) ) {
					$merged[ $base_name ]              = $submitted[ $base_name ];
					$processed_repeaters[ $base_name ] = true;
					continue;
				}
				if ( isset( $processed_repeaters[ $base_name ] ) ) {
					continue;
				}
			}

			$submitted_value     = $submitted[ $field_id ] ?? null;
			$existing_value      = $existing[ $field_id ] ?? null;
			$merged[ $field_id ] = self::field_value( $submitted_value, $existing_value, $field_config );
		}

		return $merged;
	}

	/**
	 * Resolve a single value using its field type.
	 *
	 * @param mixed                $submitted Submitted value.
	 * @param mixed                $existing Existing value.
	 * @param array<string, mixed> $config Field configuration.
	 */
	private static function field_value( mixed $submitted, mixed $existing, array $config ): mixed {
		$type = $config['type'] ?? 'text';

		if ( 'encrypted' === $type && empty( $submitted ) && ! empty( $existing ) && Encryption::is_encrypted_value( $existing ) ) {
			return $existing;
		}

		if ( in_array( $type, array( 'checkbox', 'switch' ), true ) && null === $submitted ) {
			return false;
		}

		return $submitted ?? $existing;
	}
}
