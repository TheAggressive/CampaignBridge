<?php
/**
 * Form Field Textarea
 *
 * Handles textarea input fields.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Field Textarea Class
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Field_Textarea extends Form_Field_Base {

	/**
	 * Render the input element
	 */
	public function render_input(): void {
		$value      = $this->get_value();
		$attributes = $this->render_common_attributes();

		// Add textarea-specific attributes.
		$rows = intval( $this->config['rows'] ?? 5 );
		$cols = intval( $this->config['cols'] ?? 50 );

		$attributes .= sprintf( ' rows="%d" cols="%d"', $rows, $cols );

		printf(
			'<textarea %s>%s</textarea>',
			$attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes is built by render_common_attributes() with proper escaping.
			esc_textarea( $value )
		);
	}
}
