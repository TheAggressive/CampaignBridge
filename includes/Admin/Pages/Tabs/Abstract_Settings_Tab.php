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
		return array();
	}

	/**
	 * Sanitize settings for this tab.
	 *
	 * @since 0.1.0
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( array $settings ): array {
		return $settings;
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
	 * Render a field with proper error handling.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param string $field_type Field type (text, email, select, etc.).
	 * @param array  $attributes Field attributes.
	 * @return void
	 */
	protected static function render_field( string $field_name, string $field_type = 'text', array $attributes = array() ): void {
		$settings = Settings_Manager::get_settings();
		$value    = $settings[ $field_name ] ?? '';

		$default_attributes = array(
			'type'  => $field_type,
			'name'  => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'value' => $value,
			'class' => 'regular-text',
		);

		$attributes = array_merge( $default_attributes, $attributes );

		// Build attributes string
		$attr_string = '';
		foreach ( $attributes as $key => $val ) {
			if ( 'value' === $key && empty( $val ) ) {
				continue;
			}
			$attr_string .= ' ' . esc_attr( $key ) . '="' . esc_attr( $val ) . '"';
		}

		echo '<input' . $attr_string . ' />';

		// Add field description if provided
		if ( isset( $attributes['description'] ) ) {
			echo '<p class="description">' . esc_html( $attributes['description'] ) . '</p>';
		}
	}

	/**
	 * Render a select field.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $options    Select options.
	 * @param array  $attributes Field attributes.
	 * @return void
	 */
	protected static function render_select_field( string $field_name, array $options, array $attributes = array() ): void {
		$settings = Settings_Manager::get_settings();
		$value    = $settings[ $field_name ] ?? '';

		$default_attributes = array(
			'name'  => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'class' => 'regular-text',
		);

		$attributes = array_merge( $default_attributes, $attributes );

		echo '<select name="' . esc_attr( $attributes['name'] ) . '">';

		foreach ( $options as $option_value => $option_label ) {
			$selected = selected( $value, $option_value, false );
			echo '<option value="' . esc_attr( $option_value ) . '"' . $selected . '>' . esc_html( $option_label ) . '</option>';
		}

		echo '</select>';

		// Add field description if provided
		if ( isset( $attributes['description'] ) ) {
			echo '<p class="description">' . esc_html( $attributes['description'] ) . '</p>';
		}
	}

	/**
	 * Render a textarea field.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $attributes Field attributes.
	 * @return void
	 */
	protected static function render_textarea_field( string $field_name, array $attributes = array() ): void {
		$settings = Settings_Manager::get_settings();
		$value    = $settings[ $field_name ] ?? '';

		$default_attributes = array(
			'name'  => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'rows'  => 4,
			'cols'  => 50,
			'class' => 'large-text',
		);

		$attributes = array_merge( $default_attributes, $attributes );

		// Build attributes string
		$attr_string = '';
		foreach ( $attributes as $key => $val ) {
			if ( in_array( $key, array( 'rows', 'cols' ) ) ) {
				$attr_string .= ' ' . esc_attr( $key ) . '="' . esc_attr( $val ) . '"';
			}
		}

		echo '<textarea' . $attr_string . '>' . esc_textarea( $value ) . '</textarea>';

		// Add field description if provided
		if ( isset( $attributes['description'] ) ) {
			echo '<p class="description">' . esc_html( $attributes['description'] ) . '</p>';
		}
	}

	/**
	 * Render a checkbox field.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $attributes Field attributes.
	 * @return void
	 */
	protected static function render_checkbox_field( string $field_name, array $attributes = array() ): void {
		$settings = Settings_Manager::get_settings();
		$value    = $settings[ $field_name ] ?? '';

		$default_attributes = array(
			'name'  => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'value' => '1',
			'class' => '',
		);

		$attributes = array_merge( $default_attributes, $attributes );

		$checked = checked( $value, '1', false );

		echo '<input type="checkbox" name="' . esc_attr( $attributes['name'] ) . '" value="' . esc_attr( $attributes['value'] ) . '"' . $checked . ' />';

		// Add field description if provided
		if ( isset( $attributes['description'] ) ) {
			echo ' <label for="' . esc_attr( $attributes['name'] ) . '">' . esc_html( $attributes['description'] ) . '</label>';
		}
	}

	/**
	 * Display validation errors for a specific field.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name to check for errors.
	 * @return void
	 */
	protected static function display_field_errors( string $field_name ): void {
		$errors = Settings_Manager::get_validation_errors();

		if ( isset( $errors[ $field_name ] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $errors[ $field_name ] ) . '</p></div>';
		}
	}
}
