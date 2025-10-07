<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Tab Field Renderer for CampaignBridge Settings.
 *
 * Handles field rendering logic for settings tabs with proper HTML generation
 * and attribute handling for different field types and configurations.
 *
 * @package CampaignBridge\Admin\Pages\Tabs\Helpers
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages\Tabs\Helpers;

use CampaignBridge\Admin\Pages\Settings_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field rendering helper class.
 *
 * Provides rendering methods for different field types used in settings tabs
 * with proper HTML generation, attribute handling, and accessibility features.
 */
class Tab_Field_Renderer {
	/**
	 * Render a field based on type.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param string $field_type Field type (text, select, etc.).
	 * @param array  $attributes Additional attributes.
	 * @return void
	 */
	public static function render_field( string $field_name, string $field_type = 'text', array $attributes = array() ): void {
		switch ( $field_type ) {
			case 'select':
				self::render_select_field( $field_name, $attributes );
				break;
			case 'textarea':
				self::render_textarea_field( $field_name, $attributes );
				break;
			case 'checkbox':
				self::render_checkbox_field( $field_name, $attributes );
				break;
			case 'radio':
				self::render_radio_field( $field_name, $attributes['options'] ?? array(), $attributes );
				break;
			case 'number':
				self::render_number_field( $field_name, $attributes );
				break;
			case 'url':
				self::render_url_field( $field_name, $attributes );
				break;
			case 'text':
			default:
				self::render_text_field( $field_name, $attributes );
				break;
		}
	}

