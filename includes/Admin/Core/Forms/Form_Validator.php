<?php
/**
 * Form Validator
 *
 * Handles form validation with comprehensive rules and error handling.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Validator Class
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Validator {

	/**
	 * Validate form data against field configurations
	 *
	 * @param array $data   Form data to validate.
	 * @param array $fields Field configurations.
	 * @return array Validation result with 'valid' boolean and 'errors' array.
	 */
	public function validate( array $data, array $fields ): array {
		$errors   = array();
		$is_valid = true;

		foreach ( $fields as $field_id => $field_config ) {
			$value = $data[ $field_id ] ?? '';

			// Create field instance for validation.
			$field_factory = new Form_Field_Factory();
			$field         = $field_factory->create_field( $field_id, $field_config, $value );

			$field_validation = $field->validate( $value );

			if ( is_wp_error( $field_validation ) ) {
				$errors[ $field_id ] = $field_validation->get_error_message();
				$is_valid            = false;
			}
		}

		return array(
			'valid'  => $is_valid,
			'errors' => $errors,
		);
	}

	/**
	 * Validate a single field
	 *
	 * @param mixed $value       Field value.
	 * @param array $field_config Field configuration.
	 * @return bool|\WP_Error True if valid, \WP_Error if invalid.
	 */
	public function validate_field( $value, array $field_config ) {
		$field_type       = $field_config['type'] ?? 'text';
		$validation_rules = $field_config['validation'] ?? array();

		// Type-specific validation.
		$type_validation = $this->validate_field_type( $value, $field_type, $field_config );
		if ( is_wp_error( $type_validation ) ) {
			return $type_validation;
		}

		// Custom validation rules.
		foreach ( $validation_rules as $rule => $rule_config ) {
			$rule_validation = $this->validate_rule( $rule, $value, $rule_config, $field_config );
			if ( is_wp_error( $rule_validation ) ) {
				return $rule_validation;
			}
		}

		return true;
	}

	/**
	 * Validate field type
	 *
	 * @param mixed  $value       Field value.
	 * @param string $field_type  Field type.
	 * @param array  $field_config Field configuration.
	 * @return bool|WP_Error
	 */
	private function validate_field_type( $value, string $field_type, array $field_config ) {
		// Required field validation.
		if ( ( $field_config['required'] ?? false ) && $this->is_empty_value( $value ) ) {
			return new \WP_Error(
				'field_required',
				sprintf(
					/* translators: %s: field label */
					__( '%s is required.', 'campaignbridge' ),
					$field_config['label'] ?? 'This field'
				)
			);
		}

		// Skip further validation if empty and not required.
		if ( $this->is_empty_value( $value ) && ! ( $field_config['required'] ?? false ) ) {
			return true;
		}

		// Type-specific validation.
		switch ( $field_type ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					return new \WP_Error(
						'invalid_email',
						__( 'Please enter a valid email address.', 'campaignbridge' )
					);
				}
				break;

			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return new \WP_Error(
						'invalid_url',
						__( 'Please enter a valid URL.', 'campaignbridge' )
					);
				}
				break;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return new \WP_Error(
						'invalid_number',
						__( 'Please enter a valid number.', 'campaignbridge' )
					);
				}

				$value = floatval( $value );

				// Min/Max validation for numbers.
				if ( isset( $field_config['min'] ) && $value < $field_config['min'] ) {
					return new \WP_Error(
						'number_too_small',
						sprintf(
							/* translators: %s: minimum value */
							__( 'Value must be at least %s.', 'campaignbridge' ),
							$field_config['min']
						)
					);
				}

				if ( isset( $field_config['max'] ) && $value > $field_config['max'] ) {
					return new \WP_Error(
						'number_too_large',
						sprintf(
							/* translators: %s: maximum value */
							__( 'Value must be no more than %s.', 'campaignbridge' ),
							$field_config['max']
						)
					);
				}
				break;

			case 'date':
				if ( ! $this->is_valid_date( $value ) ) {
					return new \WP_Error(
						'invalid_date',
						__( 'Please enter a valid date.', 'campaignbridge' )
					);
				}
				break;

			case 'file':
				// File validation for processed upload data
				if ( is_array( $value ) ) {
					$required_keys = array( 'file', 'url', 'filename' );
					foreach ( $required_keys as $key ) {
						if ( ! isset( $value[ $key ] ) ) {
							return new \WP_Error(
								'invalid_file_data',
								__( 'Invalid file data structure.', 'campaignbridge' )
							);
						}
					}

					// Validate file actually exists
					if ( ! file_exists( $value['file'] ) ) {
						return new \WP_Error(
							'file_not_found',
							__( 'Uploaded file could not be found.', 'campaignbridge' )
						);
					}

					// Validate file size if specified
					if ( isset( $field_config['max_size'] ) ) {
						$file_size = filesize( $value['file'] );
						if ( $file_size > $field_config['max_size'] ) {
							return new \WP_Error(
								'file_too_large',
								sprintf(
									/* translators: %s: maximum file size */
									__( 'File size exceeds maximum allowed size of %s.', 'campaignbridge' ),
									size_format( $field_config['max_size'] )
								)
							);
						}
					}
				} elseif ( is_string( $value ) && ! empty( $value ) ) {
					// Allow string values (URLs or attachment IDs) for existing files
					break;
				} elseif ( $field_config['required'] ?? false ) {
					return new \WP_Error(
						'file_required',
						__( 'Please select a file to upload.', 'campaignbridge' )
					);
				}
				break;
		}

		return true;
	}

	/**
	 * Validate a specific rule
	 *
	 * @param string $rule        Rule name.
	 * @param mixed  $value       Value to validate.
	 * @param mixed  $rule_config Rule configuration.
	 * @param array  $field_config Field configuration.
	 * @return bool|WP_Error
	 */
	private function validate_rule( string $rule, $value, $rule_config, array $field_config ) {
		switch ( $rule ) {
			case 'min_length':
				if ( strlen( (string) $value ) < $rule_config ) {
					return new \WP_Error(
						'min_length',
						sprintf(
							/* translators: %d: minimum length */
							__( 'Minimum length is %d characters.', 'campaignbridge' ),
							$rule_config
						)
					);
				}
				break;

			case 'max_length':
				if ( strlen( (string) $value ) < $rule_config ) {
					return new \WP_Error(
						'max_length',
						sprintf(
							/* translators: %d: maximum length */
							__( 'Maximum length is %d characters.', 'campaignbridge' ),
							$rule_config
						)
					);
				}
				break;

			case 'pattern':
				if ( ! preg_match( $rule_config, $value ) ) {
					return new \WP_Error(
						'invalid_pattern',
						__( 'Value does not match the required format.', 'campaignbridge' )
					);
				}
				break;

			case 'in':
				if ( ! in_array( $value, (array) $rule_config, true ) ) {
					return new \WP_Error(
						'value_not_allowed',
						__( 'Selected value is not allowed.', 'campaignbridge' )
					);
				}
				break;

			case 'custom':
				if ( is_callable( $rule_config ) ) {
					$result = call_user_func( $rule_config, $value, $field_config );

					if ( true !== $result ) {
						$message = is_string( $result ) ? $result : __( 'Custom validation failed.', 'campaignbridge' );
						return new \WP_Error( 'custom_validation', $message );
					}
				}
				break;
		}

		return true;
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
	 * Validate date string
	 *
	 * @param string $date Date string to validate.
	 * @return bool True if valid date, false otherwise.
	 */
	private function is_valid_date( string $date ): bool {
		$timestamp = strtotime( $date );
		return false !== $timestamp && gmdate( 'Y-m-d', $timestamp ) === $date;
	}

	/**
	 * Sanitize field value based on type
	 *
	 * @param mixed $value       Raw value.
	 * @param array $field_config Field configuration.
	 * @return mixed Sanitized value.
	 */
	public function sanitize_value( $value, array $field_config ) {
		$field_type = $field_config['type'] ?? 'text';

		switch ( $field_type ) {
			case 'email':
				return sanitize_email( $value );

			case 'url':
				return esc_url_raw( $value );

			case 'number':
				return is_numeric( $value ) ? floatval( $value ) : 0;

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'checkbox':
				return ! empty( $value ) ? 1 : 0;

			case 'wysiwyg':
				return wp_kses_post( $value );

			default:
				return sanitize_text_field( $value );
		}
	}
}
