<?php
/**
 * Form Field Repeater - Creates multiple fields with smart state management
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Admin\Core\Form_Builder;

/**
 * Form Field Repeater Class
 *
 * Intermediate builder class that creates multiple fields of the same type
 * with optional state management from persistent data sources.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Field_Repeater {

	/**
	 * Parent form builder instance
	 *
	 * @var Form_Builder
	 */
	private Form_Builder $form_builder;

	/**
	 * Base field ID
	 *
	 * @var string
	 */
	private string $field_id;

	/**
	 * All possible choice options
	 *
	 * @var array<string, string>
	 */
	private array $populate_all_choices;

	/**
	 * Current state/persistent data
	 *
	 * @var array<int|string, mixed>
	 */
	private array $persistent_data;

	/**
	 * Default checked choice key
	 *
	 * @var string|null
	 */
	private ?string $default_checked = null;

	/**
	 * Whether fields should be grouped inline
	 *
	 * @var bool
	 */
	private bool $grouped = false;

	/**
	 * Layout direction for grouped fields ('horizontal' or 'vertical')
	 *
	 * @var string
	 */
	private string $group_layout = 'horizontal';

	/**
	 * Constructor
	 *
	 * @param Form_Builder              $form_builder         Parent form builder.
	 * @param string                    $field_id             Base field name.
	 * @param array<string, string|int> $populate_all_choices All possible options.
	 * @param mixed                     $persistent_data      Current state data (normalized to array).
	 *
	 * @throws \InvalidArgumentException If validation fails.
	 */
	public function __construct(
		Form_Builder $form_builder,
		string $field_id,
		array $populate_all_choices,
		$persistent_data = null
	) {
		// Validate field_id.
		if ( empty( $field_id ) ) {
			throw new \InvalidArgumentException( 'Field ID cannot be empty in repeater() method.' );
		}

		// Validate populate_all_choices.
		if ( empty( $populate_all_choices ) ) {
			throw new \InvalidArgumentException( 'Choices array cannot be empty in repeater() method.' );
		}

		// Validate populate_all_choices structure.
		$this->validate_choice_structure( $populate_all_choices );

		// Normalize persistent_data to array.
		$this->persistent_data = $this->normalize_persistent_data( $persistent_data );

		$this->form_builder         = $form_builder;
		$this->field_id             = $field_id;
		$this->populate_all_choices = $populate_all_choices;
	}

	/**
	 * Normalize persistent data to array format
	 *
	 * Accepts various formats and converts to array:
	 * - null/empty → []
	 * - string → [string]
	 * - array → array (unchanged)
	 *
	 * @param mixed $data Raw persistent data.
	 * @return array<int|string, mixed> Normalized array format.
	 */
	private function normalize_persistent_data( $data ): array {
		if ( is_array( $data ) ) {
			return $data;
		}

		if ( is_string( $data ) && ! empty( $data ) ) {
			return array( $data );
		}

		return array();
	}

	/**
	 * Set default checked choice
	 *
	 * @param string $key Choice key to be checked by default.
	 * @return self
	 */
	public function default( string $key ): self {
		$this->default_checked = $key;
		return $this;
	}

	/**
	 * Group fields inline instead of individual rows
	 *
	 * @param string $layout Layout direction ('horizontal' or 'vertical'). Defaults to 'horizontal'.
	 * @return Form_Field_Repeater
	 */
	public function group( string $layout = 'horizontal' ): Form_Field_Repeater {
		$this->grouped      = true;
		$this->group_layout = $layout;

		// Validate layout option.
		if ( ! in_array( $layout, array( 'horizontal', 'vertical' ), true ) ) {
			$this->group_layout = 'horizontal'; // Default fallback.
		}

		// When grouped, automatically use div layout for inline rendering.
		$this->form_builder->div();
		return $this;
	}

	/**
	 * Create switch/toggle fields
	 *
	 * @return Form_Builder
	 */
	public function switch(): Form_Builder {
		return $this->create_fields( 'switch' );
	}

	/**
	 * Create checkbox fields
	 *
	 * @return Form_Builder
	 */
	public function checkbox(): Form_Builder {
		return $this->create_fields( 'checkbox' );
	}

	/**
	 * Create radio button fields
	 *
	 * @return Form_Builder
	 */
	public function radio(): Form_Builder {
		return $this->create_fields( 'radio' );
	}

	/**
	 * Create select dropdown fields
	 *
	 * @return Form_Builder
	 */
	public function select(): Form_Builder {
		return $this->create_fields( 'select' );
	}

	/**
	 * Create the actual fields based on type
	 *
	 * @param string $type Field type.
	 * @return Form_Builder
	 */
	private function create_fields( string $type ): Form_Builder {
		// Radio and select fields work differently - they need ONE field with options.
		if ( 'radio' === $type || 'select' === $type ) {
			return $this->create_single_field_with_options( $type );
		}

		// For switch/checkbox, create individual fields for each choice.
		foreach ( $this->populate_all_choices as $key => $label ) {
			// Use triple underscore as separator to avoid PHP array parsing issues.
			// When form wraps this as form_id[field___key], PHP parses it correctly.
			// Form_Handler will convert back to proper array format on save.
			$field_name = $this->field_id . '___' . $key;

			// Determine if should be checked/selected.
			$should_check = $this->should_be_checked( $key );

			// Add the field using public API.
			$field = $this->form_builder->add( $field_name, $type, (string) $label );

			// Add CSS classes for grouped repeater styling and layout direction.
			if ( $this->grouped ) {
				$field->class( 'campaignbridge-repeater-grouped' );
				$field->class( 'campaignbridge-repeater-' . $this->group_layout );
			}

			// For checkbox and switch types, disable hidden field (repeater handles unchecked state).
			if ( in_array( $type, array( 'checkbox', 'switch' ), true ) ) {
				$this->form_builder->get_config()->update_field( $field_name, array( 'skip_hidden_field' => true ) );
			}

			// Set default value if needed.
			if ( $should_check ) {
				$field->default( true );
			}

			// End field configuration to return to form builder.
			$field->end();
		}

		return $this->form_builder;
	}

	/**
	 * Create a single field with options (for radio/select)
	 *
	 * Radio and select fields need ONE field with all choices as options.
	 *
	 * @param string $type Field type (radio or select).
	 * @return Form_Builder
	 */
	private function create_single_field_with_options( string $type ): Form_Builder {
		// For radio/select, determine which option should be selected.
		$selected_value = null;

		foreach ( array_keys( $this->populate_all_choices ) as $key ) {
			if ( $this->should_be_checked( $key ) ) {
				$selected_value = $key;
				break; // Radio/select can only have one selection.
			}
		}

		// Create a single field with all choices as options.
		$field = $this->form_builder->add( $this->field_id, $type, '' );

		// Set the options.
		$field->options( $this->populate_all_choices );

		// Set default selected value if found.
		if ( null !== $selected_value ) {
			$field->default( $selected_value );
		}

		// End field configuration.
		$field->end();

		return $this->form_builder;
	}

	/**
	 * Determine if a choice should be checked/selected
	 *
	 * @param string|int $key Choice key.
	 * @return bool
	 */
	private function should_be_checked( $key ): bool {
		// Priority 1: Check persistent data (state-based mode).
		if ( ! empty( $this->persistent_data ) ) {
			return in_array( $key, $this->persistent_data, true );
		}

		// Priority 2: Check if this is the default specified.
		if ( null !== $this->default_checked && (string) $this->default_checked === (string) $key ) {
			return true;
		}

		// Priority 3: Stateless mode - all unchecked.
		return false;
	}

	/**
	 * Validate the structure of choice options array.
	 *
	 * Performs defensive runtime validation to ensure array structure meets
	 * requirements, providing robustness against incorrect usage.
	 *
	 * @param array<mixed, mixed> $choices Choice options to validate.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private function validate_choice_structure( array $choices ): void {
		foreach ( $choices as $key => $label ) {
			// Runtime validation: ensure keys are strings.
			if ( ! is_string( $key ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Choice key must be a string in repeater() method. Got: %s',
						esc_html( gettype( $key ) )
					)
				);
			}

			// Runtime validation: ensure labels are valid types.
			if ( ! $this->is_valid_choice_label( $label ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Choice label for key "%s" must be a string or number in repeater() method.',
						esc_html( (string) $key )
					)
				);
			}
		}
	}

	/**
	 * Check if a choice label is valid.
	 *
	 * @param mixed $label Label to validate.
	 * @return bool True if valid.
	 */
	private function is_valid_choice_label( $label ): bool {
		return is_string( $label ) || is_numeric( $label );
	}
}
