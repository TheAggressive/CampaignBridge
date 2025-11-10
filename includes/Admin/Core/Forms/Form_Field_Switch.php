<?php
/**
 * Form Field Switch/Toggle
 *
 * Handles switch/toggle fields (styled checkboxes).
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Field Switch Class
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Field_Switch extends Form_Field_Base {

	/**
	 * Render the switch element
	 */
	public function render_input(): void {
		$value      = $this->get_value();
		$checked    = $value ? 'checked' : '';
		$field_id   = $this->config['id'];
		$field_name = $this->config['name'];

		$attributes = $this->render_common_attributes();

		// Switch/toggle styling.
		$switch_classes = 'campaignbridge-switch';
		if ( isset( $this->config['class'] ) ) {
			$switch_classes .= ' ' . $this->config['class'];
		}

		// Add hidden input BEFORE checkbox to ensure unchecked state, but allow checkbox to override.
		// Skip for repeater fields (they handle unchecked state differently).
		$hidden_input = '';
		if ( ! isset( $this->config['skip_hidden_field'] ) || ! $this->config['skip_hidden_field'] ) {
			$hidden_input = sprintf(
				'<input type="hidden" name="%s" value="0" />',
				esc_attr( $field_name )
			);
		}

		printf(
			'%s<div class="%s">
				<input type="checkbox" id="%s" name="%s" value="1" %s %s />
				<label for="%s" class="campaignbridge-switch__label">
					<span class="campaignbridge-switch__slider"></span>
				</label>
			</div>',
			$hidden_input, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $hidden_input is built by render_input() with proper escaping.
			esc_attr( $switch_classes ),
			esc_attr( $field_id ),
			esc_attr( $field_name ),
			$checked, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $checked is a safe string literal (' checked' or '').
			$attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes is built by render_common_attributes() with proper escaping.
			esc_attr( $field_id )
		);
	}

	/**
	 * Merge submitted value with existing value for switch fields
	 *
	 * Handles the special case where unchecked switches are not submitted.
	 *
	 * @param mixed $submitted_value Value submitted in form (null if not submitted).
	 * @param mixed $existing_value  Existing saved value.
	 * @return mixed Merged value.
	 */
	public function merge_values( $submitted_value, $existing_value ) {
		// If switch was not submitted, it was unchecked.
		if ( null === $submitted_value ) {
			return false;
		}

		// Otherwise use default behavior: submitted value takes priority.
		return $submitted_value;
	}
}
