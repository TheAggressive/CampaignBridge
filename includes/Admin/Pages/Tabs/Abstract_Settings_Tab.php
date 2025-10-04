<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Abstract Settings Tab Base Class for CampaignBridge Admin Interface.
 *
 * Abstract base class for settings tab implementations providing
 * consistent interface, shared functionality, and standardized patterns
 * for creating admin settings tabs with proper validation and rendering.
 *
 * @package CampaignBridge\Admin\Pages
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages\Tabs;

use CampaignBridge\Admin\Pages\Settings_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for settings tabs.
 *
 * Provides common functionality, interface contracts, and standardized patterns
 * for all settings tab implementations. Ensures consistent behavior across
 * different settings tabs with proper validation, rendering, and state management.
 */
abstract class Abstract_Settings_Tab {
	/**
	 * Get the tab slug (used as identifier and URL parameter).
	 *
	 * @since 0.1.0
	 * @return string The tab slug.
	 */
	abstract public static function get_slug(): string;

	/**
	 * Get the tab label (display name).
	 *
	 * @since 0.1.0
	 * @return string The tab label.
	 */
	abstract public static function get_label(): string;

	/**
	 * Get the tab description.
	 *
	 * @since 0.1.0
	 * @return string The tab description.
	 */
	abstract public static function get_description(): string;

	/**
	 * Register settings sections and fields for this tab.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	abstract public static function register_settings(): void;

	/**
	 * Render the tab content.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	abstract public static function render(): void;

	/**
	 * Get the fields that belong to this tab.
	 *
	 * @since 0.1.0
	 * @return array Array of field names.
	 */
	abstract public static function get_tab_fields(): array;

	/**
	 * Validate settings for this tab.
	 *
	 * @since 0.1.0
	 * @param array $settings Settings to validate.
	 * @return array Validation errors or empty array if valid.
	 */
	public static function validate_settings( array $settings ): array {
		$errors = array();

		foreach ( static::get_tab_fields() as $field_name ) {
			$field_errors = static::validate_field( $field_name, $settings[ $field_name ] ?? null );
			$errors = array_merge( $errors, $field_errors );
		}

		return $errors;
	}

