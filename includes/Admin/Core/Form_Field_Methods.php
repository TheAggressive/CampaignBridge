<?php
/**
 * Form Field Methods Trait - Provides fluent API for field creation
 *
 * Contains all field creation methods to reduce duplication in Form class
 * while maintaining perfect static analysis compatibility.
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

use CampaignBridge\Admin\Core\Forms\Form_Field_Builder;

/**
 * Form Field Methods Trait
 *
 * @package CampaignBridge\Admin\Core
 */
trait Form_Field_Methods {
	/**
	 * Create a text field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function text( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->text( $name, $label );
	}

	/**
	 * Create an email field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function email( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->email( $name, $label );
	}

	/**
	 * Create a password field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function password( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->password( $name, $label );
	}

	/**
	 * Create a URL field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function url( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->url( $name, $label );
	}

	/**
	 * Create a number field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function number( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->number( $name, $label );
	}

	/**
	 * Create a textarea field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function textarea( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->textarea( $name, $label );
	}

	/**
	 * Create a select field.
	 *
	 * @param string               $name    Field name.
	 * @param string               $label   Field label.
	 * @param array<string, mixed> $options Field options.
	 * @return Form_Field_Builder
	 */
	public function select( string $name, string $label = '', array $options = array() ): Form_Field_Builder {
		return $this->builder->select( $name, $label, $options );
	}

	/**
	 * Create a radio field.
	 *
	 * @param string               $name    Field name.
	 * @param string               $label   Field label.
	 * @param array<string, mixed> $options Field options.
	 * @return Form_Field_Builder
	 */
	public function radio( string $name, string $label = '', array $options = array() ): Form_Field_Builder {
		return $this->builder->radio( $name, $label, $options );
	}

	/**
	 * Create a checkbox field.
	 *
	 * @param string               $name    Field name.
	 * @param string               $label   Field label.
	 * @param array<string, mixed> $options Field options.
	 * @return Form_Field_Builder
	 */
	public function checkbox( string $name, string $label = '', array $options = array() ): Form_Field_Builder {
		return $this->builder->checkbox( $name, $label, $options );
	}

	/**
	 * Add a file field to the form.
	 *
	 * @param string      $name   Field name.
	 * @param string      $label  Field label.
	 * @param string|null $accept Accepted file types.
	 * @return Form_Field_Builder
	 */
	public function file( string $name, string $label = '', ?string $accept = null ): Form_Field_Builder {
		return $this->builder->file( $name, $label, $accept );
	}

	/**
	 * Create a WYSIWYG field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function wysiwyg( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->wysiwyg( $name, $label );
	}

	/**
	 * Create a switch field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function switch( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->switch( $name, $label );
	}

	/**
	 * Create a toggle field (alias for switch).
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function toggle( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->toggle( $name, $label );
	}

	/**
	 * Create a range/slider field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function range( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->range( $name, $label );
	}

	/**
	 * Create a slider field (alias for range).
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function slider( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->slider( $name, $label );
	}

	/**
	 * Create a color picker field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function color( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->color( $name, $label );
	}

	/**
	 * Create a date picker field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function date( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->date( $name, $label );
	}

	/**
	 * Create a time picker field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function time( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->time( $name, $label );
	}

	/**
	 * Create a datetime picker field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function datetime( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->datetime( $name, $label );
	}

	/**
	 * Create a telephone field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function tel( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->tel( $name, $label );
	}

	/**
	 * Create a search field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function search( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->search( $name, $label );
	}

	/**
	 * Create a hidden field.
	 *
	 * @param string $name  Field name.
	 * @param string $value Field value.
	 * @return Form_Field_Builder
	 */
	public function hidden( string $name, string $value = '' ): Form_Field_Builder {
		return $this->builder->hidden( $name, $value );
	}

	/**
	 * Create an encrypted field for sensitive data.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function encrypted( string $name, string $label = '' ): Form_Field_Builder {
		return $this->builder->encrypted( $name, $label );
	}

	/**
	 * Create a field with custom type.
	 *
	 * @param string $name  Field name.
	 * @param string $type  Field type.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function add( string $name, string $type, string $label = '' ): Form_Field_Builder {
		return $this->builder->add( $name, $type, $label );
	}

	/**
	 * Create multiple fields with smart state management (repeater pattern).
	 *
	 * @param string                $field_id             Base field name.
	 * @param array<string, string> $populate_all_choices All possible options [key => label].
	 * @param mixed                 $persistent_data      Current state data (string, array, or null).
	 * @return Forms\Form_Field_Repeater
	 * @throws \InvalidArgumentException When validation fails.
	 */
	public function repeater( string $field_id, array $populate_all_choices, $persistent_data = null ): Forms\Form_Field_Repeater {
		return $this->builder->repeater( $field_id, $populate_all_choices, $persistent_data );
	}

	/**
	 * Create an information display field (display-only).
	 *
	 * @param string $label The label text.
	 * @param string $value The value to display.
	 * @return Form_Field_Builder
	 */
	public function info( string $label, string $value ): Form_Field_Builder {
		return $this->builder->info( $label, $value );
	}
}
