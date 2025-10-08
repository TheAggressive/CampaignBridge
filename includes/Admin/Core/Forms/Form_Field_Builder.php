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
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'label' => $label ] );
		return $this;
	}

	/**
	 * Set field as required
	 *
	 * @return Form_Field_Builder
	 */
	public function required(): self {
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'required' => true ] );
		return $this;
	}

	/**
	 * Set field description
	 *
	 * @param string $description Field description.
	 * @return Form_Field_Builder
	 */
	public function description( string $description ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'description' => $description ] );
		return $this;
	}

	/**
	 * Set field placeholder
	 *
	 * @param string $placeholder Field placeholder.
	 * @return Form_Field_Builder
	 */
	public function placeholder( string $placeholder ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'placeholder' => $placeholder ] );
		return $this;
	}

	/**
	 * Set field default value
	 *
	 * @param mixed $default Default value.
	 * @return Form_Field_Builder
	 */
	public function default( $default ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'default' => $default ] );
		return $this;
	}

	/**
	 * Set field options (for select, radio, checkbox)
	 *
	 * @param array $options Field options.
	 * @return Form_Field_Builder
	 */
	public function options( array $options ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'options' => $options ] );
		return $this;
	}

	/**
	 * Set field class
	 *
	 * @param string $class CSS class.
	 * @return Form_Field_Builder
	 */
	public function class( string $class ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'class' => $class ] );
		return $this;
	}

	/**
	 * Set field validation rules
	 *
	 * @param array $rules Validation rules.
	 * @return Form_Field_Builder
	 */
	public function rules( array $rules ): self {
		$field_config               = $this->form_builder->get_config()->get_field( $this->field_name ) ?? [];
		$field_config['validation'] = $rules;
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'validation' => $rules ] );
		return $this;
	}

	/**
	 * Set minimum length validation
	 *
	 * @param int $length Minimum length.
	 * @return Form_Field_Builder
	 */
	public function min_length( int $length ): self {
		$field_config             = $this->form_builder->get_config()->get_field( $this->field_name ) ?? [];
		$validation               = $field_config['validation'] ?? [];
		$validation['min_length'] = $length;
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'validation' => $validation ] );
		return $this;
	}

	/**
	 * Set maximum length validation
	 *
	 * @param int $length Maximum length.
	 * @return Form_Field_Builder
	 */
	public function max_length( int $length ): self {
		$field_config             = $this->form_builder->get_config()->get_field( $this->field_name ) ?? [];
		$validation               = $field_config['validation'] ?? [];
		$validation['max_length'] = $length;
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'validation' => $validation ] );
		return $this;
	}

	/**
	 * Set minimum value (for number, range, slider inputs)
	 *
	 * @param int $min Minimum value.
	 * @return Form_Field_Builder
	 */
	public function min( int $min ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'min' => $min ] );
		return $this;
	}

	/**
	 * Set maximum value (for number, range, slider inputs)
	 *
	 * @param int $max Maximum value.
	 * @return Form_Field_Builder
	 */
	public function max( int $max ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'max' => $max ] );
		return $this;
	}

	/**
	 * Set step value (for number, range, slider inputs)
	 *
	 * @param int $step Step value.
	 * @return Form_Field_Builder
	 */
	public function step( int $step ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'step' => $step ] );
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
		$this->form_builder->get_config()->update_field( $this->field_name,
			[
				'min' => $min,
				'max' => $max,
        ] );
		return $this;
	}

	/**
	 * Set textarea rows
	 *
	 * @param int $rows Number of rows.
	 * @return Form_Field_Builder
	 */
	public function rows( int $rows ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'rows' => $rows ] );
		return $this;
	}

	/**
	 * Set file accept types
	 *
	 * @param string $accept Accept attribute value.
	 * @return Form_Field_Builder
	 */
	public function accept( string $accept ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'accept' => $accept ] );
		return $this;
	}

	/**
	 * Set autocomplete attribute
	 *
	 * @param string $value Autocomplete value (e.g., 'email', 'name', 'organization').
	 * @return Form_Field_Builder
	 */
	public function autocomplete( string $value ): self {
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'autocomplete' => $value ] );
		return $this;
	}

	/**
	 * Add custom field attributes
	 *
	 * @param array $attributes Custom attributes.
	 * @return Form_Field_Builder
	 */
	public function attributes( array $attributes ): self {
		$field_config     = $this->form_builder->get_config()->get_field( $this->field_name ) ?? [];
		$field_attributes = $field_config['attributes'] ?? [];
		$field_attributes = array_merge( $field_attributes, $attributes );
		$this->form_builder->get_config()->update_field( $this->field_name, [ 'attributes' => $field_attributes ] );
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
}
