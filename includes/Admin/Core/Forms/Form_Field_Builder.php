<?php
/**
 * Form Field Builder - Fluent API for field configuration
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Admin\Core\Form_Builder;
use CampaignBridge\Admin\Core\Form_Config_Methods;
use CampaignBridge\Admin\Core\Form_Field_Methods;
use CampaignBridge\Admin\Core\Form_Hook_Methods;
use CampaignBridge\Admin\Core\Form_Layout_Methods;

/**
 * Form Field Builder - Fluent API for field configuration
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Field_Builder {
	use Form_Field_Methods;
	use Form_Config_Methods;
	use Form_Hook_Methods;
	use Form_Layout_Methods;

	/**
	 * Parent form builder instance
	 *
	 * @var Form_Builder
	 */
	private Form_Builder $builder;

	/**
	 * Field name
	 *
	 * @var string
	 */
	private string $field_name;

	/**
	 * Constructor
	 *
	 * @param Form_Builder $builder    Parent form builder.
	 * @param string       $field_name Field name.
	 */
	public function __construct( Form_Builder $builder, string $field_name ) {
		$this->builder    = $builder;
		$this->field_name = $field_name;
	}

	/**
	 * Set field label
	 *
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function label( string $label ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'label' => $label ) );
		return $this;
	}

	/**
	 * Set field as required
	 *
	 * @return self
	 */
	public function required(): self {
		// Set the HTML required attribute.
		$this->builder->get_config()->update_field( $this->field_name, array( 'required' => true ) );

		// Also add to validation rules for JavaScript validation.
		$field_config           = $this->builder->get_config()->get_field( $this->field_name ) ?? array();
		$validation             = $field_config['validation'] ?? array();
		$validation['required'] = true;
		$this->builder->get_config()->update_field( $this->field_name, array( 'validation' => $validation ) );

		return $this;
	}

	/**
	 * Set field description
	 *
	 * @param string $description Field description.
	 * @return Form_Field_Builder
	 */
	public function description( string $description ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'description' => $description ) );
		return $this;
	}

	/**
	 * Set field placeholder
	 *
	 * @param string $placeholder Field placeholder.
	 * @return Form_Field_Builder
	 */
	public function placeholder( string $placeholder ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'placeholder' => $placeholder ) );
		return $this;
	}

	/**
	 * Set field default value
	 *
	 * @param mixed $value Default value.
	 * @return Form_Field_Builder
	 */
	public function default( $value ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'default' => $value ) );
		return $this;
	}

	/**
	 * Set field options (for select, radio, checkbox)
	 *
	 * @param array<string, mixed> $options Field options.
	 * @return Form_Field_Builder
	 */
	public function options( array $options ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'options' => $options ) );
		return $this;
	}

	/**
	 * Set field class
	 *
	 * @param string $class_name CSS class.
	 * @return Form_Field_Builder
	 */
	public function class( string $class_name ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'class' => $class_name ) );
		return $this;
	}

	/**
	 * Set field validation rules
	 *
	 * @param array<string, mixed> $rules Validation rules.
	 * @return Form_Field_Builder
	 */
	public function rules( array $rules ): self {
		$field_config               = $this->builder->get_config()->get_field( $this->field_name ) ?? array();
		$field_config['validation'] = $rules;
		$this->builder->get_config()->update_field( $this->field_name, array( 'validation' => $rules ) );
		return $this;
	}

	/**
	 * Add a specific validation rule
	 *
	 * @param string $rule Validation rule name.
	 * @param mixed  $value Validation rule value.
	 * @return self
	 */
	public function validation( string $rule, $value ): self {
		$this->add_validation_rule( $rule, $value );
		return $this;
	}

	/**
	 * Set minimum length validation
	 *
	 * @param int $length Minimum length.
	 * @return self
	 */
	public function min_length( int $length ): self {
		$this->add_validation_rule( 'min_length', $length );
		return $this;
	}

	/**
	 * Set maximum length validation
	 *
	 * @param int $length Maximum length.
	 * @return self
	 */
	public function max_length( int $length ): self {
		$this->add_validation_rule( 'max_length', $length );
		return $this;
	}

	/**
	 * Set pattern validation
	 *
	 * @param string $pattern Regular expression pattern.
	 * @param string $message Custom error message.
	 * @return self
	 */
	public function pattern( string $pattern, string $message = '' ): self {
		$this->add_validation_rule(
			'pattern',
			array(
				'pattern' => $pattern,
				'message' => $message ? $message : 'Please enter a valid value.',
			)
		);
		return $this;
	}

	/**
	 * Add a validation rule to the field
	 *
	 * @param string $rule  Validation rule name.
	 * @param mixed  $value Validation rule value.
	 */
	private function add_validation_rule( string $rule, $value ): void {
		$field_config               = $this->builder->get_config()->get_field( $this->field_name ) ?? array();
		$validation                 = $field_config['validation'] ?? array();
		$validation[ $rule ]        = $value;
		$field_config['validation'] = $validation;
		$this->builder->get_config()->update_field( $this->field_name, $field_config );
	}

	/**
	 * Set minimum value (for number, range, slider inputs)
	 *
	 * @param int $min Minimum value.
	 * @return Form_Field_Builder
	 */
	public function min( int $min ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'min' => $min ) );
		return $this;
	}

	/**
	 * Set maximum value (for number, range, slider inputs)
	 *
	 * @param int $max Maximum value.
	 * @return Form_Field_Builder
	 */
	public function max( int $max ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'max' => $max ) );
		return $this;
	}

	/**
	 * Set step value (for number, range, slider inputs)
	 *
	 * @param int $step Step value.
	 * @return Form_Field_Builder
	 */
	public function step( int $step ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'step' => $step ) );
		return $this;
	}


	/**
	 * Set maximum file size (for file inputs)
	 *
	 * @param int $bytes Maximum file size in bytes.
	 * @return Form_Field_Builder
	 * @throws \InvalidArgumentException If called on non-file field types.
	 */
	public function max_size( int $bytes ): self {
		$this->validate_file_field_method( 'max_size()' );
		$this->builder->get_config()->update_field( $this->field_name, array( 'max_size' => $bytes ) );
		return $this;
	}

	/**
	 * Set textarea rows
	 *
	 * @param int $rows Number of rows.
	 * @return Form_Field_Builder
	 */
	public function rows( int $rows ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'rows' => $rows ) );
		return $this;
	}

	/**
	 * Validate that a method can only be called on file fields
	 *
	 * @param string $method_name Method name for error message.
	 * @throws \InvalidArgumentException If called on non-file field types.
	 */
	private function validate_file_field_method( string $method_name ): void {
		$field_config = $this->builder->get_config()->get_field( $this->field_name );

		if ( 'file' !== ( $field_config['type'] ?? '' ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'The %s method can only be used with file fields. Field "%s" is of type "%s".',
					esc_html( $method_name ),
					esc_html( $this->field_name ),
					esc_html( $field_config['type'] ?? 'unknown' )
				)
			);
		}
	}

	/**
	 * Set file accept types
	 *
	 * @param string $accept Accept attribute value.
	 * @return Form_Field_Builder
	 * @throws \InvalidArgumentException If called on non-file field types.
	 */
	public function accept( string $accept ): self {
		$this->validate_file_field_method( 'accept()' );
		$this->builder->get_config()->update_field( $this->field_name, array( 'accept' => $accept ) );
		return $this;
	}

	/**
	 * Enable multiple file selection
	 *
	 * @return Form_Field_Builder
	 * @throws \InvalidArgumentException If called on non-file field types.
	 */
	public function multiple_files(): self {
		$this->validate_file_field_method( 'multiple_files()' );
		$this->builder->get_config()->update_field( $this->field_name, array( 'multiple_files' => true ) );
		return $this;
	}

	/**
	 * Set autocomplete attribute
	 *
	 * @param string $value Autocomplete value (e.g., 'email', 'name', 'organization').
	 * @return Form_Field_Builder
	 */
	public function autocomplete( string $value ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'autocomplete' => $value ) );
		return $this;
	}

	/**
	 * Add custom field attributes
	 *
	 * @param array<string, mixed> $attributes Custom attributes.
	 * @return Form_Field_Builder
	 */
	public function attributes( array $attributes ): self {
		$field_config     = $this->builder->get_config()->get_field( $this->field_name ) ?? array();
		$field_attributes = $field_config['attributes'] ?? array();
		$field_attributes = array_merge( $field_attributes, $attributes );
		$this->builder->get_config()->update_field( $this->field_name, array( 'attributes' => $field_attributes ) );
		return $this;
	}

	/**
	 * Show field when conditions are met
	 *
	 * @param array<array<string, mixed>> $conditions Array of condition arrays.
	 * @return self
	 */
	public function show_when( array $conditions ): self {
		$this->set_conditional_logic( 'show_when', $conditions );
		return $this;
	}

	/**
	 * Hide field when conditions are met
	 *
	 * @param array<array<string, mixed>> $conditions Array of condition arrays.
	 * @return self
	 */
	public function hide_when( array $conditions ): self {
		$this->set_conditional_logic( 'hide_when', $conditions );
		return $this;
	}

	/**
	 * Make field required when conditions are met
	 *
	 * @param array<array<string, mixed>> $conditions Array of condition arrays.
	 * @return self
	 */
	public function required_when( array $conditions ): self {
		$this->set_conditional_logic( 'required_when', $conditions );
		return $this;
	}

	/**
	 * Set conditional logic for the field
	 *
	 * @param string                      $type       Conditional type ('show_when', 'hide_when', 'required_when').
	 * @param array<array<string, mixed>> $conditions Array of condition arrays.
	 */
	private function set_conditional_logic( string $type, array $conditions ): void {
		$this->builder->get_config()->update_field(
			$this->field_name,
			array(
				'conditional' => array(
					'type'       => $type,
					'conditions' => $conditions,
				),
			)
		);
	}

	/**
	 * End field configuration and return to form builder
	 *
	 * @return Form_Builder
	 */
	public function end(): Form_Builder {
		return $this->builder;
	}

	/**
	 * Add another field (convenience method)
	 *
	 * @param string $name New field name.
	 * @param string $type New field type.
	 * @param string $label New field label.
	 * @return Form_Field_Builder
	 */
	public function add( string $name, string $type, string $label = '' ): Form_Field_Builder {
		return $this->builder->add( $name, $type, $label );
	}

	/**
	 * Set security context for encrypted fields
	 *
	 * @param string $context Security context ('api_key', 'sensitive', 'personal', 'public').
	 * @return self
	 */
	public function context( string $context ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'context' => $context ) );
		return $this;
	}

	/**
	 * Show or hide the reveal button for encrypted fields
	 *
	 * @param bool $show Whether to show the reveal button.
	 * @return self
	 */
	public function show_reveal( bool $show = true ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'show_reveal' => $show ) );
		return $this;
	}

	/**
	 * Show or hide the edit button for encrypted fields
	 *
	 * @param bool $show Whether to show the edit button.
	 * @return self
	 */
	public function show_edit( bool $show = true ): self {
		$this->builder->get_config()->update_field( $this->field_name, array( 'show_edit' => $show ) );
		return $this;
	}


	/**
	 * Magic method to proxy methods to parent Form_Builder or handle locally
	 *
	 * This allows seamless chaining from field configuration to form configuration
	 * without needing to call ->end() explicitly. Any method that exists on
	 * Form_Builder will be proxied automatically.
	 *
	 * @param string       $method Method name.
	 * @param array<mixed> $args   Method arguments.
	 * @return mixed Result of the method call.
	 *
	 * @throws \BadMethodCallException If method does not exist.
	 */
	public function __call( string $method, array $args ) {
		// Check if this is a field creation method by examining Form_Builder's methods
		// Field creation methods return Form_Field_Builder.
		if ( $this->is_field_creation_method( $method ) ) {
			// Automatically end the current field configuration and create the new field.
			$builder = $this->end();
			return $builder->{$method}( ...$args );
		}

		// First, check if this method exists on the parent Form_Builder.
		if ( method_exists( $this->builder, $method ) ) {
			// Proxy to Form_Builder for seamless chaining.
			return $this->builder->{$method}( ...$args );
		}

		// Check if this method exists on Form_Field_Builder itself.
		if ( method_exists( $this, $method ) ) {
			// Call the local method.
			return $this->{$method}( ...$args );
		}

		// Method not found anywhere.
		throw new \BadMethodCallException(
			esc_html( "Method '{$method}' does not exist on " . __CLASS__ . ' or ' . get_class( $this->builder ) )
		);
	}

	/**
	 * Check if a method is a field creation method (returns Form_Field_Builder).
	 *
	 * @param string $method Method name to check.
	 * @return bool True if method creates fields.
	 */
	private function is_field_creation_method( string $method ): bool {
		static $field_creation_methods = null;

		// Cache the result to avoid repeated reflection.
		if ( null === $field_creation_methods ) {
			$field_creation_methods = array();
			$reflection             = new \ReflectionClass( $this->builder );

			foreach ( $reflection->getMethods( \ReflectionMethod::IS_PUBLIC ) as $reflection_method ) {
				$return_type = $reflection_method->getReturnType();
				if ( $return_type instanceof \ReflectionNamedType && $return_type->getName() === self::class ) {
					$field_creation_methods[] = $reflection_method->getName();
				}
			}
		}

		return in_array( $method, $field_creation_methods, true );
	}

	/**
	 * Render the form.
	 *
	 * @return void
	 */
	public function render(): void {
		$this->builder->render();
	}
}
