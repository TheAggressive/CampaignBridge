<?php
/**
 * Field Sanitizer - Unified field sanitization logic
 *
 * Provides centralized sanitization for all form field types, eliminating
 * duplication across Form_Validator, Form_Security, Form_Handler, and legacy code.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Field Sanitizer Class
 *
 * Unified sanitization logic for all field types.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Field_Sanitizer {

	/**
	 * Sanitize field value based on type and configuration
	 *
	 * @param mixed                $value        Raw field value.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @return mixed Sanitized value.
	 */
	public static function sanitize( $value, array $field_config ) {
		$field_type = $field_config['type'] ?? 'text';

		switch ( $field_type ) {
			case 'email':
				return self::sanitize_email( $value );

			case 'url':
				return self::sanitize_url( $value );

			case 'number':
				return self::sanitize_number( $value, $field_config );

			case 'textarea':
			case 'wysiwyg':
				return self::sanitize_rich_text( $value );

			case 'checkbox':
			case 'switch':
				return self::sanitize_checkbox( $value );

			case 'file':
				return self::sanitize_file( $value );

			case 'encrypted':
				return self::sanitize_encrypted( $value );

			default:
				return self::sanitize_text( $value );
		}
	}

	/**
	 * Sanitize email field
	 *
	 * @param mixed $value Raw email value.
	 * @return string Sanitized email.
	 */
	public static function sanitize_email( $value ): string {
		return sanitize_email( $value );
	}

	/**
	 * Sanitize URL field
	 *
	 * @param mixed $value Raw URL value.
	 * @return string Sanitized URL.
	 */
	public static function sanitize_url( $value ): string {
		return esc_url_raw( $value );
	}

	/**
	 * Sanitize number field
	 *
	 * @param mixed                $value        Raw number value.
	 * @param array<string, mixed> $field_config Field configuration for min/max constraints.
	 * @return float|int Sanitized number.
	 */
	public static function sanitize_number( $value, array $field_config = array() ) {
		if ( ! is_numeric( $value ) ) {
			return isset( $field_config['default'] ) ? $field_config['default'] : 0;
		}

		$value = floatval( $value );

		// Apply min/max constraints if specified.
		if ( isset( $field_config['min'] ) && $value < $field_config['min'] ) {
			$value = $field_config['min'];
		}

		if ( isset( $field_config['max'] ) && $value > $field_config['max'] ) {
			$value = $field_config['max'];
		}

		return $value;
	}

	/**
	 * Sanitize rich text field (textarea/wysiwyg)
	 *
	 * @param mixed $value Raw text value.
	 * @return string Sanitized rich text.
	 */
	public static function sanitize_rich_text( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		return wp_kses_post( $value );
	}

	/**
	 * Sanitize checkbox/switch field
	 *
	 * @param mixed $value Raw checkbox value.
	 * @return int 1 for checked, 0 for unchecked.
	 */
	public static function sanitize_checkbox( $value ): int {
		return ! empty( $value ) ? 1 : 0;
	}

	/**
	 * Sanitize file field
	 *
	 * File fields are handled specially by Form_File_Uploader.
	 *
	 * @param mixed $value Raw file value.
	 * @return mixed Original value (files handled elsewhere).
	 */
	public static function sanitize_file( $value ) {
		return $value;
	}

	/**
	 * Sanitize encrypted field
	 *
	 * Encrypted fields are handled specially by Form_Field_Encrypted.
	 *
	 * @param mixed $value Raw encrypted value.
	 * @return mixed Original value (encryption handled elsewhere).
	 */
	public static function sanitize_encrypted( $value ) {
		return $value;
	}

	/**
	 * Sanitize text field (default)
	 *
	 * @param mixed $value Raw text value.
	 * @return string Sanitized text.
	 */
	public static function sanitize_text( $value ): string {
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize settings data array
	 *
	 * @param array<string, mixed> $data         Raw settings data.
	 * @param array<string, mixed> $field_types  Field type mapping (for legacy compatibility).
	 * @return array<string, mixed> Sanitized data.
	 */
	public static function sanitize_settings( array $data, array $field_types = array() ): array {
		$sanitized = array();

		foreach ( $data as $field_id => $value ) {
			$field_type = $field_types[ $field_id ] ?? 'text';

			// Create minimal field config for sanitization.
			$field_config = array( 'type' => $field_type );

			$sanitized[ $field_id ] = self::sanitize( $value, $field_config );
		}

		return $sanitized;
	}

	/**
	 * Sanitize provider settings (for Abstract_Provider compatibility)
	 *
	 * @param array<string, mixed> $settings Raw provider settings.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public static function sanitize_provider_settings( array $settings ): array {
		$sanitized = array();

		foreach ( $settings as $field_id => $field_schema ) {
			if ( ! is_array( $field_schema ) ) {
				$sanitized[ $field_id ] = self::sanitize_text( $field_schema );
				continue;
			}

			$value                  = $field_schema['value'] ?? '';
			$sanitized[ $field_id ] = self::sanitize_field_by_schema( $value, $field_schema );
		}

		return $sanitized;
	}

	/**
	 * Sanitize field by provider schema (for Abstract_Provider compatibility)
	 *
	 * @param mixed                $value        Raw field value.
	 * @param array<string, mixed> $field_schema Provider field schema.
	 * @return mixed Sanitized value.
	 */
	private static function sanitize_field_by_schema( $value, array $field_schema ) {
		$type = $field_schema['type'] ?? 'string';

		switch ( $type ) {
			case 'string':
				return self::sanitize_string_by_schema( $value, $field_schema );

			case 'boolean':
				return self::sanitize_boolean_by_schema( $value );

			case 'integer':
				return self::sanitize_integer_by_schema( $value, $field_schema );

			case 'email':
				return self::sanitize_email_by_schema( $value );

			case 'url':
				return self::sanitize_url_by_schema( $value );

			default:
				return $value;
		}
	}

	/**
	 * Sanitize string by provider schema
	 *
	 * @param mixed                $value        Raw value.
	 * @param array<string, mixed> $field_schema Field schema.
	 * @return string|null Sanitized string.
	 */
	private static function sanitize_string_by_schema( $value, array $field_schema ): ?string {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return null;
		}

		$value = (string) $value;

		// Check length constraints.
		$min_length = $field_schema['min_length'] ?? 0;
		$max_length = $field_schema['max_length'] ?? 1000;

		if ( strlen( $value ) < $min_length || strlen( $value ) > $max_length ) {
			return null;
		}

		// Apply pattern validation if specified.
		if ( isset( $field_schema['pattern'] ) ) {
			if ( ! preg_match( $field_schema['pattern'], $value ) ) {
				return null;
			}
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize boolean by provider schema
	 *
	 * @param mixed $value Raw value.
	 * @return bool|null Sanitized boolean.
	 */
	private static function sanitize_boolean_by_schema( $value ): ?bool {
		if ( ! is_bool( $value ) && ! is_string( $value ) && ! is_numeric( $value ) ) {
			return null;
		}

		return (bool) $value;
	}

	/**
	 * Sanitize integer by provider schema
	 *
	 * @param mixed                $value        Raw value.
	 * @param array<string, mixed> $field_schema Field schema.
	 * @return int|null Sanitized integer.
	 */
	private static function sanitize_integer_by_schema( $value, array $field_schema ): ?int {
		if ( ! is_numeric( $value ) ) {
			return null;
		}

		$value = (int) $value;

		// Check range constraints.
		if ( isset( $field_schema['min'] ) && $value < $field_schema['min'] ) {
			return null;
		}

		if ( isset( $field_schema['max'] ) && $value > $field_schema['max'] ) {
			return null;
		}

		return $value;
	}

	/**
	 * Sanitize email by provider schema
	 *
	 * @param mixed $value Raw value.
	 * @return string|null Sanitized email.
	 */
	private static function sanitize_email_by_schema( $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$sanitized = sanitize_email( $value );
		return ! empty( $sanitized ) ? $sanitized : null;
	}

	/**
	 * Sanitize URL by provider schema
	 *
	 * @param mixed $value Raw value.
	 * @return string|null Sanitized URL.
	 */
	private static function sanitize_url_by_schema( $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$sanitized = esc_url_raw( $value );
		return ! empty( $sanitized ) ? $sanitized : null;
	}
}
