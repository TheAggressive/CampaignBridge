<?php
/**
 * Form Field Factory
 *
 * Creates form field instances based on configuration.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

	/**
	 * Form Field Factory Class
	 *
	 * @package CampaignBridge\Admin\Core\Forms
	 */
class Form_Field_Factory {

	/**
	 * Form validator instance
	 *
	 * @var Form_Validator
	 */
	private Form_Validator $validator;

	/**
	 * Constructor
	 *
	 * @param Form_Validator $validator Form validator instance.
	 */
	public function __construct( Form_Validator $validator ) {
		$this->validator = $validator;
	}

	/**
	 * Create a form field instance
	 *
	 * @param string               $field_id     Field ID.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param mixed                $value        Field value.
	 * @return Form_Field_Interface
	 */
	public function create_field( string $field_id, array $field_config, $value ): Form_Field_Interface {
		$field_type = $field_config['type'] ?? 'text';

		// Normalize field configuration.
		$field_config = $this->normalize_field_config( $field_id, $field_config, $value );

		// Create field instance based on type.
		switch ( $field_type ) {
			case 'text':
			case 'email':
			case 'url':
			case 'password':
			case 'number':
			case 'tel':
			case 'search':
			case 'date':
			case 'datetime-local':
			case 'month':
			case 'week':
			case 'time':
			case 'color':
			case 'range':
				return new Form_Field_Input( $field_config, $this->validator );

			case 'textarea':
				return new Form_Field_Textarea( $field_config, $this->validator );

			case 'select':
				return new Form_Field_Select( $field_config, $this->validator );

			case 'checkbox':
				return new Form_Field_Checkbox( $field_config, $this->validator );

			case 'radio':
				return new Form_Field_Radio( $field_config, $this->validator );

			case 'file':
				return new Form_Field_File( $field_config, $this->validator );

			case 'wysiwyg':
				return new Form_Field_Wysiwyg( $field_config, $this->validator );

			case 'switch':
				return new Form_Field_Switch( $field_config, $this->validator );
			case 'toggle':
				return new Form_Field_Switch( $field_config, $this->validator );

			case 'encrypted':
				return new Form_Field_Encrypted( $field_config, $this->validator );

			default:
				// Allow custom field types via filter.
				$custom_field = apply_filters( 'campaignbridge_form_custom_field', null, $field_type, $field_config );

				if ( $custom_field instanceof Form_Field_Interface ) {
					return $custom_field;
				}

				// Fallback to text input.
				$field_config['type'] = 'text';
				return new Form_Field_Input( $field_config, $this->validator );
		}
	}

	/**
	 * Normalize field configuration
	 *
	 * @param string               $field_id     Field ID.
	 * @param array<string, mixed> $field_config Raw field configuration.
	 * @param mixed                $value        Field value.
	 * @return array<string, mixed> Normalized configuration.
	 */
	private function normalize_field_config( string $field_id, array $field_config, $value ): array {
		// Start with defaults.
		$defaults = array(
			'id'               => $field_id,
			'name'             => $field_id,
			'type'             => 'text',
			'label'            => ucfirst( str_replace( '_', ' ', $field_id ) ),
			'value'            => $value,
			'default'          => '',
			'description'      => '',
			'placeholder'      => '',
			'required'         => false,
			'disabled'         => false,
			'readonly'         => false,
			'class'            => 'regular-text',
			'attributes'       => array(),
			'validation'       => array(),
			'options'          => array(), // For select, radio, checkbox fields.
			'rows'             => 5,  // For textarea.
			'cols'             => 50, // For textarea.
			'multiple'         => false, // For multiselect.
			'accept'           => '', // For file inputs.
			'min'              => '', // For number inputs.
			'max'              => '', // For number inputs.
			'step'             => '', // For number inputs.
			'pattern'          => '', // For input validation.
			'autocomplete'     => '', // For accessibility.
			'aria-describedby' => '', // For accessibility.
			'wrapper_class'    => '', // For styling.
			'before'           => '', // HTML before field.
			'after'            => '', // HTML after field.
		);

		// Merge with provided config.
		$config = wp_parse_args( $field_config, $defaults );

		// Add automatic validation based on field type.
		$config['validation'] = $this->add_automatic_validation( $config );

		return $config;
	}

	/**
	 * Add automatic validation rules based on field type
	 *
	 * @param array<string, mixed> $config Field configuration.
	 * @return array<string, mixed> Validation rules.
	 */
	private function add_automatic_validation( array $config ): array {
		$type       = $config['type'] ?? 'text';
		$validation = $config['validation'] ?? array();

		// Add type-specific validation if not already present.
		switch ( $type ) {
			case 'email':
				if ( ! isset( $validation['email'] ) ) {
					$validation['email'] = true;
				}
				break;

			case 'url':
				if ( ! isset( $validation['url'] ) ) {
					$validation['url'] = true;
				}
				break;

			case 'number':
				if ( ! isset( $validation['numeric'] ) ) {
					$validation['numeric'] = true;
				}
				// Add min/max validation if specified.
				if ( ! empty( $config['min'] ) && ! isset( $validation['min'] ) ) {
					$validation['min'] = $config['min'];
				}
				if ( ! empty( $config['max'] ) && ! isset( $validation['max'] ) ) {
					$validation['max'] = $config['max'];
				}
				break;

			case 'date':
				if ( ! isset( $validation['date'] ) ) {
					$validation['date'] = true;
				}
				break;
		}

		// Add pattern validation if specified.
		if ( ! empty( $config['pattern'] ) && ! isset( $validation['pattern'] ) ) {
			$validation['pattern'] = $config['pattern'];
		}

		return $validation;
	}
}
