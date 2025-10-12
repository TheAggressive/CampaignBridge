<?php
/**
 * Form Field Builder - Fluent API for field configuration
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Admin\Core\Form_Builder;

/**
 * Form Field Builder - Fluent API for field configuration
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Field_Builder {

	/**
	 * Parent form builder instance
	 *
	 * @var Form_Builder
	 */
	private Form_Builder $form_builder;

	/**
	 * Field name
	 *
	 * @var string
	 */
	private string $field_name;

	/**
	 * Constructor
	 *
	 * @param Form_Builder $form_builder Parent form builder.
	 * @param string       $field_name   Field name.
	 */
	public function __construct( Form_Builder $form_builder, string $field_name ) {
		$this->form_builder = $form_builder;
		$this->field_name   = $field_name;
	}

	/**
	 * Set field label
	 *
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function label( string $label ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'label' => $label ) );
		return $this;
	}

	/**
	 * Set field as required
	 *
	 * @return Form_Field_Builder
	 */
	public function required(): self {
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'required' => true ) );
		return $this;
	}

	/**
	 * Set field description
	 *
	 * @param string $description Field description.
	 * @return Form_Field_Builder
	 */
	public function description( string $description ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'description' => $description ) );
		return $this;
	}

	/**
	 * Set field placeholder
	 *
	 * @param string $placeholder Field placeholder.
	 * @return Form_Field_Builder
	 */
	public function placeholder( string $placeholder ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'placeholder' => $placeholder ) );
		return $this;
	}

	/**
	 * Set field default value
	 *
	 * @param mixed $value Default value.
	 * @return Form_Field_Builder
	 */
	public function default( $value ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'default' => $value ) );
		return $this;
	}

	/**
	 * Set field options (for select, radio, checkbox)
	 *
	 * @param array $options Field options.
	 * @return Form_Field_Builder
	 */
	public function options( array $options ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'options' => $options ) );
		return $this;
	}

	/**
	 * Set field class
	 *
	 * @param string $class_name CSS class.
	 * @return Form_Field_Builder
	 */
	public function class( string $class_name ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'class' => $class_name ) );
		return $this;
	}

	/**
	 * Set field validation rules
	 *
	 * @param array $rules Validation rules.
	 * @return Form_Field_Builder
	 */
	public function rules( array $rules ): self {
		$field_config               = $this->form_builder->get_config()->get_field( $this->field_name ) ?? array();
		$field_config['validation'] = $rules;
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'validation' => $rules ) );
		return $this;
	}

	/**
	 * Set minimum length validation
	 *
	 * @param int $length Minimum length.
	 * @return Form_Field_Builder
	 */
	public function min_length( int $length ): self {
		$field_config             = $this->form_builder->get_config()->get_field( $this->field_name ) ?? array();
		$validation               = $field_config['validation'] ?? array();
		$validation['min_length'] = $length;
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'validation' => $validation ) );
		return $this;
	}

	/**
	 * Set maximum length validation
	 *
	 * @param int $length Maximum length.
	 * @return Form_Field_Builder
	 */
	public function max_length( int $length ): self {
		$field_config             = $this->form_builder->get_config()->get_field( $this->field_name ) ?? array();
		$validation               = $field_config['validation'] ?? array();
		$validation['max_length'] = $length;
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'validation' => $validation ) );
		return $this;
	}

	/**
	 * Set minimum value (for number, range, slider inputs)
	 *
	 * @param int $min Minimum value.
	 * @return Form_Field_Builder
	 */
	public function min( int $min ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'min' => $min ) );
		return $this;
	}

	/**
	 * Set maximum value (for number, range, slider inputs)
	 *
	 * @param int $max Maximum value.
	 * @return Form_Field_Builder
	 */
	public function max( int $max ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'max' => $max ) );
		return $this;
	}

	/**
	 * Set step value (for number, range, slider inputs)
	 *
	 * @param int $step Step value.
	 * @return Form_Field_Builder
	 */
	public function step( int $step ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'step' => $step ) );
		return $this;
	}

	/**
	 * Set numeric min/max
	 *
	 * @param int $min Minimum value.
	 * @param int $max Maximum value.
	 * @return Form_Field_Builder
	 */
	public function range( int $min, int $max ): self {
		$this->form_builder->get_config()->update_field(
			$this->field_name,
			array(
				'min' => $min,
				'max' => $max,
			)
		);
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
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'max_size' => $bytes ) );
		return $this;
	}

	/**
	 * Set textarea rows
	 *
	 * @param int $rows Number of rows.
	 * @return Form_Field_Builder
	 */
	public function rows( int $rows ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'rows' => $rows ) );
		return $this;
	}

	/**
	 * Validate that a method can only be called on file fields
	 *
	 * @param string $method_name Method name for error message.
	 * @throws \InvalidArgumentException If called on non-file field types.
	 */
	private function validate_file_field_method( string $method_name ): void {
		$field_config = $this->form_builder->get_config()->get_field( $this->field_name );

		if ( ! 'file' !== $field_config || $field_config['type'] ) {
			throw new \InvalidArgumentException(
				sprintf(
					'The %s method can only be used with file fields. Field "%s" is of type "%s".',
					$method_name,
					$this->field_name,
					$field_config['type'] ?? 'unknown'
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
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'accept' => $accept ) );
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
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'multiple_files' => true ) );
		return $this;
	}

	/**
	 * Set autocomplete attribute
	 *
	 * @param string $value Autocomplete value (e.g., 'email', 'name', 'organization').
	 * @return Form_Field_Builder
	 */
	public function autocomplete( string $value ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'autocomplete' => $value ) );
		return $this;
	}

	/**
	 * Add custom field attributes
	 *
	 * @param array $attributes Custom attributes.
	 * @return Form_Field_Builder
	 */
	public function attributes( array $attributes ): self {
		$field_config     = $this->form_builder->get_config()->get_field( $this->field_name ) ?? array();
		$field_attributes = $field_config['attributes'] ?? array();
		$field_attributes = array_merge( $field_attributes, $attributes );
		$this->form_builder->get_config()->update_field( $this->field_name, array( 'attributes' => $field_attributes ) );
		return $this;
	}

	/**
	 * End field configuration and return to form builder
	 *
	 * @return Form_Builder
	 */
	public function end(): Form_Builder {
		return $this->form_builder;
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
		return $this->form_builder->add( $name, $type, $label );
	}

	/**
	 * Magic method to proxy methods to parent Form_Builder or handle locally
	 *
	 * This allows seamless chaining from field configuration to form configuration
	 * without needing to call ->end() explicitly. Any method that exists on
	 * Form_Builder will be proxied automatically.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Method arguments.
	 * @return mixed Result of the method call.
	 *
	 * @throws \BadMethodCallException If method does not exist.
	 */
	public function __call( string $method, array $args ) {
		// First, check if this method exists on the parent Form_Builder.
		if ( method_exists( $this->form_builder, $method ) ) {
			// Proxy to Form_Builder for seamless chaining.
			return $this->form_builder->{$method}( ...$args );
		}

		// Check if this method exists on Form_Field_Builder itself.
		if ( method_exists( $this, $method ) ) {
			// Call the local method.
			return $this->{$method}( ...$args );
		}

		// Method not found anywhere.
		throw new \BadMethodCallException(
			esc_html( "Method '{$method}' does not exist on " . __CLASS__ . ' or ' . get_class( $this->form_builder ) )
		);
	}
}
