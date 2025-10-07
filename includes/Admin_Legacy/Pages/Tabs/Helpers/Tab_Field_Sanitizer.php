<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Tab Field Sanitizer for CampaignBridge Settings.
 *
 * Handles field sanitization logic for settings tabs with proper data cleaning
 * and type conversion for different field types and security requirements.
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
 * Field sanitization helper class.
 *
 * Provides sanitization methods for different field types and data cleaning
 * used in settings tabs with proper security considerations.
 */
class Tab_Field_Sanitizer {
	/**
	 * Sanitize field value based on type.
	 *
	 * @since 0.1.0
	 * @param mixed  $value     Raw field value.
	 * @param string $field_type Field type (text, email, url, etc.).
	 * @return mixed Sanitized value.
	 */
	public static function sanitize_field_value( $value, string $field_type = 'text' ) {
		switch ( $field_type ) {
			case 'email':
				return self::sanitize_email( $value );
			case 'url':
				return self::sanitize_url( $value );
			case 'textarea':
				return self::sanitize_textarea( $value );
			case 'number':
				return self::sanitize_number( $value );
			case 'checkbox':
				return self::sanitize_checkbox( $value );
			case 'select':
				return self::sanitize_select( $value );
			case 'text':
			default:
				return self::sanitize_text( $value );
		}
	}

	/**
	 * Sanitize text field.
	 *
	 * @since 0.1.0
	 * @param mixed $value Raw value.
	 * @return string Sanitized text.
	 */
	public static function sanitize_text( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize email field.
	 *
	 * @since 0.1.0
	 * @param mixed $value Raw value.
	 * @return string Sanitized email.
	 */
	public static function sanitize_email( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return sanitize_email( $value );
	}

	/**
	 * Sanitize URL field.
	 *
	 * @since 0.1.0
	 * @param mixed $value Raw value.
	 * @return string Sanitized URL.
	 */
	public static function sanitize_url( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return esc_url_raw( $value );
	}

	/**
	 * Sanitize textarea field.
	 *
	 * @since 0.1.0
	 * @param mixed $value Raw value.
	 * @return string Sanitized textarea content.
	 */
	public static function sanitize_textarea( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return sanitize_textarea_field( $value );
	}

	/**
	 * Sanitize number field.
	 *
	 * @since 0.1.0
	 * @param mixed $value Raw value.
	 * @return float|int Sanitized number.
	 */
	public static function sanitize_number( $value ) {
		if ( is_numeric( $value ) ) {
			$value = (float) $value;
			// Return as int if it's a whole number.
			return ( $value == (int) $value ) ? (int) $value : $value; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		}
		return 0;
	}

	/**
	 * Sanitize checkbox field.
	 *
	 * @since 0.1.0
	 * @param mixed $value Raw value.
	 * @return bool Sanitized boolean.
	 */
	public static function sanitize_checkbox( $value ): bool {
		return wp_validate_boolean( $value );
	}

	/**
	 * Sanitize select field.
	 *
	 * @since 0.1.0
	 * @param mixed $value Raw value.
	 * @return string Sanitized select value.
	 */
	public static function sanitize_select( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return sanitize_key( $value );
	}

	/**
	 * Sanitize multiple settings at once.
	 *
	 * @since 0.1.0
	 * @param array $settings    Raw settings array.
	 * @param array $field_types Array of field names => types.
	 * @return array Sanitized settings array.
	 */
	public static function sanitize_settings( array $settings, array $field_types ): array {
		$sanitized = array();

		foreach ( $field_types as $field_name => $field_type ) {
			$value = $settings[ $field_name ] ?? '';
			$sanitized[ $field_name ] = self::sanitize_field_value( $value, $field_type );
		}

		return $sanitized;
	}
}
