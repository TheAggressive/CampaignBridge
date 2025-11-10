<?php
/**
 * Form Field Select
 *
 * Handles select dropdown fields.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Field Select Class
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Field_Select extends Form_Field_Base {

	/**
	 * Render the input element
	 */
	public function render_input(): void {
		$value      = $this->get_value();
		$attributes = $this->render_common_attributes();
		$options    = $this->config['options'] ?? array();

		if ( empty( $options ) ) {
			printf(
				'<select %s><option value="">%s</option></select>',
				$attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by render_common_attributes() with proper escaping.
				esc_html__( 'No options available', 'campaignbridge' )
			);
			return;
		}

		$multiple = $this->config['multiple_files'] ?? false;
		if ( $multiple ) {
			$attributes           .= ' multiple';
			$this->config['name'] .= '[]'; // Handle array values for multiple select.
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes is built by render_common_attributes() with proper escaping.
		printf( '<select %s>', $attributes );

		// Add placeholder option if no value is selected.
		if ( empty( $value ) && ! empty( $this->config['placeholder'] ) ) {
			printf(
				'<option value="" disabled selected>%s</option>',
				esc_html( $this->config['placeholder'] )
			);
		}

		foreach ( $options as $option_value => $option_label ) {
			$selected = '';

			if ( $multiple && is_array( $value ) ) {
				$selected = in_array( $option_value, $value, true ) ? ' selected' : '';
			} else {
				$selected = (string) $option_value === (string) $value ? ' selected' : '';
			}

			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $option_value ),
				$selected, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe string literal (' selected' or '').
				esc_html( $option_label )
			);
		}

		echo '</select>';
	}
}
