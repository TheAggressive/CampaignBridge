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
		$options = $this->config['options'] ?? [];

		if ( empty( $options ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'No options configured for this radio field.', 'campaignbridge' )
			);
			return;
		}

		echo '<div class="campaignbridge-radio-group">';

		foreach ( $options as $option_value => $option_label ) {
			$radio_id   = $this->config['id'] . '_' . sanitize_key( $option_value );
			$checked    = (string) $option_value === (string) $value ? ' checked' : '';
			$attributes = $this->render_common_attributes();

			// Remove ID and name from attributes as we set them specifically
			$attributes = preg_replace( '/\bid="[^"]*"/', '', $attributes );
			$attributes = preg_replace( '/\bname="[^"]*"/', '', $attributes );

			printf(
				'<label for="%s" class="campaignbridge-radio-label">
					<input type="radio" id="%s" name="%s" value="%s"%s %s />
					<span class="campaignbridge-radio-text">%s</span>
				</label>',
				esc_attr( $radio_id ),
				esc_attr( $radio_id ),
				esc_attr( $this->config['name'] ),
				esc_attr( $option_value ),
				$attributes,
				$checked,
				esc_html( $option_label )
			);
		}

		echo '</div>';
	}
}
