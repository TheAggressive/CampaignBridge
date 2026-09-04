<?php
/**
 * Conditional-field integrity checks for submitted forms.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Core\Client_Address;
use CampaignBridge\Core\Error_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Rejects hidden values from persistence and records manipulation attempts. */
final class Form_Conditional_Submission_Guard {
	/**
	 * Find populated conditional fields that should not be visible.
	 *
	 * @param Form_Conditional_Manager|null $manager Conditional manager.
	 * @param array<string, mixed>          $fields Field configuration.
	 * @param array<string, mixed>          $data Submitted values.
	 * @return array<string, string>
	 */
	public static function validate( ?Form_Conditional_Manager $manager, array $fields, array $data ): array {
		if ( ! $manager ) {
			return array();
		}

		$issues            = array();
		$integrity_manager = clone $manager;
		$integrity_manager->with_form_data( $data );

		foreach ( $data as $field_id => $value ) {
			if ( ! isset( $fields[ $field_id ]['conditional'] ) || ( empty( $value ) && ! is_numeric( $value ) ) ) {
				continue;
			}

			if ( ! $integrity_manager->should_show_field( $field_id ) ) {
				$issues[ $field_id ] = sprintf(
					'Data submitted for field that should be hidden based on conditional logic: %s',
					$field_id
				);
			}
		}

		return $issues;
	}

	/**
	 * Remove values belonging to hidden conditional fields.
	 *
	 * @param Form_Conditional_Manager|null $manager Conditional manager.
	 * @param array<string, mixed>          $fields Field configuration.
	 * @param array<string, mixed>          $data Submitted values.
	 * @return array<string, mixed>
	 */
	public static function filter( ?Form_Conditional_Manager $manager, array $fields, array $data ): array {
		if ( ! $manager ) {
			return $data;
		}

		return array_filter(
			$data,
			static fn( mixed $value, int|string $field_id ): bool => ! isset( $fields[ $field_id ]['conditional'] ) || $manager->should_show_field( (string) $field_id ),
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Record an integrity violation without writing raw submitted secrets.
	 *
	 * @param array<string, string> $issues Integrity violations.
	 * @param array<string, mixed>  $data Submitted form data.
	 */
	public static function log( array $issues, array $data ): void {
		Error_Handler::warning(
			'Potential form manipulation detected in conditional fields',
			array(
				'issues'      => $issues,
				'field_count' => count( $data ),
				'form_data'   => Form_Log_Sanitizer::sanitize( $data ),
				'user_id'     => get_current_user_id(),
				'user_ip'     => Client_Address::get(),
				'timestamp'   => current_time( 'mysql' ),
			)
		);
	}
}
