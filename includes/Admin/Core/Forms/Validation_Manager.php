<?php
/**
 * Validation Manager - Centralized validation logic
 *
 * Provides unified validation methods for forms, fields, and provider settings.
 * Consolidates validation logic from multiple sources to eliminate duplication.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Admin\Core\Forms\Validation_Messages;
use CampaignBridge\Admin\Core\Forms\Field_Sanitizer;

/**
 * Validation Manager Class
 *
 * Centralized validation logic for all form and provider validation needs.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Validation_Manager {

	/**
	 * Form validator instance
	 *
	 * @var Form_Validator
	 */
	private Form_Validator $form_validator;

	/**
	 * Constructor
	 *
	 * @param Form_Validator|null $form_validator Optional form validator instance.
	 */
	public function __construct( ?Form_Validator $form_validator = null ) {
		$this->form_validator = $form_validator ?? new Form_Validator();
	}

	/**
	 * Validate form data
	 *
	 * @param array<string, mixed> $data            Form data to validate.
	 * @param array<string, mixed> $fields          Field configurations.
	 * @param array<string>        $rendered_fields Optional rendered field IDs.
	 * @return array<string, mixed> Validation result with 'valid' and 'errors'.
	 */
	public function validate_form( array $data, array $fields, array $rendered_fields = array() ): array {
		return $this->form_validator->validate_form( $data, $fields, $rendered_fields );
	}

	/**
	 * Validate single field
	 *
	 * @param string               $field_id    Field ID.
	 * @param mixed                $value       Field value.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param array<string, mixed> $form_data    Optional form data context.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_field( string $field_id, $value, array $field_config, array $form_data = array() ): bool|\WP_Error {
		return $this->form_validator->validate_field( $field_id, $value, $field_config, $form_data );
	}

	/**
	 * Validate provider settings
	 *
	 * @param array<string, mixed> $settings    Settings to validate.
	 * @param array<string, mixed> $field_schemas Field schemas with validation rules.
	 * @return array<string, mixed> Validation result with 'valid' and 'errors'.
	 */
	public function validate_provider_settings( array $settings, array $field_schemas ): array {
		$errors   = array();
		$is_valid = true;

		foreach ( $field_schemas as $field_id => $schema ) {
			$value = $settings[ $field_id ] ?? null;

			// Check required fields.
			if ( isset( $schema['required'] ) && $schema['required'] && $this->is_empty_value( $value ) ) {
				$errors[ $field_id ] = Validation_Messages::get( 'field_required', array( $schema['label'] ?? $field_id ) );
				$is_valid            = false;
				continue;
			}

			// Skip validation for empty optional fields.
			if ( $this->is_empty_value( $value ) && ! ( $schema['required'] ?? false ) ) {
				continue;
			}

			// Validate field value.
			$validation_result = $this->validate_field_by_schema( $value, $schema );
			if ( is_wp_error( $validation_result ) ) {
				$errors[ $field_id ] = $validation_result->get_error_message();
				$is_valid            = false;
			}
		}

		return array(
			'valid'  => $is_valid,
			'errors' => $errors,
		);
	}

	/**
	 * Validate field by provider schema
	 *
	 * @param mixed                $value   Field value.
	 * @param array<string, mixed> $schema Field schema.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_field_by_schema( $value, array $schema ): bool|\WP_Error {
		$type = $schema['type'] ?? 'string';

		switch ( $type ) {
			case 'string':
				return $this->validate_string_field( $value, $schema );

			case 'boolean':
				return $this->validate_boolean_field( $value );

			case 'integer':
				return $this->validate_integer_field( $value, $schema );

			case 'email':
				return $this->validate_email_field( $value );

			case 'url':
				return $this->validate_url_field( $value );

			default:
				return true; // Unknown types pass validation.
		}
	}

	/**
	 * Validate string field
	 *
	 * @param mixed                $value  Field value.
	 * @param array<string, mixed> $schema Field schema.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_string_field( $value, array $schema ): bool|\WP_Error {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return new \WP_Error( 'invalid_type', 'Field must be a string.' );
		}

		$value = (string) $value;

		// Check length constraints.
		$min_length = $schema['min_length'] ?? 0;
		$max_length = $schema['max_length'] ?? 1000;

		if ( strlen( $value ) < $min_length ) {
			return new \WP_Error( 'too_short', sprintf( 'Minimum length is %d characters.', $min_length ) );
		}

		if ( strlen( $value ) > $max_length ) {
			return new \WP_Error( 'too_long', sprintf( 'Maximum length is %d characters.', $max_length ) );
		}

		// Check pattern validation.
		if ( isset( $schema['pattern'] ) && ! preg_match( $schema['pattern'], $value ) ) {
			return new \WP_Error( 'invalid_pattern', $schema['message'] ?? 'Value does not match required format.' );
		}

		return true;
	}

	/**
	 * Validate boolean field
	 *
	 * @param mixed $value Field value.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_boolean_field( $value ): bool|\WP_Error {
		if ( ! is_bool( $value ) && ! is_string( $value ) && ! is_numeric( $value ) ) {
			return new \WP_Error( 'invalid_type', 'Field must be a boolean.' );
		}

		return true;
	}

	/**
	 * Validate integer field
	 *
	 * @param mixed                $value  Field value.
	 * @param array<string, mixed> $schema Field schema.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_integer_field( $value, array $schema ): bool|\WP_Error {
		if ( ! is_numeric( $value ) ) {
			return new \WP_Error( 'invalid_type', 'Field must be a number.' );
		}

		$value = (int) $value;

		// Check range constraints.
		if ( isset( $schema['min'] ) && $value < $schema['min'] ) {
			return new \WP_Error( 'too_small', sprintf( 'Value must be at least %s.', $schema['min'] ) );
		}

		if ( isset( $schema['max'] ) && $value > $schema['max'] ) {
			return new \WP_Error( 'too_large', sprintf( 'Value must be no more than %s.', $schema['max'] ) );
		}

		return true;
	}

	/**
	 * Validate email field
	 *
	 * @param mixed $value Field value.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_email_field( $value ): bool|\WP_Error {
		if ( ! is_string( $value ) ) {
			return new \WP_Error( 'invalid_type', 'Email must be a string.' );
		}

		if ( ! is_email( $value ) ) {
			return new \WP_Error( 'invalid_email', Validation_Messages::get( 'invalid_email' ) );
		}

		return true;
	}

	/**
	 * Validate URL field
	 *
	 * @param mixed $value Field value.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_url_field( $value ): bool|\WP_Error {
		if ( ! is_string( $value ) ) {
			return new \WP_Error( 'invalid_type', 'URL must be a string.' );
		}

		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error( 'invalid_url', Validation_Messages::get( 'invalid_url' ) );
		}

		return true;
	}

	/**
	 * Validate required provider settings
	 *
	 * @param array<string, mixed> $settings Settings to validate.
	 * @param array<string>        $required Required setting keys.
	 * @return bool True if all required settings are present.
	 */
	public function validate_required_settings( array $settings, array $required ): bool {
		foreach ( $required as $key ) {
			if ( ! isset( $settings[ $key ] ) || $this->is_empty_value( $settings[ $key ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Validate legacy field (for Tab_Field_Validator compatibility)
	 *
	 * @param string               $field_name Field name.
	 * @param mixed                $value      Field value.
	 * @param array<string, mixed> $config     Field configuration.
	 * @return array<string, mixed> Validation result with 'valid' and 'errors'.
	 */
	public function validate_legacy_field( string $field_name, $value, array $config ): array {
		$errors   = array();
		$is_valid = true;

		// Basic required validation.
		if ( isset( $config['required'] ) && $config['required'] && $this->is_empty_value( $value ) ) {
			$errors[] = sprintf( '%s is required.', $field_name );
			$is_valid = false;
		}

		// Type-specific validation.
		$type = $config['type'] ?? 'text';

		switch ( $type ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					$errors[] = Validation_Messages::get( 'invalid_email' );
					$is_valid = false;
				}
				break;

			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					$errors[] = Validation_Messages::get( 'invalid_url' );
					$is_valid = false;
				}
				break;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					$errors[] = Validation_Messages::get( 'invalid_number' );
					$is_valid = false;
				}
				break;
		}

		return array(
			'valid'  => $is_valid,
			'errors' => $errors,
		);
	}

	/**
	 * Check if a value is considered empty
	 *
	 * @param mixed $value Value to check.
	 * @return bool True if empty, false otherwise.
	 */
	private function is_empty_value( $value ): bool {
		if ( null === $value || '' === $value ) {
			return true;
		}

		if ( is_array( $value ) ) {
			return empty( $value );
		}

		return false;
	}

	/**
	 * Sanitize form data
	 *
	 * @param array<string, mixed> $data Settings data to sanitize.
	 * @return array<string, mixed> Sanitized data.
	 */
	public function sanitize_form_data( array $data ): array {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			// Use Field_Sanitizer for text fields by default.
			$sanitized[ $key ] = Field_Sanitizer::sanitize_text( $value );
		}

		return $sanitized;
	}

	/**
	 * Sanitize provider settings
	 *
	 * @param array<string, mixed> $settings Settings to sanitize.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public function sanitize_provider_settings( array $settings ): array {
		return Field_Sanitizer::sanitize_provider_settings( $settings );
	}
}
