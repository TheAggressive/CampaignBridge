<?php
/**
 * Form Conditional Manager
 *
 * Handles conditional logic for form fields including visibility and requirements.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Conditional Manager Class
 *
 * Manages conditional field logic for show/hide and required states.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Conditional_Manager {

	/**
	 * Form fields configuration
	 *
	 * @var array<string, mixed>
	 */
	private array $fields;

	/**
	 * Form data for conditional evaluation
	 *
	 * @var array<string, mixed>
	 */
	private array $form_data;

	/**
	 * Constructor
	 *
	 * @param array<string, mixed> $fields    Form fields configuration.
	 * @param array<string, mixed> $form_data Current form data.
	 */
	public function __construct( array $fields, array $form_data = array() ) {
		$this->fields    = $fields;
		$this->form_data = $form_data;
	}

	/**
	 * Update form data for conditional evaluation
	 *
	 * @param array<string, mixed> $form_data New form data.
	 * @return self
	 */
	public function with_form_data( array $form_data ): self {
		$this->form_data = $form_data;
		return $this;
	}

	/**
	 * Check if a field should be visible based on conditional logic
	 *
	 * @param string $field_id Field ID to check.
	 * @return bool True if field should be visible.
	 */
	public function should_show_field( string $field_id ): bool {
		$field_config = $this->fields[ $field_id ] ?? array();

		if ( ! isset( $field_config['conditional'] ) ) {
			return true; // No conditions, field is visible by default.
		}

		$conditional = $field_config['conditional'];

		if ( ! isset( $conditional['type'] ) || ! isset( $conditional['conditions'] ) ) {
			return true;
		}

		$type       = $conditional['type'];
		$conditions = $conditional['conditions'];

		// Evaluate the conditions.
		$result = $this->evaluate_conditions( $conditions );

		// Apply logic based on type.
		switch ( $type ) {
			case 'show_when':
				return $result; // Show when conditions are met.
			case 'hide_when':
				return ! $result; // Hide when conditions are met (inverse).
			case 'required_when':
				return true; // Visibility is not affected by required_when.
			default:
				return true;
		}
	}

	/**
	 * Check if a field should be required based on conditional logic
	 *
	 * @param string $field_id Field ID to check.
	 * @return bool True if field should be required.
	 */
	public function should_require_field( string $field_id ): bool {
		$field_config = $this->fields[ $field_id ] ?? array();

		// Start with the field's base required setting.
		$is_required = $field_config['required'] ?? false;

		// Check for required_when conditions.
		if ( isset( $field_config['conditional'] ) ) {
			$conditional = $field_config['conditional'];

			if ( isset( $conditional['type'] ) && 'required_when' === $conditional['type'] ) {
				if ( isset( $conditional['conditions'] ) ) {
					$is_required = $this->evaluate_conditions( $conditional['conditions'] );
				}
			}
		}

		return $is_required;
	}

	/**
	 * Get all conditional field configurations
	 *
	 * @return array<string, array<string, mixed>> Conditional field configs.
	 */
	public function get_conditional_fields(): array {
		$conditionals = array();

		foreach ( $this->fields as $field_id => $field_config ) {
			if ( isset( $field_config['conditional'] ) ) {
				$conditionals[ $field_id ] = $field_config['conditional'];
			}
		}

		return $conditionals;
	}

	/**
	 * Check if form has any conditional fields
	 *
	 * @return bool True if form has conditional fields.
	 */
	public function has_conditional_fields(): bool {
		return ! empty( $this->get_conditional_fields() );
	}

	/**
	 * Evaluate a set of conditions
	 *
	 * @param array<array<string, mixed>> $conditions Array of condition arrays.
	 * @return bool True if all conditions are met (AND logic).
	 */
	private function evaluate_conditions( array $conditions ): bool {
		foreach ( $conditions as $condition ) {
			if ( ! $this->evaluate_single_condition( $condition ) ) {
				return false; // Any condition failing means overall false.
			}
		}

		return true; // All conditions passed.
	}

	/**
	 * Evaluate a single condition
	 *
	 * @param array<string, mixed> $condition Condition configuration.
	 * @return bool True if condition is met.
	 */
	private function evaluate_single_condition( array $condition ): bool {
		$field    = $condition['field'] ?? '';
		$operator = $condition['operator'] ?? 'equals';
		$value    = $condition['value'] ?? '';

		if ( empty( $field ) ) {
			return false;
		}

		// Get the field value from form data.
		$field_value = $this->form_data[ $field ] ?? '';

		// Evaluate based on operator.
		switch ( $operator ) {
			case 'equals':
				return $field_value == $value; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison

			case 'not_equals':
				return $field_value != $value; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison

			case 'is_checked':
				return ! empty( $field_value );

			case 'not_checked':
				return empty( $field_value );

			case 'contains':
				return strpos( (string) $field_value, (string) $value ) !== false;

			case 'greater_than':
				return (float) $field_value > (float) $value;

			case 'less_than':
				return (float) $field_value < (float) $value;

			default:
				return false;
		}
	}

	/**
	 * Get fields that should be visible based on current form data
	 *
	 * @return array<string> Array of visible field IDs.
	 */
	public function get_visible_fields(): array {
		$visible = array();

		foreach ( $this->fields as $field_id => $field_config ) {
			if ( $this->should_show_field( $field_id ) ) {
				$visible[] = $field_id;
			}
		}

		return $visible;
	}

	/**
	 * Validate conditional requirements for a field
	 *
	 * @param string               $field_id    Field ID.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param mixed                $field_value Field value.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_conditional_requirements( string $field_id, array $field_config, $field_value ) {
		// Check required_when conditions.
		if ( isset( $field_config['conditional'] ) ) {
			$conditional = $field_config['conditional'];

			if ( 'required_when' === $conditional['type'] && $this->evaluate_conditions( $conditional['conditions'] ) ) {
				// Field is conditionally required and conditions are met.
				if ( empty( $field_value ) ) {
					return new \WP_Error(
						'field_required',
						sprintf(
							/* translators: %s: field label */
							__( '%s is required.', 'campaignbridge' ),
							$field_config['label'] ?? 'This field'
						)
					);
				}
			}
		}

		return true;
	}
}
