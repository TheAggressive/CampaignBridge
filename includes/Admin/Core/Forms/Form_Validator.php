<?php
/**
 * Form Validator - Centralized validation logic for form fields
 *
 * Handles PHP validation rules, JavaScript validation conversion,
 * and validation rule management.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Admin\Core\Forms\Validation_Messages;
use CampaignBridge\Admin\Core\Forms\Field_Sanitizer;

/**
 * Form Validator - Centralized validation logic
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Validator {

	/**
	 * Available validation rules
	 *
	 * @var array<string, callable>
	 */
	private array $validation_rules = array();

	/**
	 * Conditional manager instance
	 *
	 * @var Form_Conditional_Manager|null
	 */
	private ?Form_Conditional_Manager $conditional_manager = null;

	/**
	 * Cache for field visibility checks during validation
	 *
	 * @var array<string, bool>
	 */
	private array $visibility_cache = array();

	/**
	 * Constructor - Register default validation rules
	 *
	 * @param Form_Conditional_Manager|null $conditional_manager Optional conditional manager instance.
	 */
	public function __construct( ?Form_Conditional_Manager $conditional_manager = null ) {
		$this->conditional_manager = $conditional_manager;
		$this->register_default_rules();
	}

	/**
	 * Set the conditional manager
	 *
	 * @param Form_Conditional_Manager $conditional_manager Conditional manager instance.
	 * @return void
	 */
	public function set_conditional_manager( Form_Conditional_Manager $conditional_manager ): void {
		$this->conditional_manager = $conditional_manager;
	}

	/**
	 * Register a custom validation rule
	 *
	 * @param string   $rule_name Rule name.
	 * @param callable $validator Validation function.
	 */
	public function register_rule( string $rule_name, callable $validator ): void {
		$this->validation_rules[ $rule_name ] = $validator;
	}

	/**
	 * Validate field value against rules
	 *
	 * @param mixed                $value       Value to validate.
	 * @param array<string, mixed> $rules       Validation rules.
	 * @param string               $field_label Field label for error messages.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate( $value, array $rules, string $field_label = '' ) {
		// Check required validation first.
		if ( ! empty( $rules['required'] ) && empty( $value ) ) {
			return new \WP_Error(
				'field_required',
				sprintf(
					Validation_Messages::get( 'field_required' ),
					$field_label
				)
			);
		}

		// Apply other validation rules.
		foreach ( $rules as $rule => $rule_config ) {
			if ( 'required' === $rule ) {
				continue; // Already handled above.
			}

			$result = $this->validate_rule( $rule, $value, $rule_config, $field_label );
			if ( \is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Validate a specific rule
	 *
	 * @param string $rule        Rule name.
	 * @param mixed  $value       Value to validate.
	 * @param mixed  $rule_config Rule configuration.
	 * @param string $field_label Field label for error messages.
	 * @return bool|\WP_Error
	 */
	private function validate_rule( string $rule, $value, $rule_config, string $field_label = '' ) {
		if ( ! isset( $this->validation_rules[ $rule ] ) ) {
			return true; // Unknown rule, skip.
		}

		return $this->validation_rules[ $rule ]( $value, $rule_config, $field_label );
	}

	/**
	 * Convert PHP validation rules to JavaScript validation format
	 *
	 * @param array<string, mixed> $php_rules PHP validation rules.
	 * @param string               $field_label Field label for messages.
	 * @return array<array<string, mixed>> JavaScript validation rules.
	 */
	public function convert_to_js_rules( array $php_rules, string $field_label = '' ): array {
		$js_rules = array();

		// Define mapping of PHP rules to JS rule configurations.
		$rule_mappings = array(
			'required' => array( 'message' => Validation_Messages::get( 'this_field_required' ) ),
			'email'    => array( 'message' => Validation_Messages::get( 'invalid_email' ) ),
			'url'      => array( 'message' => Validation_Messages::get( 'invalid_url' ) ),
			'numeric'  => array( 'message' => Validation_Messages::get( 'invalid_number' ) ),
			'date'     => array( 'message' => Validation_Messages::get( 'invalid_date' ) ),
		);

		// Handle simple rules with fixed messages.
		foreach ( $rule_mappings as $rule_name => $config ) {
			if ( ! empty( $php_rules[ $rule_name ] ) ) {
				$js_rules[] = $this->create_js_rule( $rule_name, $config['message'] );
			}
		}

		// Handle rules with values and dynamic messages.
		if ( ! empty( $php_rules['min_length'] ) ) {
			$value      = (int) $php_rules['min_length'];
			$message    = sprintf( Validation_Messages::get( 'min_length' ), $value );
			$js_rules[] = $this->create_js_rule( 'minLength', $message, $value );
		}

		if ( ! empty( $php_rules['max_length'] ) ) {
			$value      = (int) $php_rules['max_length'];
			$message    = sprintf( Validation_Messages::get( 'max_length' ), $value );
			$js_rules[] = $this->create_js_rule( 'maxLength', $message, $value );
		}

		if ( isset( $php_rules['min'] ) ) {
			$value      = (int) $php_rules['min'];
			$message    = sprintf( Validation_Messages::get( 'minimum_value' ), $value );
			$js_rules[] = $this->create_js_rule( 'min', $message, $value );
		}

		if ( isset( $php_rules['max'] ) ) {
			$value      = (int) $php_rules['max'];
			$message    = sprintf( Validation_Messages::get( 'maximum_value' ), $value );
			$js_rules[] = $this->create_js_rule( 'max', $message, $value );
		}

		// Handle pattern validation (special case with complex logic).
		if ( ! empty( $php_rules['pattern'] ) ) {
			if ( is_array( $php_rules['pattern'] ) ) {
				$pattern = $php_rules['pattern']['pattern'] ?? '';
				$message = $php_rules['pattern']['message'] ?? Validation_Messages::get( 'invalid_pattern' );
			} else {
				$pattern = $php_rules['pattern'];
				$message = Validation_Messages::get( 'invalid_pattern' );
			}

			$js_rules[] = array(
				'name'    => 'pattern',
				'type'    => 'pattern',
				'pattern' => $pattern,
				'message' => $message,
			);
		}

		return $js_rules;
	}

	/**
	 * Create a JavaScript validation rule array
	 *
	 * @param string   $rule_name Rule name.
	 * @param string   $message   Error message.
	 * @param int|null $value     Optional value for the rule.
	 * @return array<string, mixed> JavaScript rule configuration.
	 */
	private function create_js_rule( string $rule_name, string $message, ?int $value = null ): array {
		$rule = array(
			'name'    => $rule_name,
			'type'    => $rule_name,
			'message' => $message,
		);

		if ( null !== $value ) {
			$rule['value'] = $value;
		}

		return $rule;
	}

	/**
	 * Validate form data against field configurations
	 *
	 * @param array<string, mixed> $data            Form data to validate.
	 * @param array<string, mixed> $fields          Field configurations.
	 * @param array<string>        $rendered_fields Optional array of rendered field names to validate.
	 * @return array<string, mixed> Validation result with 'valid' boolean and 'errors' array.
	 */
	public function validate_form( array $data, array $fields, array $rendered_fields = array() ): array {
		// Clear visibility cache for fresh validation.
		$this->visibility_cache = array();

		$errors   = array();
		$is_valid = true;

		// Handle conditional field validation if we have a conditional manager.
		if ( $this->conditional_manager ) {
			$conditional_validation = $this->conditional_manager->validate_conditional_fields( $data );
			if ( ! $conditional_validation['valid'] ) {
				$errors   = array_merge( $errors, $conditional_validation['errors'] );
				$is_valid = false;
			}
		}

		// Validate all configured fields that are visible (config is source of truth).
		foreach ( $fields as $field_id => $field_config ) {
			// Skip validation for hidden conditional fields.
			if ( $this->should_skip_field_validation( $field_id ) ) {
				continue;
			}

			$value = $data[ $field_id ] ?? '';

			$field_validation = $this->validate_field( $field_id, $value, $field_config, $data );
			if ( \is_wp_error( $field_validation ) ) {
				$errors[ $field_id ] = $field_validation->get_error_message();
				$is_valid            = false;
			}
		}

		// Check for configured but unused fields.
		if ( ! empty( $rendered_fields ) ) {
			$unused_fields = array_diff( array_keys( $fields ), $rendered_fields );
			if ( ! empty( $unused_fields ) ) {
				$errors['unused_fields'] = $this->generate_unused_fields_error( $unused_fields );
				$is_valid                = false;
			}
		}

		return array(
			'valid'  => $is_valid,
			'errors' => $errors,
		);
	}

	/**
	 * Generate error message for unused fields
	 *
	 * @param array<string> $unused_fields Array of unused field names.
	 * @return string Error message.
	 */
	private function generate_unused_fields_error( array $unused_fields ): string {
		$field_list = implode( ', ', $unused_fields );
		$count      = count( $unused_fields );

		if ( 1 === $count ) {
			return sprintf(
				/* translators: %s: field name */
				\__( 'Field "%s" is configured but not rendered. Either render it or remove it from the form configuration.', 'campaignbridge' ),
				$field_list
			);
		}

		return sprintf(
			/* translators: 1: number of fields, 2: field names */
			\__( '%1$d fields are configured but not rendered: %2$s. Either render them or remove them from the form configuration.', 'campaignbridge' ),
			$count,
			$field_list
		);
	}

	/**
	 * Check if field validation should be skipped (for hidden conditional fields).
	 *
	 * Uses caching for performance during batch validation.
	 *
	 * @param string $field_id Field ID to check.
	 * @return bool True if validation should be skipped.
	 */
	private function should_skip_field_validation( string $field_id ): bool {
		if ( ! $this->conditional_manager ) {
			return false;
		}

		// Use cache for repeated checks during validation.
		if ( ! isset( $this->visibility_cache[ $field_id ] ) ) {
			$this->visibility_cache[ $field_id ] = $this->conditional_manager->should_show_field( $field_id );
		}

		return ! $this->visibility_cache[ $field_id ];
	}

	/**
	 * Validate a single field
	 *
	 * @param string               $field_id    Field ID.
	 * @param mixed                $value       Field value.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param array<string, mixed> $form_data    Optional form data for context.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_field( string $field_id, $value, array $field_config, array $form_data = array() ): bool|\WP_Error {
		$field_type       = $field_config['type'] ?? 'text';
		$validation_rules = $field_config['validation'] ?? array();

		// For encrypted fields, skip validation entirely since they handle sensitive data
		// and validation rules may not apply to encrypted values.
		if ( 'encrypted' === $field_type ) {
			return true;
		}

		// Type-specific validation.
		$type_validation = $this->validate_field_type( $value, $field_type, $field_config, $field_id );
		if ( is_wp_error( $type_validation ) ) {
			return $type_validation;
		}

		// Custom validation rules.
		foreach ( $validation_rules as $rule => $rule_config ) {
			$rule_validation = $this->validate_rule( $rule, $value, $rule_config, $field_config['label'] ?? '' );
			if ( is_wp_error( $rule_validation ) ) {
				return $rule_validation;
			}
		}

		return true;
	}

	/**
	 * Validate field type
	 *
	 * @param mixed                $value       Field value.
	 * @param string               $field_type  Field type.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param string               $field_id    Field ID.
	 * @return bool|\WP_Error
	 */
	private function validate_field_type( $value, string $field_type, array $field_config, string $field_id ): bool|\WP_Error {
		// Required field validation - check conditional requirements.
		$is_required = $field_config['required'] ?? false;
		if ( $this->conditional_manager ) {
			$is_required = $this->conditional_manager->should_require_field( $field_id );
		}

		if ( $is_required && $this->is_empty_value( $value ) ) {
			return new \WP_Error(
				'field_required',
				sprintf(
					Validation_Messages::get( 'field_required' ),
					$field_config['label'] ?? 'This field'
				)
			);
		}

		// Skip further validation if empty and not required.
		$is_required = $field_config['required'] ?? false;
		if ( $this->conditional_manager ) {
			$is_required = $this->conditional_manager->should_require_field( $field_id );
		}
		if ( $this->is_empty_value( $value ) && ! $is_required ) {
			return true;
		}

		// Type-specific validation.
		switch ( $field_type ) {
			case 'email':
				if ( ! $this->is_valid_email( $value ) ) {
					return new \WP_Error(
						'invalid_email',
						Validation_Messages::get( 'invalid_email' )
					);
				}
				break;

			case 'url':
				if ( ! $this->is_valid_url( $value ) ) {
					return new \WP_Error(
						'invalid_url',
						Validation_Messages::get( 'invalid_url' )
					);
				}
				break;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return new \WP_Error(
						'invalid_number',
						Validation_Messages::get( 'invalid_number' )
					);
				}

				$value = floatval( $value );

				// Min/Max validation for numbers.
				if ( isset( $field_config['min'] ) && $value < $field_config['min'] ) {
					return new \WP_Error(
						'number_too_small',
						sprintf( Validation_Messages::get( 'number_too_small' ), $field_config['min'] )
					);
				}

				if ( isset( $field_config['max'] ) && $value > $field_config['max'] ) {
					return new \WP_Error(
						'number_too_large',
						sprintf( Validation_Messages::get( 'number_too_large' ), $field_config['max'] )
					);
				}
				break;

			case 'date':
				if ( ! $this->is_valid_date( $value ) ) {
					return new \WP_Error(
						'invalid_date',
						Validation_Messages::get( 'invalid_date' )
					);
				}
				break;

			case 'file':
				// File validation for processed upload data.
				if ( is_array( $value ) ) {
					$required_keys = array( 'file', 'url', 'filename' );
					foreach ( $required_keys as $key ) {
						if ( ! isset( $value[ $key ] ) ) {
							return new \WP_Error(
								'invalid_file_data',
								Validation_Messages::get( 'invalid_file_data' )
							);
						}
					}

					// Validate file actually exists.
					if ( ! file_exists( $value['file'] ) ) {
						return new \WP_Error(
							'file_not_found',
							Validation_Messages::get( 'file_not_found' )
						);
					}

					// Validate file size if specified.
					if ( isset( $field_config['max_size'] ) ) {
						$file_size = filesize( $value['file'] );
						if ( $file_size > $field_config['max_size'] ) {
							return new \WP_Error(
								'file_too_large',
								sprintf(
									Validation_Messages::get( 'file_too_large' ),
									size_format( $field_config['max_size'] )
								)
							);
						}
					}
				} elseif ( is_string( $value ) && ! empty( $value ) ) {
					// Allow string values (URLs or attachment IDs) for existing files.
					break;
				} elseif ( $field_config['required'] ?? false ) {
					return new \WP_Error(
						'file_required',
						Validation_Messages::get( 'file_required' )
					);
				}
				break;
		}

		return true;
	}

	/**
	 * Register default validation rules
	 */
	private function register_default_rules(): void {
		$this->validation_rules = array(
			'email'      => $this->create_simple_validator(
				fn( $value ) => $this->is_valid_email( $value ),
				'invalid_email',
				Validation_Messages::get( 'invalid_email' )
			),

			'url'        => $this->create_simple_validator(
				fn( $value ) => $this->is_valid_url( $value ),
				'invalid_url',
				Validation_Messages::get( 'invalid_url' )
			),

			'min_length' => $this->create_length_validator(
				fn( $value, $rule_config ) => strlen( $value ) < $rule_config,
				'min_length',
				Validation_Messages::get( 'min_length' )
			),

			'max_length' => $this->create_length_validator(
				fn( $value, $rule_config ) => strlen( $value ) > $rule_config,
				'max_length',
				Validation_Messages::get( 'max_length' )
			),

			'pattern'    => function ( $value, $rule_config, $field_label ) {
				// Handle both string and array formats for backward compatibility.
				if ( is_array( $rule_config ) ) {
					$pattern = $rule_config['pattern'] ?? '';
					$message = $rule_config['message'] ?? Validation_Messages::get( 'invalid_pattern' );
				} else {
					$pattern = $rule_config;
					$message = Validation_Messages::get( 'invalid_pattern' );
				}

				if ( ! preg_match( $pattern, $value ) ) {
					return new \WP_Error(
						'invalid_pattern',
						$message
					);
				}
				return true;
			},

			'numeric'    => $this->create_simple_validator(
				fn( $value ) => is_numeric( $value ),
				'not_numeric',
				Validation_Messages::get( 'not_numeric' )
			),

			'min'        => $this->create_numeric_validator(
				fn( $value, $rule_config ) => is_numeric( $value ) && $value < $rule_config,
				'value_too_low',
				Validation_Messages::get( 'value_too_low' )
			),

			'max'        => $this->create_numeric_validator(
				fn( $value, $rule_config ) => is_numeric( $value ) && $value > $rule_config,
				'value_too_high',
				Validation_Messages::get( 'value_too_high' )
			),

			'date'       => $this->create_simple_validator(
				fn( $value ) => $this->is_valid_date( $value ),
				'invalid_date',
				Validation_Messages::get( 'invalid_date' )
			),
		);
	}

	/**
	 * Create a simple validation function
	 *
	 * @param callable $validator Validation logic function.
	 * @param string   $error_code Error code for WP_Error.
	 * @param string   $error_message Error message.
	 * @return callable Validation function.
	 */
	private function create_simple_validator( callable $validator, string $error_code, string $error_message ): callable {
		return function ( $value, $rule_config, $field_label ) use ( $validator, $error_code, $error_message ) {
			if ( ! $validator( $value ) ) {
				return new \WP_Error( $error_code, $error_message );
			}
			return true;
		};
	}

	/**
	 * Create a length validation function
	 *
	 * @param callable $validator Validation logic function.
	 * @param string   $error_code Error code for WP_Error.
	 * @param string   $error_message Error message template.
	 * @return callable Validation function.
	 */
	private function create_length_validator( callable $validator, string $error_code, string $error_message ): callable {
		return function ( $value, $rule_config, $field_label ) use ( $validator, $error_code, $error_message ) {
			if ( $validator( $value, $rule_config ) ) {
				return new \WP_Error( $error_code, sprintf( $error_message, $rule_config ) );
			}
			return true;
		};
	}

	/**
	 * Create a numeric validation function
	 *
	 * @param callable $validator Validation logic function.
	 * @param string   $error_code Error code for WP_Error.
	 * @param string   $error_message Error message template.
	 * @return callable Validation function.
	 */
	private function create_numeric_validator( callable $validator, string $error_code, string $error_message ): callable {
		return function ( $value, $rule_config, $field_label ) use ( $validator, $error_code, $error_message ) {
			if ( $validator( $value, $rule_config ) ) {
				return new \WP_Error( $error_code, sprintf( $error_message, $rule_config ) );
			}
			return true;
		};
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
	 * Validate email address
	 *
	 * Centralized email validation logic to eliminate duplication.
	 *
	 * @param string $email Email address to validate.
	 * @return bool True if valid email, false otherwise.
	 */
	private function is_valid_email( string $email ): bool {
		return (bool) is_email( $email );
	}

	/**
	 * Validate URL
	 *
	 * Centralized URL validation logic to eliminate duplication.
	 *
	 * @param string $url URL to validate.
	 * @return bool True if valid URL, false otherwise.
	 */
	private function is_valid_url( string $url ): bool {
		return (bool) filter_var( $url, FILTER_VALIDATE_URL );
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
	 * @param mixed                $value       Raw value.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @return mixed Sanitized value.
	 */
	public function sanitize_value( $value, array $field_config ) {
		return Field_Sanitizer::sanitize( $value, $field_config );
	}
}
