<?php
/**
 * Form Conditional Manager
 *
 * Handles conditional logic for form fields including visibility and requirements.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Admin\Core\Forms\Validation_Messages;

/**
 * Form Conditional Manager Class
 *
 * Manages conditional field logic for show/hide and required states.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Conditional_Manager {
	/**
	 * Conditional type constants.
	 */
	const CONDITIONAL_TYPE_SHOW_WHEN     = 'show_when';
	const CONDITIONAL_TYPE_HIDE_WHEN     = 'hide_when';
	const CONDITIONAL_TYPE_REQUIRED_WHEN = 'required_when';

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
	 * Cache for visibility calculations to improve performance
	 *
	 * @var array<string, bool>
	 */
	private array $visibility_cache = array();

	/**
	 * Whether to enable caching for visibility calculations
	 *
	 * Disable for debugging complex conditional logic.
	 *
	 * @var bool
	 */
	private bool $caching_enabled = true;

	/**
	 * Constructor
	 *
	 * @param array<string, mixed> $fields          Form fields configuration.
	 * @param array<string, mixed> $form_data       Current form data.
	 * @param bool                 $caching_enabled Whether to enable caching for debugging.
	 */
	public function __construct( array $fields, array $form_data = array(), bool $caching_enabled = true ) {
		$this->fields          = $fields;
		$this->form_data       = $form_data;
		$this->caching_enabled = $caching_enabled;
	}

	/**
	 * Update form data for conditional evaluation
	 *
	 * @param array<string, mixed> $form_data New form data.
	 * @return self
	 */
	public function with_form_data( array $form_data ): self {
		$this->form_data = $form_data;
		$this->clear_cache(); // Clear cache when form data changes.
		return $this;
	}

	/**
	 * Clear the visibility calculation cache
	 *
	 * Call this when form data changes to ensure fresh calculations.
	 */
	public function clear_cache(): void {
		$this->visibility_cache = array();
	}

	/**
	 * Enable or disable caching for visibility calculations
	 *
	 * Useful for debugging complex conditional logic where you want to see
	 * fresh calculations on every evaluation.
	 *
	 * @param bool $enabled Whether to enable caching.
	 * @return self
	 */
	public function set_caching_enabled( bool $enabled ): self {
		$this->caching_enabled = $enabled;

		// Clear cache when disabling to ensure fresh state.
		if ( ! $enabled ) {
			$this->clear_cache();
		}

		return $this;
	}

	/**
	 * Check if caching is currently enabled
	 *
	 * @return bool True if caching is enabled.
	 */
	public function is_caching_enabled(): bool {
		return $this->caching_enabled;
	}

	/**
	 * Check if a field should be visible based on conditional logic
	 *
	 * This method evaluates cascading conditions - a field is only visible if:
	 * 1. Its own conditions are met, AND
	 * 2. All parent fields it depends on are also visible
	 *
	 * @param string $field_id Field ID to check.
	 * @return bool True if field should be visible.
	 */
	public function should_show_field( string $field_id ): bool {
		return $this->should_show_field_with_tracking( $field_id, array(), 0 );
	}

	/**
	 * Check if a field should be visible with circular dependency tracking
	 *
	 * Internal method that tracks visited fields to prevent infinite recursion.
	 *
	 * @param string   $field_id Field ID to check.
	 * @param string[] $visited  Fields already visited in this evaluation chain.
	 * @param int      $depth    Current recursion depth.
	 * @return bool True if field should be visible.
	 */
	private function should_show_field_with_tracking( string $field_id, array $visited, int $depth ): bool {
		// Check cache first (but only if not in a dependency chain to avoid stale data).
		$cache_key = $this->get_cache_key( $field_id );
		if ( $this->caching_enabled && empty( $visited ) && isset( $this->visibility_cache[ $cache_key ] ) ) {
			return $this->visibility_cache[ $cache_key ];
		}

		// Track if this is a top-level call for caching decisions.
		$is_top_level_call = empty( $visited );

		// Add current field to visited list to detect circular dependencies.
		$visited[] = $field_id;

		$field_config = $this->fields[ $field_id ] ?? array();

		if ( ! isset( $field_config['conditional'] ) ) {
			$visibility = true; // No conditions, field is visible by default.
		} else {
			$conditional = $field_config['conditional'];

			if ( ! isset( $conditional['type'] ) || ! isset( $conditional['conditions'] ) ) {
				$visibility = true;
			} else {
				$type       = $conditional['type'];
				$conditions = $conditional['conditions'];

				// First check if all dependent fields (parent conditions) are visible.
				if ( ! $this->are_parent_conditions_met( $conditions, $visited, $depth ) ) {
					$visibility = false; // Parent conditions not met, field should be hidden.
				} else {
					// Evaluate the field's own conditions.
					$result = $this->evaluate_conditions( $conditions );

					// Apply logic based on type.
					switch ( $type ) {
						case self::CONDITIONAL_TYPE_SHOW_WHEN:
							$visibility = $result; // Show when conditions are met.
							break;
						case self::CONDITIONAL_TYPE_HIDE_WHEN:
							$visibility = ! $result; // Hide when conditions are met (inverse).
							break;
						case self::CONDITIONAL_TYPE_REQUIRED_WHEN:
							$visibility = true; // Visibility is not affected by required_when.
							break;
						default:
							$visibility = true;
					}
				}
			}
		}

		// Cache the result (only for top-level calls, not recursive ones).
		if ( $this->caching_enabled && $is_top_level_call ) {
			$this->visibility_cache[ $cache_key ] = $visibility;
		}

		return $visibility;
	}

	/**
	 * Generate a cache key for visibility calculations
	 *
	 * @param string $field_id Field ID.
	 * @return string Cache key.
	 */
	private function get_cache_key( string $field_id ): string {
		// Include form data hash to ensure cache validity when data changes.
		return $field_id . '_' . md5( wp_json_encode( $this->form_data ) );
	}

	/**
	 * Check if all parent conditions for a field are met (cascading visibility)
	 *
	 * This ensures that fields are only shown when their entire dependency chain is visible.
	 * For example, if field C depends on field B which depends on field A,
	 * field C is only visible if A, B, and C's conditions are all met.
	 *
	 * Includes circular dependency protection and depth limiting for robustness.
	 *
	 * @param array<array<string, mixed>> $conditions Array of condition arrays.
	 * @param array<string>               $visited    Fields already visited in this evaluation chain.
	 * @param int                         $depth      Current recursion depth.
	 * @return bool True if all parent conditions are met.
	 */
	private function are_parent_conditions_met( array $conditions, array $visited = array(), int $depth = 0 ): bool {
		// Prevent infinite recursion from circular dependencies.
		if ( $depth > 10 ) {
			\CampaignBridge\Core\Error_Handler::warning(
				'Conditional evaluation exceeded maximum depth (circular dependency?)',
				array(
					'depth'     => $depth,
					'visited'   => $visited,
					'form_data' => $this->form_data,
				)
			);
			return false;
		}

		foreach ( $conditions as $condition ) {
			if ( ! isset( $condition['field'] ) ) {
				continue;
			}

			$parent_field_id = $condition['field'];

			// Prevent circular dependencies.
			if ( in_array( $parent_field_id, $visited, true ) ) {
				\CampaignBridge\Core\Error_Handler::warning(
					'Circular dependency detected in conditional logic',
					array(
						'field'   => $parent_field_id,
						'visited' => $visited,
					)
				);
				return false;
			}

			// Recursively check if the parent field itself is visible.
			if ( ! $this->should_show_field_with_tracking( $parent_field_id, $visited, $depth + 1 ) ) {
				return false; // Parent field is not visible, so this field should be hidden.
			}
		}

		return true; // All parent conditions are met.
	}

	/**
	 * Check if a field should be required based on conditional logic
	 *
	 * @param string $field_id Field ID to check.
	 * @return bool True if field should be required.
	 */
	public function should_require_field( string $field_id ): bool {
		$field_config = $this->fields[ $field_id ] ?? array();

		// If field is not visible, it cannot be required for browser validation.
		if ( ! $this->is_field_visible_for_validation( $field_id ) ) {
			return false;
		}

		// Start with the field's base required setting.
		$is_required = $field_config['required'] ?? false;

		// Check for required_when conditions.
		if ( isset( $field_config['conditional'] ) ) {
			$conditional = $field_config['conditional'];

			if ( isset( $conditional['type'] ) && self::CONDITIONAL_TYPE_REQUIRED_WHEN === $conditional['type'] ) {
				if ( isset( $conditional['conditions'] ) ) {
					$is_required = $this->evaluate_conditions( $conditional['conditions'] );
				}
			}
		}

		return $is_required;
	}

	/**
	 * Check if a field is visible for validation purposes (avoids circular dependency)
	 *
	 * This method checks field visibility without considering required state,
	 * to avoid circular dependencies between should_show_field and should_require_field.
	 *
	 * @param string $field_id Field ID to check.
	 * @return bool True if field is visible.
	 */
	private function is_field_visible_for_validation( string $field_id ): bool {
		$field_config = $this->fields[ $field_id ] ?? array();

		if ( ! isset( $field_config['conditional'] ) ) {
			// No conditions, field is always visible.
			return true;
		}

		$conditional = $field_config['conditional'];

		if ( ! isset( $conditional['type'] ) || ! isset( $conditional['conditions'] ) ) {
			return true;
		}

		$type       = $conditional['type'];
		$conditions = $conditional['conditions'];

		// Check if all dependent fields (parent conditions) are visible.
		if ( ! $this->are_parent_conditions_met( $conditions, array(), 0 ) ) {
			return false; // Parent conditions not met.
		}

		// Evaluate the field's own conditions.
		$result = $this->evaluate_conditions( $conditions );

		// Apply logic based on type.
		switch ( $type ) {
			case self::CONDITIONAL_TYPE_SHOW_WHEN:
				return $result; // Show when conditions are met.
			case self::CONDITIONAL_TYPE_HIDE_WHEN:
				return ! $result; // Hide when conditions are met (inverse).
			case self::CONDITIONAL_TYPE_REQUIRED_WHEN:
				return true; // Visibility is not affected by required_when.
			default:
				return true;
		}
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
				return $field_value === $value;

			case 'not_equals':
				return $field_value !== $value;

			case 'is_checked':
				// For checkboxes, one, true values, or positive integers indicate checked state.
				return '1' === $field_value || 1 === $field_value || true === $field_value;

			case 'not_checked':
				// For checkboxes, zero, false values, or empty strings indicate unchecked state.
				return '0' === $field_value || 0 === $field_value || false === $field_value || empty( $field_value );

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
	 * Evaluate all fields and return their conditional status
	 *
	 * Parameters are accepted for API consistency with REST endpoints, but are
	 * not currently used in the implementation. They are reserved for future
	 * enhancements such as user-specific or form-specific conditional logic.
	 *
	 * @param string $form_id Form ID (reserved for future use - form-specific conditional logic).
	 * @param int    $user_id User ID (reserved for future use - user-specific conditional logic).
	 * @return array<string, array<string, bool>> Field status array with 'visible' and 'required' keys.
	 */
	public function evaluate_all_fields( string $form_id, int $user_id ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Parameters reserved for future enhancements
		$result = array();

		foreach ( array_keys( $this->fields ) as $field_id ) {
			$result[ $field_id ] = array(
				'visible'  => $this->should_show_field( $field_id ),
				'required' => $this->should_require_field( $field_id ),
			);
		}

		return $result;
	}

	/**
	 * Check if a field is conditionally required based on current form data
	 *
	 * @param array<string, mixed> $field_config Field configuration.
	 * @return bool True if field is conditionally required, false otherwise.
	 */
	private function is_conditionally_required( array $field_config ): bool {
		if ( ! isset( $field_config['conditional'] ) ) {
			return false;
		}

		$conditional = $field_config['conditional'];

		return self::CONDITIONAL_TYPE_REQUIRED_WHEN === ( $conditional['type'] ?? '' )
			&& isset( $conditional['conditions'] )
			&& $this->evaluate_conditions( $conditional['conditions'] );
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
		// Check if field is conditionally required.
		if ( $this->is_conditionally_required( $field_config ) ) {
			// Field is conditionally required and conditions are met.
			if ( empty( $field_value ) ) {
				return new \WP_Error(
					'field_required',
					sprintf(
						Validation_Messages::get( 'field_required' ),
						$field_config['label'] ?? 'This field'
					)
				);
			}
		}

		return true;
	}

	/**
	 * Validate all fields with conditional logic
	 *
	 * @param array<string, mixed> $form_data Form data to validate.
	 * @return array<string, mixed> Validation result with 'valid' and 'errors'.
	 */
	public function validate_conditional_fields( array $form_data ): array {
		$errors   = array();
		$is_valid = true;

		foreach ( $this->fields as $field_id => $field_config ) {
			// Skip validation for hidden conditional fields.
			if ( ! $this->should_show_field( $field_id ) ) {
				continue;
			}

			$value = $form_data[ $field_id ] ?? '';

			$field_validation = $this->validate_conditional_requirements( $field_id, $field_config, $value );
			if ( \is_wp_error( $field_validation ) ) {
				$errors[ $field_id ] = $field_validation->get_error_message();
				$is_valid            = false;
			}
		}

		return array(
			'valid'  => $is_valid,
			'errors' => $errors,
		);
	}

	/**
	 * Generate JavaScript localization data for conditional fields
	 *
	 * @param string $form_id Form ID for JavaScript targeting.
	 * @return array<string, mixed> Localized data for wp_localize_script.
	 */
	public function get_localization_data( string $form_id ): array {
		return array(
			'formId'       => $form_id,
			'conditionals' => $this->get_conditional_fields(),
		);
	}
}