	/**
	 * Validate a specific field.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name to validate.
	 * @param mixed  $value      Field value to validate.
	 * @return array Array of validation errors for this field.
	 */
	protected static function validate_field( string $field_name, $value ): array {
		$errors = array();

		// Get field configuration for validation rules
		$field_config = static::get_field_config( $field_name );

		// Check required fields
		if ( ! empty( $field_config['required'] ) && empty( $value ) ) {
			$errors[ $field_name ] = sprintf(
				/* translators: %s: Field label */
				__( '%s is required.', 'campaignbridge' ),
				$field_config['label'] ?? ucfirst( str_replace( '_', ' ', $field_name ) )
			);
			return $errors; // Don't continue validation if required field is empty
		}

		// Skip other validations if field is empty and not required
		if ( empty( $value ) && empty( $field_config['required'] ) ) {
			return $errors;
		}

		// Type-specific validation
		switch ( $field_config['type'] ?? 'text' ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					$errors[ $field_name ] = __( 'Please enter a valid email address.', 'campaignbridge' );
				}
				break;

			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					$errors[ $field_name ] = __( 'Please enter a valid URL.', 'campaignbridge' );
				}
				break;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					$errors[ $field_name ] = __( 'Please enter a valid number.', 'campaignbridge' );
				} elseif ( isset( $field_config['min'] ) && $value < $field_config['min'] ) {
					$errors[ $field_name ] = sprintf(
						/* translators: %s: Minimum value */
						__( 'Value must be at least %s.', 'campaignbridge' ),
						$field_config['min']
					);
				} elseif ( isset( $field_config['max'] ) && $value > $field_config['max'] ) {
					$errors[ $field_name ] = sprintf(
						/* translators: %s: Maximum value */
						__( 'Value must be no more than %s.', 'campaignbridge' ),
						$field_config['max']
					);
				}
				break;

			case 'text':
			default:
				// Length validation for text fields
				if ( isset( $field_config['max_length'] ) && strlen( (string) $value ) > $field_config['max_length'] ) {
					$errors[ $field_name ] = sprintf(
						/* translators: %s: Maximum length */
						__( 'Value must be no more than %s characters.', 'campaignbridge' ),
						$field_config['max_length']
					);
				}
				break;
		}

		// Custom validation callback
		if ( isset( $field_config['validate_callback'] ) && is_callable( $field_config['validate_callback'] ) ) {
			$custom_errors = call_user_func( $field_config['validate_callback'], $value, $field_name );
			if ( is_array( $custom_errors ) ) {
				$errors = array_merge( $errors, $custom_errors );
			} elseif ( is_string( $custom_errors ) ) {
				$errors[ $field_name ] = $custom_errors;
			}
		}

		return $errors;
	}

	/**
	 * Get field configuration for validation rules.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @return array Field configuration array.
	 */
	protected static function get_field_config( string $field_name ): array {
		// This should be overridden by child classes to provide field-specific configuration
		return array(
			'label' => ucfirst( str_replace( '_', ' ', $field_name ) ),
			'type' => 'text',
			'required' => false,
		);
	}

	/**
	 * Sanitize settings for this tab.
	 *
	 * @since 0.1.0
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( array $settings ): array {
		$sanitized = array();

		foreach ( static::get_tab_fields() as $field_name ) {
			$value = $settings[ $field_name ] ?? '';

			// Use field-specific sanitization if configured
			$config = static::get_field_config( $field_name );

			if ( isset( $config['sanitize_callback'] ) && is_callable( $config['sanitize_callback'] ) ) {
				$sanitized[ $field_name ] = call_user_func( $config['sanitize_callback'], $value );
			} else {
				$sanitized[ $field_name ] = self::sanitize_field_value( $value, $config['type'] ?? 'text' );
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize a field value based on its type.
	 *
	 * @since 0.1.0
	 * @param mixed  $value Field value to sanitize.
	 * @param string $type  Field type (text, email, url, number, etc.).
	 * @return mixed Sanitized value.
	 */
	protected static function sanitize_field_value( $value, string $type = 'text' ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = (string) $value;

		switch ( $type ) {
			case 'email':
				return sanitize_email( $value );

			case 'url':
				return esc_url_raw( $value );

			case 'number':
				return filter_var( $value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Check if this tab is currently active.
	 *
	 * @since 0.1.0
	 * @return bool True if this tab is active, false otherwise.
	 */
	public static function is_active(): bool {
		return Settings_Tab_Manager::get_current_tab() === static::get_slug();
	}

	/**
	 * Get the URL for this tab.
	 *
	 * @since 0.1.0
	 * @return string The tab URL.
	 */
	public static function get_url(): string {
		return Settings_Tab_Manager::get_tab_url( static::get_slug() );
	}

	/**
	 * Render a field with proper error handling and accessibility.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param string $field_type Field type (text, email, select, etc.).
	 * @param array  $attributes Field attributes.
	 * @return void
	 */
	protected static function render_field( string $field_name, string $field_type = 'text', array $attributes = array() ): void {
		$settings = \CampaignBridge\Admin\Pages\Admin::get_decrypted_settings();
		$value    = $settings[ $field_name ] ?? '';

		$default_attributes = array(
			'type'        => $field_type,
			'name'        => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'id'          => Settings_Manager::get_option_name() . '_' . $field_name,
			'value'       => $value,
			'class'       => 'regular-text campaignbridge-field',
			'aria-describedby' => isset( $attributes['description'] ) ? Settings_Manager::get_option_name() . '_' . $field_name . '_description' : '',
		);

		$attributes = array_merge( $default_attributes, $attributes );

		// Build attributes string with proper escaping and filtering
		$attr_string = self::build_attributes_string( $attributes );

		printf(
			'<input%s />',
			$attr_string
		);

		// Add field description if provided
		if ( isset( $attributes['description'] ) ) {
			printf(
				'<p id="%s" class="description">%s</p>',
				esc_attr( Settings_Manager::get_option_name() . '_' . $field_name . '_description' ),
				esc_html( $attributes['description'] )
			);
		}
	}

	/**
	 * Render a select field with proper accessibility.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $options    Select options.
	 * @param array  $attributes Field attributes.
	 * @return void
	 */
	protected static function render_select_field( string $field_name, array $options, array $attributes = array() ): void {
		$settings = \CampaignBridge\Admin\Pages\Admin::get_decrypted_settings();
		$value    = $settings[ $field_name ] ?? '';

		$default_attributes = array(
			'name'        => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'id'          => Settings_Manager::get_option_name() . '_' . $field_name,
			'class'       => 'regular-text campaignbridge-field campaignbridge-select',
			'aria-describedby' => isset( $attributes['description'] ) ? Settings_Manager::get_option_name() . '_' . $field_name . '_description' : '',
		);

		$attributes = array_merge( $default_attributes, $attributes );

		// Build attributes string
		$attr_string = self::build_attributes_string( $attributes );

		printf( '<select%s>', $attr_string );

		foreach ( $options as $option_value => $option_label ) {
			$selected = selected( $value, $option_value, false );
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $option_value ),
				$selected,
				esc_html( $option_label )
			);
		}

		echo '</select>';

		// Add field description if provided
		if ( isset( $attributes['description'] ) ) {
			printf(
				'<p id="%s" class="description">%s</p>',
				esc_attr( Settings_Manager::get_option_name() . '_' . $field_name . '_description' ),
				esc_html( $attributes['description'] )
			);
		}
	}

	/**
	 * Render a textarea field with proper accessibility.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $attributes Field attributes.
	 * @return void
	 */
	protected static function render_textarea_field( string $field_name, array $attributes = array() ): void {
		$settings = \CampaignBridge\Admin\Pages\Admin::get_decrypted_settings();
		$value    = $settings[ $field_name ] ?? '';

		$default_attributes = array(
			'name'        => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'id'          => Settings_Manager::get_option_name() . '_' . $field_name,
			'rows'        => 4,
			'cols'        => 50,
			'class'       => 'large-text campaignbridge-field campaignbridge-textarea',
			'aria-describedby' => isset( $attributes['description'] ) ? Settings_Manager::get_option_name() . '_' . $field_name . '_description' : '',
		);

		$attributes = array_merge( $default_attributes, $attributes );

		// Build attributes string (only include rows and cols for textarea)
		$textarea_attributes = array_intersect_key( $attributes, array_flip( array( 'rows', 'cols' ) ) );
		$attr_string = self::build_attributes_string( $textarea_attributes );

		printf( '<textarea%s>%s</textarea>', $attr_string, esc_textarea( $value ) );

		// Add field description if provided
		if ( isset( $attributes['description'] ) ) {
			printf(
				'<p id="%s" class="description">%s</p>',
				esc_attr( Settings_Manager::get_option_name() . '_' . $field_name . '_description' ),
				esc_html( $attributes['description'] )
			);
		}
	}

	/**
	 * Render a checkbox field with proper accessibility and labeling.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $attributes Field attributes.
	 * @return void
	 */
	protected static function render_checkbox_field( string $field_name, array $attributes = array() ): void {
		$settings = \CampaignBridge\Admin\Pages\Admin::get_decrypted_settings();
		$value    = $settings[ $field_name ] ?? '';

		$default_attributes = array(
			'name'  => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'id'    => Settings_Manager::get_option_name() . '_' . $field_name,
			'value' => '1',
			'class' => 'campaignbridge-field campaignbridge-checkbox',
			'type'  => 'checkbox',
			'aria-describedby' => isset( $attributes['description'] ) ? Settings_Manager::get_option_name() . '_' . $field_name . '_description' : '',
		);

		$attributes = array_merge( $default_attributes, $attributes );

		$checked = checked( $value, '1', false );

		// Build attributes string
		$attr_string = self::build_attributes_string( $attributes );

		printf( '<input%s%s />', $attr_string, $checked );

		// Add field description if provided (with proper label association)
		if ( isset( $attributes['description'] ) ) {
			printf(
				' <label for="%s" class="campaignbridge-checkbox-label">%s</label>',
				esc_attr( $attributes['id'] ),
				esc_html( $attributes['description'] )
			);
		}
	}

	/**
	 * Display validation errors for a specific field using WordPress Settings API.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name to check for errors.
	 * @return void
	 */
	protected static function display_field_errors( string $field_name ): void {
		// WordPress Settings API handles error display automatically
		// Individual field errors are shown by the Settings API
		// This method is kept for backward compatibility but does nothing
	}

	/**
	 * Render a field group with proper structure and accessibility.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param string $field_type Field type.
	 * @param array  $attributes Field attributes.
	 * @return void
	 */
	protected static function render_field_group( string $field_name, string $field_type = 'text', array $attributes = array() ): void {
		// WordPress Settings API handles error styling automatically
		$has_error = false;
		$field_classes = self::get_field_css_classes( $field_type, $has_error );

		printf(
			'<div class="campaignbridge-field-group %s%s">',
			implode( ' ', $field_classes ),
			$has_error ? ' campaignbridge-has-error' : ''
		);

		// Render the field based on type
		switch ( $field_type ) {
			case 'select':
				$options = $attributes['options'] ?? array();
				unset( $attributes['options'] );
				self::render_select_field( $field_name, $options, $attributes );
				break;

			case 'textarea':
				self::render_textarea_field( $field_name, $attributes );
				break;

			case 'checkbox':
				self::render_checkbox_field( $field_name, $attributes );
				break;

			case 'radio':
				$options = $attributes['options'] ?? array();
				unset( $attributes['options'] );
				self::render_radio_field( $field_name, $options, $attributes );
				break;

			case 'number':
				self::render_number_field( $field_name, $attributes );
				break;

			case 'url':
				self::render_url_field( $field_name, $attributes );
				break;

			default:
				self::render_field( $field_name, $field_type, $attributes );
				break;
		}

		echo '</div>';
	}

	/**
	 * Get standardized CSS classes for field types.
	 *
	 * @since 0.1.0
	 * @param string $field_type Field type.
	 * @param bool   $has_error  Whether field has validation error.
	 * @return array Array of CSS classes.
	 */
	private static function get_field_css_classes( string $field_type, bool $has_error = false ): array {
		$base_classes = array( 'campaignbridge-field-wrapper' );

		switch ( $field_type ) {
			case 'textarea':
				$base_classes[] = 'campaignbridge-textarea-wrapper';
				break;
			case 'select':
				$base_classes[] = 'campaignbridge-select-wrapper';
				break;
			case 'checkbox':
				$base_classes[] = 'campaignbridge-checkbox-wrapper';
				break;
			default:
				$base_classes[] = 'campaignbridge-input-wrapper';
				break;
		}

		if ( $has_error ) {
			$base_classes[] = 'campaignbridge-field-error';
		}

		return $base_classes;
	}

	/**
	 * Render a radio button field.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $options    Radio options.
	 * @param array  $attributes Field attributes.
	 * @return void
	 */
	protected static function render_radio_field( string $field_name, array $options, array $attributes = array() ): void {
		$settings = \CampaignBridge\Admin\Pages\Admin::get_decrypted_settings();
		$value    = $settings[ $field_name ] ?? '';

		$base_id = Settings_Manager::get_option_name() . '_' . $field_name;

		echo '<fieldset class="campaignbridge-radio-fieldset">';
		echo '<legend class="campaignbridge-radio-legend">' . esc_html( $attributes['label'] ?? '' ) . '</legend>';

		foreach ( $options as $option_value => $option_label ) {
			$option_id = $base_id . '_' . sanitize_key( $option_value );
			$checked = checked( $value, $option_value, false );

			printf(
				'<label for="%s" class="campaignbridge-radio-label">
					<input type="radio" name="%s" id="%s" value="%s" class="campaignbridge-field campaignbridge-radio"%s />
					%s
				</label>',
				esc_attr( $option_id ),
				esc_attr( Settings_Manager::get_option_name() . '[' . $field_name . ']' ),
				esc_attr( $option_id ),
				esc_attr( $option_value ),
				$checked,
				esc_html( $option_label )
			);
		}

		echo '</fieldset>';

		// Add field description if provided
		if ( isset( $attributes['description'] ) ) {
			printf(
				'<p id="%s_description" class="description">%s</p>',
				esc_attr( $base_id ),
				esc_html( $attributes['description'] )
			);
		}
	}

	/**
	 * Render a number field with proper validation.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $attributes Field attributes.
	 * @return void
	 */
	protected static function render_number_field( string $field_name, array $attributes = array() ): void {
		$attributes['type'] = 'number';

		// Add number-specific attributes
		if ( ! isset( $attributes['step'] ) ) {
			$attributes['step'] = 'any';
		}

		self::render_field( $field_name, 'number', $attributes );
	}

	/**
	 * Render a URL field with proper validation.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $attributes Field attributes.
	 * @return void
	 */
	protected static function render_url_field( string $field_name, array $attributes = array() ): void {
		$attributes['type'] = 'url';
		$attributes['placeholder'] = $attributes['placeholder'] ?? 'https://example.com';

		self::render_field( $field_name, 'url', $attributes );
	}

	/**
	 * Build HTML attributes string with proper escaping and filtering.
	 *
	 * @since 0.1.0
	 * @param array $attributes Array of attributes.
	 * @return string Formatted attributes string.
	 */
	private static function build_attributes_string( array $attributes ): string {
		$attr_parts = array();

		foreach ( $attributes as $key => $value ) {
			// Skip empty values for certain attributes
			if ( in_array( $key, array( 'value', 'placeholder' ) ) && empty( $value ) ) {
				continue;
			}

			// Handle boolean attributes
			if ( in_array( $key, array( 'required', 'disabled', 'readonly', 'checked' ) ) ) {
				if ( $value ) {
					$attr_parts[] = esc_attr( $key );
				}
			} else {
				$attr_parts[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
			}
		}

		return implode( ' ', $attr_parts ) ? ' ' . implode( ' ', $attr_parts ) : '';
	}
}
