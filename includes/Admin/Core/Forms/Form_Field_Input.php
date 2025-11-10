<?php
/**
 * Form Field Input
 *
 * Handles text, email, password, number, etc. input fields.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Field Input Class
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Field_Input extends Form_Field_Base {

	/**
	 * Render the input element
	 */
	public function render_input(): void {
		$value = $this->get_value();
		$type  = $this->config['type'];

		// Ensure valid input type.
		$valid_types = array(
			'text',
			'email',
			'url',
			'password',
			'number',
			'tel',
			'search',
			'date',
			'datetime-local',
			'month',
			'week',
			'time',
			'color',
			'range',
			'hidden',
		);

		if ( ! in_array( $type, $valid_types, true ) ) {
			$type = 'text';
		}

		$attributes = $this->render_common_attributes();

		printf(
			'<input type="%s" value="%s" %s />',
			esc_attr( $type ),
			esc_attr( $value ),
			$attributes // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes is built by render_common_attributes() with proper escaping.
		);
	}
}
