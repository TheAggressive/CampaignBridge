<?php
/**
 * Form Field Checkbox
 *
 * Handles checkbox input fields.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Field Checkbox Class
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Field_Checkbox extends Form_Field_Base {

	/**
	 * Render the input element
	 */
	public function render_input(): void {
		$value      = $this->get_value();
		$attributes = $this->render_common_attributes();
		$options    = $this->config['options'] ?? array();

		// Single checkbox.
		if ( empty( $options ) ) {
			$checked = ! empty( $value ) ? ' checked' : '';

			// Add hidden input BEFORE checkbox to ensure unchecked state, but allow checkbox to override.
			// Skip for repeater fields (they handle unchecked state differently).
			if ( ! isset( $this->config['skip_hidden_field'] ) || ! $this->config['skip_hidden_field'] ) {
				printf(
					'<input type="hidden" name="%s" value="0" />',
					esc_attr( $this->config['name'] )
				);
			}

			printf(
				'<input type="checkbox" value="1" %s%s />',
				$attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes is built by render_common_attributes() with proper escaping.
				$checked, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $checked is a safe string literal (' checked' or '').
			);

			return;
		}

		// Multiple checkboxes.
		$current_values = is_array( $value ) ? $value : ( ! empty( $value ) ? array( $value ) : array() );

		echo '<div class="campaignbridge-checkbox-group">';

		foreach ( $options as $option_value => $option_label ) {
			$checkbox_name = $this->config['name'] . '[]';
			$checkbox_id   = $this->config['id'] . '_' . sanitize_key( $option_value );
			$checked       = in_array( $option_value, $current_values, true ) ? ' checked' : '';

			printf(
				'<label for="%s" class="campaignbridge-checkbox-label">
					<input type="checkbox" id="%s" name="%s" value="%s"%s %s />
					<span class="campaignbridge-checkbox-text">%s</span>
				</label>',
				esc_attr( $checkbox_id ),
				esc_attr( $checkbox_id ),
				esc_attr( $checkbox_name ),
				esc_attr( $option_value ),
				$attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes is built by render_common_attributes() with proper escaping.
				$checked, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $checked is a safe string literal (' checked' or '').
				esc_html( $option_label )
			);
		}

		echo '</div>';
	}

	/**
	 * Render field label (override for single checkbox)
	 */
	protected function render_label(): void {
		$options = $this->config['options'] ?? array();

		// For single checkbox, don't render separate label.
		if ( empty( $options ) ) {
			return;
		}

		parent::render_label();
	}

	/**
	 * Render the field in table layout (override for single checkbox)
	 */
	public function render_table_row(): void {
		$options = $this->config['options'] ?? array();

		// For single checkbox, different layout.
		if ( empty( $options ) ) {
			$wrapper_class = $this->config['wrapper_class'] ? ' class="' . esc_attr( $this->config['wrapper_class'] ) . '"' : '';

			printf( '<tr%s>', esc_attr( $wrapper_class ) );

			echo '<th scope="row">';
			$this->render_label();
			echo '</th>';

			echo '<td>';
			echo '<fieldset>';
			echo '<legend class="screen-reader-text">' . esc_html( $this->config['label'] ) . '</legend>';
			$this->render_html( 'before' );
			$this->render_input();
			$this->render_description();
			$this->render_html( 'after' );
			echo '</fieldset>';
			echo '</td>';

			echo '</tr>';
			return;
		}

		parent::render_table_row();
	}

	/**
	 * Merge submitted value with existing value for checkbox fields
	 *
	 * Handles the special case where unchecked checkboxes are not submitted.
	 *
	 * @param mixed $submitted_value Value submitted in form (null if not submitted).
	 * @param mixed $existing_value  Existing saved value.
	 * @return mixed Merged value.
	 */
	public function merge_values( $submitted_value, $existing_value ) {
		// If checkbox was not submitted, it was unchecked.
		if ( null === $submitted_value ) {
			return false;
		}

		// Otherwise use default behavior: submitted value takes priority.
		return $submitted_value;
	}
}
