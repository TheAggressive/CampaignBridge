<?php
/**
 * Form Field Interface
 *
 * Defines the contract for all form field types.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Field Interface
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
interface Form_Field_Interface {

	/**
	 * Render the field in table layout
	 */
	public function render_table_row(): void;

	/**
	 * Render the field in div layout
	 */
	public function render_div_field(): void;

	/**
	 * Render just the input element
	 */
	public function render_input(): void;

	/**
	 * Get field configuration
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array;

	/**
	 * Get field value
	 *
	 * @return mixed
	 */
	public function get_value();

	/**
	 * Set field value
	 *
	 * @param mixed $value Field value.
	 */
	public function set_value( $value ): void;

	/**
	 * Check if field is required
	 *
	 * @return bool
	 */
	public function is_required(): bool;

	/**
	 * Get field validation rules
	 *
	 * @return array<string, mixed>
	 */
	public function get_validation_rules(): array;

	/**
	 * Validate field value
	 *
	 * @param mixed $value Value to validate.
	 * @return bool|\WP_Error True if valid, \WP_Error if invalid.
	 */
	public function validate( $value );
}
