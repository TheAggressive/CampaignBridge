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

		// Switch/toggle styling
		$switch_classes = 'campaignbridge-switch';
		if ( isset( $this->config['class'] ) ) {
			$switch_classes .= ' ' . $this->config['class'];
		}

		printf(
			'<div class="%s">
				<input type="checkbox" id="%s" name="%s" value="1" %s %s />
				<label for="%s" class="campaignbridge-switch__label">
					<span class="campaignbridge-switch__slider"></span>
				</label>
			</div>',
			esc_attr( $switch_classes ),
			esc_attr( $field_id ),
			esc_attr( $field_name ),
			$checked,
			$attributes,
			esc_attr( $field_id )
		);
	}
}
