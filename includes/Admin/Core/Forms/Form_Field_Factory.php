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
	 * Create a form field instance
	 *
	 * @param string $field_id     Field ID.
	 * @param array  $field_config Field configuration.
	 * @param mixed  $value        Field value.
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
				return new Form_Field_Input( $field_config );

			case 'textarea':
				return new Form_Field_Textarea( $field_config );

			case 'select':
				return new Form_Field_Select( $field_config );

			case 'checkbox':
				return new Form_Field_Checkbox( $field_config );

			case 'radio':
				return new Form_Field_Radio( $field_config );

			case 'file':
				return new Form_Field_File( $field_config );

			case 'wysiwyg':
				return new Form_Field_Wysiwyg( $field_config );

			case 'switch':
				return new Form_Field_Switch( $field_config );
			case 'toggle':
				return new Form_Field_Switch( $field_config );

			default:
				// Allow custom field types via filter.
				$custom_field = apply_filters( 'campaignbridge_form_custom_field', null, $field_type, $field_config );

				if ( $custom_field instanceof Form_Field_Interface ) {
					return $custom_field;
				}

				// Fallback to text input.
				$field_config['type'] = 'text';
				return new Form_Field_Input( $field_config );
		}
	}

	/**
	 * Normalize field configuration
	 *
	 * @param string $field_id     Field ID.
	 * @param array  $field_config Raw field configuration.
	 * @param mixed  $value        Field value.
	 * @return array Normalized configuration.
	 */
	private function normalize_field_config( string $field_id, array $field_config, $value ): array {
		return wp_parse_args(
			$field_config,
			array(
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
			)
		);
	}
}