	/**
	 * Render text field.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $attributes Additional attributes.
	 * @return void
	 */
	public static function render_text_field( string $field_name, array $attributes = array() ): void {
		$value = $attributes['value'] ?? '';
		unset( $attributes['value'] );

		$attributes = array_merge( array(
			'type'  => 'text',
			'id'    => Settings_Manager::get_option_name() . '_' . $field_name,
			'name'  => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'value' => $value,
			'class' => 'regular-text campaignbridge-field',
		), $attributes );

		$attributes_string = self::build_attributes_string( $attributes );

		echo '<input ' . $attributes_string . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render select field.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $attributes Additional attributes including 'options'.
	 * @return void
	 */
	public static function render_select_field( string $field_name, array $attributes = array() ): void {
		$options = $attributes['options'] ?? array();
		$value   = $attributes['value'] ?? '';
		unset( $attributes['options'], $attributes['value'] );

		$attributes = array_merge( array(
			'id'    => Settings_Manager::get_option_name() . '_' . $field_name,
			'name'  => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'class' => 'regular-text campaignbridge-field campaignbridge-select',
		), $attributes );

		$attributes_string = self::build_attributes_string( $attributes );

		echo '<select ' . $attributes_string . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		foreach ( $options as $option_value => $option_label ) {
			$selected = selected( $value, $option_value, false );
			echo '<option value="' . esc_attr( $option_value ) . '" ' . $selected . '>' . esc_html( $option_label ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</select>';
	}

	/**
	 * Render textarea field.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $attributes Additional attributes.
	 * @return void
	 */
	public static function render_textarea_field( string $field_name, array $attributes = array() ): void {
		$value = $attributes['value'] ?? '';
		unset( $attributes['value'] );

		$attributes = array_merge( array(
			'id'    => Settings_Manager::get_option_name() . '_' . $field_name,
			'name'  => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'class' => 'regular-text campaignbridge-field',
			'rows'  => 5,
			'cols'  => 50,
		), $attributes );

		$attributes_string = self::build_attributes_string( $attributes );

		echo '<textarea ' . $attributes_string . '>' . esc_textarea( $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render checkbox field.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $attributes Additional attributes.
	 * @return void
	 */
	public static function render_checkbox_field( string $field_name, array $attributes = array() ): void {
		$value = $attributes['value'] ?? '';
		$checked = ! empty( $value );
		unset( $attributes['value'] );

		$attributes = array_merge( array(
			'type'  => 'checkbox',
			'id'    => Settings_Manager::get_option_name() . '_' . $field_name,
			'name'  => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'class' => 'campaignbridge-field',
			'value' => '1',
		), $attributes );

		if ( $checked ) {
			$attributes['checked'] = 'checked';
		}

		$attributes_string = self::build_attributes_string( $attributes );

		echo '<input ' . $attributes_string . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Add hidden field for unchecked state.
		echo '<input type="hidden" name="' . esc_attr( Settings_Manager::get_option_name() . '[' . $field_name . ']' ) . '" value="0" />';
	}

	/**
	 * Render radio field.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $options    Radio options.
	 * @param array  $attributes Additional attributes.
	 * @return void
	 */
	public static function render_radio_field( string $field_name, array $options, array $attributes = array() ): void {
		$value = $attributes['value'] ?? '';
		unset( $attributes['value'] );

		$base_attributes = array(
			'type'  => 'radio',
			'name'  => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'class' => 'campaignbridge-field',
		);

		foreach ( $options as $option_value => $option_label ) {
			$radio_attributes = array_merge( $base_attributes, array(
				'id'    => Settings_Manager::get_option_name() . '_' . $field_name . '_' . $option_value,
				'value' => $option_value,
			), $attributes );

			if ( $value === $option_value ) {
				$radio_attributes['checked'] = 'checked';
			}

			$attributes_string = self::build_attributes_string( $radio_attributes );

			echo '<label>';
			echo '<input ' . $attributes_string . ' /> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo esc_html( $option_label );
			echo '</label><br />';
		}
	}

	/**
	 * Render number field.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $attributes Additional attributes.
	 * @return void
	 */
	public static function render_number_field( string $field_name, array $attributes = array() ): void {
		$value = $attributes['value'] ?? '';
		unset( $attributes['value'] );

		$attributes = array_merge( array(
			'type'  => 'number',
			'id'    => Settings_Manager::get_option_name() . '_' . $field_name,
			'name'  => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'value' => $value,
			'class' => 'regular-text campaignbridge-field',
		), $attributes );

		$attributes_string = self::build_attributes_string( $attributes );

		echo '<input ' . $attributes_string . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render URL field.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $attributes Additional attributes.
	 * @return void
	 */
	public static function render_url_field( string $field_name, array $attributes = array() ): void {
		$value = $attributes['value'] ?? '';
		unset( $attributes['value'] );

		$attributes = array_merge( array(
			'type'  => 'url',
			'id'    => Settings_Manager::get_option_name() . '_' . $field_name,
			'name'  => Settings_Manager::get_option_name() . '[' . $field_name . ']',
			'value' => $value,
			'class' => 'regular-text campaignbridge-field',
		), $attributes );

		$attributes_string = self::build_attributes_string( $attributes );

		echo '<input ' . $attributes_string . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render field group with label and description.
	 *
	 * @since 0.1.0
	 * @param string $field_name  Field name.
	 * @param string $field_type  Field type.
	 * @param array  $attributes  Additional attributes.
	 * @return void
	 */
	public static function render_field_group( string $field_name, string $field_type = 'text', array $attributes = array() ): void {
		$label       = $attributes['label'] ?? ucfirst( str_replace( '_', ' ', $field_name ) );
		$description = $attributes['description'] ?? '';
		$has_error   = $attributes['has_error'] ?? false;
		$required    = $attributes['required'] ?? false;

		$css_classes = self::get_field_css_classes( $field_type, $has_error );

		echo '<tr class="' . esc_attr( implode( ' ', $css_classes ) ) . '">';
		echo '<th scope="row">';
		echo '<label for="' . esc_attr( Settings_Manager::get_option_name() . '_' . $field_name ) . '">';
		echo esc_html( $label );
		if ( $required ) {
			echo '<span class="required">*</span>';
		}
		echo '</label>';
		echo '</th>';
		echo '<td>';

		self::render_field( $field_name, $field_type, $attributes );

		if ( ! empty( $description ) ) {
			echo '<p class="description">' . wp_kses_post( $description ) . '</p>';
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Get CSS classes for field row.
	 *
	 * @since 0.1.0
	 * @param string $field_type Field type.
	 * @param bool   $has_error  Whether field has errors.
	 * @return array Array of CSS classes.
	 */
	private static function get_field_css_classes( string $field_type, bool $has_error = false ): array {
		$classes = array( 'campaignbridge-field-row' );

		if ( $has_error ) {
			$classes[] = 'campaignbridge-field-error';
		}

		return $classes;
	}

	/**
	 * Build HTML attributes string from array.
	 *
	 * @since 0.1.0
	 * @param array $attributes Attributes array.
	 * @return string HTML attributes string.
	 */
	private static function build_attributes_string( array $attributes ): string {
		$attribute_strings = array();

		foreach ( $attributes as $name => $value ) {
			if ( is_bool( $value ) ) {
				if ( $value ) {
					$attribute_strings[] = $name;
				}
			} else {
				$attribute_strings[] = $name . '="' . esc_attr( $value ) . '"';
			}
		}

		return implode( ' ', $attribute_strings );
	}
}
