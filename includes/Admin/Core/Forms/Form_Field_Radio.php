<?php
/**
 * Form Field Radio
 *
 * Handles radio button input fields.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Field Radio Class
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Field_Radio extends Form_Field_Base {

	/**
	 * Render the input element
	 */
	public function render_input(): void {
		$value   = $this->get_value();
		$options = $this->config['options'] ?? array();

		if ( empty( $options ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'No options configured for this radio field.', 'campaignbridge' )
			);
			return;
		}

		$field_label = $this->config['label'] ?? '';

		// Use fieldset and legend for proper accessibility grouping.
		echo '<fieldset class="campaignbridge-radio-group">';
		if ( ! empty( $field_label ) ) {
			echo '<legend class="campaignbridge-radio-legend">' . esc_html( $field_label ) . '</legend>';
		}

		foreach ( $options as $option_value => $option_label ) {
			$radio_id   = $this->config['id'] . '_' . sanitize_key( $option_value );
			$checked    = (string) $option_value === (string) $value ? ' checked' : '';
			$attributes = $this->render_common_attributes();

			// Remove ID and name from attributes as we set them specifically.
			$attributes = preg_replace( '/\bid="[^"]*"/', '', $attributes ) ?? $attributes;
			$attributes = preg_replace( '/\bname="[^"]*"/', '', $attributes ) ?? $attributes;

			printf(
				'<label for="%s" class="campaignbridge-radio-label">
					<input type="radio" id="%s" name="%s" value="%s" class="campaignbridge-radio-input"%s %s />
					<span class="campaignbridge-radio-text">%s</span>
				</label>',
				esc_attr( $radio_id ),
				esc_attr( $radio_id ),
				esc_attr( $this->config['name'] ),
				esc_attr( $option_value ),
				$attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes is built by render_common_attributes() with proper escaping.
				$checked, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $checked is a safe string literal (' checked' or '').
				esc_html( $option_label )
			);
		}

		echo '</fieldset>';
	}

	/**
	 * Render the field in div layout
	 * Radio fields use fieldset/legend instead of external labels
	 */
	public function render_div_field(): void {
		$wrapper_class = 'campaignbridge-field-wrapper campaignbridge-radio-field-wrapper';
		if ( ! empty( $this->config['wrapper_class'] ) ) {
			$wrapper_class .= ' ' . $this->config['wrapper_class'];
		}

		printf( '<div class="%s">', esc_attr( $wrapper_class ) );

		$this->render_html( 'before' );
		$this->render_input();
		$this->render_description();
		$this->render_html( 'after' );

		echo '</div>';
	}

	/**
	 * Render the field in table layout
	 * Radio fields use fieldset/legend instead of external labels
	 */
	public function render_table_row(): void {
		$wrapper_class = 'campaignbridge-radio-field-wrapper';
		if ( ! empty( $this->config['wrapper_class'] ) ) {
			$wrapper_class .= ' ' . $this->config['wrapper_class'];
		}

		printf( '<tr class="%s">', esc_attr( $wrapper_class ) );

		// Empty th for radio fields since label is in fieldset.
		echo '<th scope="row"></th>';

		echo '<td>';
		$this->render_html( 'before' );
		$this->render_input();
		$this->render_description();
		$this->render_html( 'after' );
		echo '</td>';

		echo '</tr>';
	}
}
