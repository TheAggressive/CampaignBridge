<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Tab Field Validator for CampaignBridge Settings.
 *
 * Handles field validation logic for settings tabs with proper error handling
 * and validation rules for different field types and constraints.
 *
 * @package CampaignBridge\Admin\Pages\Tabs\Helpers
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages\Tabs\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field validation helper class.
 *
 * Provides validation methods for different field types and constraints
 * used in settings tabs with proper error handling and user feedback.
 */
class Tab_Field_Validator {
	/**
	 * Validate field value based on configuration.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param mixed  $value      Field value.
	 * @param array  $config     Field configuration.
	 * @return array Validation result with 'valid' boolean and 'errors' array.
	 */
	public static function validate_field( string $field_name, $value, array $config ): array {
		$errors = array();

		// Check required fields.
		if ( isset( $config['required'] ) && $config['required'] && self::is_empty( $value ) ) {
			$label = $config['label'] ?? $field_name;
			$errors[] = sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'campaignbridge' ),
				$label
			);
		}

		// Skip further validation if field is empty and not required.
		if ( self::is_empty( $value ) ) {
			return array(
				'valid'  => empty( $errors ),
				'errors' => $errors,
			);
		}

		// Type-specific validation.
		$field_type = $config['type'] ?? 'text';
		switch ( $field_type ) {
			case 'email':
				$errors = array_merge( $errors, self::validate_email( $value, $config ) );
				break;
			case 'url':
				$errors = array_merge( $errors, self::validate_url( $value, $config ) );
				break;
			case 'number':
				$errors = array_merge( $errors, self::validate_number( $value, $config ) );
				break;
			case 'select':
				$errors = array_merge( $errors, self::validate_select( $value, $config ) );
				break;
		}

		// Length validation.
		if ( isset( $config['max_length'] ) && is_string( $value ) ) {
			if ( strlen( $value ) > $config['max_length'] ) {
				$errors[] = sprintf(
					/* translators: 1: field label, 2: max length */
					__( '%1$s must be less than %2$d characters.', 'campaignbridge' ),
					$config['label'] ?? $field_name,
					$config['max_length']
				);
			}
		}

		// Min length validation.
		if ( isset( $config['min_length'] ) && is_string( $value ) ) {
			if ( strlen( $value ) < $config['min_length'] ) {
				$errors[] = sprintf(
					/* translators: 1: field label, 2: min length */
					__( '%1$s must be at least %2$d characters.', 'campaignbridge' ),
					$config['label'] ?? $field_name,
					$config['min_length']
				);
			}
		}

		// Custom validation callback.
		if ( isset( $config['validate_callback'] ) && is_callable( $config['validate_callback'] ) ) {
			$custom_error = call_user_func( $config['validate_callback'], $value, $field_name );
			if ( ! empty( $custom_error ) ) {
				$errors[] = $custom_error;
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Validate email field.
	 *
	 * @since 0.1.0
	 * @param mixed $value  Field value.
	 * @param array $config Field configuration.
	 * @return array Array of error messages.
	 */
	private static function validate_email( $value, array $config ): array {
		$errors = array();

		if ( ! is_email( $value ) ) {
			$errors[] = sprintf(
				/* translators: %s: field label */
				__( '%s must be a valid email address.', 'campaignbridge' ),
				$config['label'] ?? __( 'Email', 'campaignbridge' )
			);
		}

		return $errors;
	}

	/**
	 * Validate URL field.
	 *
	 * @since 0.1.0
	 * @param mixed $value  Field value.
	 * @param array $config Field configuration.
	 * @return array Array of error messages.
	 */
	private static function validate_url( $value, array $config ): array {
		$errors = array();

		if ( ! wp_http_validate_url( $value ) ) {
			$errors[] = sprintf(
				/* translators: %s: field label */
				__( '%s must be a valid URL.', 'campaignbridge' ),
				$config['label'] ?? __( 'URL', 'campaignbridge' )
			);
		}

		return $errors;
	}

	/**
	 * Validate number field.
	 *
	 * @since 0.1.0
	 * @param mixed $value  Field value.
	 * @param array $config Field configuration.
	 * @return array Array of error messages.
	 */
	private static function validate_number( $value, array $config ): array {
		$errors = array();

		if ( ! is_numeric( $value ) ) {
			$errors[] = sprintf(
				/* translators: %s: field label */
				__( '%s must be a number.', 'campaignbridge' ),
				$config['label'] ?? __( 'Number', 'campaignbridge' )
			);
			return $errors;
		}

		$value = (float) $value;

		if ( isset( $config['min'] ) && $value < $config['min'] ) {
			$errors[] = sprintf(
				/* translators: 1: field label, 2: minimum value */
				__( '%1$s must be at least %2$s.', 'campaignbridge' ),
				$config['label'] ?? __( 'Number', 'campaignbridge' ),
				$config['min']
			);
		}

		if ( isset( $config['max'] ) && $value > $config['max'] ) {
			$errors[] = sprintf(
				/* translators: 1: field label, 2: maximum value */
				__( '%1$s must be no more than %2$s.', 'campaignbridge' ),
				$config['label'] ?? __( 'Number', 'campaignbridge' ),
				$config['max']
			);
		}

		return $errors;
	}

	/**
	 * Validate select field.
	 *
	 * @since 0.1.0
	 * @param mixed $value  Field value.
	 * @param array $config Field configuration.
	 * @return array Array of error messages.
	 */
	private static function validate_select( $value, array $config ): array {
		$errors = array();

		if ( isset( $config['options'] ) && is_array( $config['options'] ) ) {
			if ( ! array_key_exists( $value, $config['options'] ) ) {
				$errors[] = sprintf(
					/* translators: %s: field label */
					__( 'Please select a valid option for %s.', 'campaignbridge' ),
					$config['label'] ?? __( 'Selection', 'campaignbridge' )
				);
			}
		}

		return $errors;
	}

	/**
	 * Check if a value is empty.
	 *
	 * @since 0.1.0
	 * @param mixed $value Value to check.
	 * @return bool True if empty, false otherwise.
	 */
	private static function is_empty( $value ): bool {
		if ( is_string( $value ) ) {
			return '' === trim( $value );
		}
		return empty( $value );
	}
}
