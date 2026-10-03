<?php
/**
 * Field Sanitizer
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Maps each form field type to its WordPress sanitization function.
 *
 * This is the only sanitization pass for form values. Rendered values are
 * escaped separately at output.
 */
class Field_Sanitizer {

	/**
	 * Sanitize a field value for its type.
	 *
	 * @param mixed                $value        Raw field value.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @return mixed Sanitized value.
	 */
	public static function sanitize( $value, array $field_config ) {
		switch ( $field_config['type'] ?? 'text' ) {
			case 'email':
				return sanitize_email( $value );

			case 'url':
				return esc_url_raw( $value );

			case 'number':
				return self::sanitize_number( $value, $field_config );

			case 'textarea':
			case 'wysiwyg':
				return is_string( $value ) ? wp_kses_post( $value ) : '';

			case 'checkbox':
			case 'switch':
				return ! empty( $value ) ? 1 : 0;

			case 'file':
			case 'encrypted':
				// Uploads are stored by Form_File_Uploader; encrypted values by Form_Handler.
				return $value;

			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Sanitize a number and clamp it to the field's min/max.
	 *
	 * @param mixed                $value        Raw number value.
	 * @param array<string, mixed> $field_config Field configuration for min/max constraints.
	 * @return float|int|mixed Sanitized number, or the field default when not numeric.
	 */
	private static function sanitize_number( $value, array $field_config ) {
		if ( ! is_numeric( $value ) ) {
			return $field_config['default'] ?? 0;
		}

		$value = floatval( $value );

		if ( isset( $field_config['min'] ) && $value < $field_config['min'] ) {
			$value = $field_config['min'];
		}

		if ( isset( $field_config['max'] ) && $value > $field_config['max'] ) {
			$value = $field_config['max'];
		}

		return $value;
	}
}
