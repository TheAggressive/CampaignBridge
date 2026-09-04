<?php
/**
 * Fluent field factories exposed by Form_Builder.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Core\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps the field-factory surface separate from form orchestration. */
trait Form_Builder_Fields {
	/**
	 * Add a text field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function text( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'text', $label );
	}

	/**
	 * Add an email field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function email( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'email', $label );
	}

	/**
	 * Add a password field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function password( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'password', $label );
	}

	/**
	 * Add a URL field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function url( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'url', $label );
	}

	/**
	 * Add a number field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function number( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'number', $label );
	}

	/**
	 * Add a textarea field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function textarea( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'textarea', $label );
	}

	/**
	 * Add a select field.
	 *
	 * @param string               $name Field name.
	 * @param string               $label Field label.
	 * @param array<string, mixed> $options Field options.
	 */
	public function select( string $name, string $label = '', array $options = array() ): Form_Field_Builder {
		return $this->field_with_options( $name, 'select', $label, $options );
	}

	/**
	 * Add a radio field.
	 *
	 * @param string               $name Field name.
	 * @param string               $label Field label.
	 * @param array<string, mixed> $options Field options.
	 */
	public function radio( string $name, string $label = '', array $options = array() ): Form_Field_Builder {
		return $this->field_with_options( $name, 'radio', $label, $options );
	}

	/**
	 * Add a checkbox field.
	 *
	 * @param string               $name Field name.
	 * @param string               $label Field label.
	 * @param array<string, mixed> $options Field options.
	 */
	public function checkbox( string $name, string $label = '', array $options = array() ): Form_Field_Builder {
		return $this->field_with_options( $name, 'checkbox', $label, $options );
	}

	/**
	 * Add a file field and enable multipart encoding.
	 *
	 * @param string      $name Field name.
	 * @param string      $label Field label.
	 * @param string|null $accept Accepted MIME types.
	 */
	public function file( string $name, string $label = '', ?string $accept = null ): Form_Field_Builder {
		$this->enable_file_uploads();
		$field = $this->add_field( $name, 'file', $label );

		if ( null !== $accept ) {
			$field->accept( $accept );
		}

		return $field;
	}

	/**
	 * Add a WYSIWYG field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function wysiwyg( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'wysiwyg', $label );
	}

	/**
	 * Add a switch field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function switch( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'switch', $label );
	}

	/**
	 * Add a toggle alias for a switch field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function toggle( string $name, string $label = '' ): Form_Field_Builder {
		return $this->switch( $name, $label );
	}

	/**
	 * Add a range field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function range( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'range', $label );
	}

	/**
	 * Add a slider alias for a range field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function slider( string $name, string $label = '' ): Form_Field_Builder {
		return $this->range( $name, $label );
	}

	/**
	 * Add a color field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function color( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'color', $label );
	}

	/**
	 * Add a date field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function date( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'date', $label );
	}

	/**
	 * Add a time field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function time( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'time', $label );
	}

	/**
	 * Add a local datetime field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function datetime( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'datetime-local', $label );
	}

	/**
	 * Add a telephone field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function tel( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'tel', $label );
	}

	/**
	 * Add a search field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function search( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'search', $label );
	}

	/**
	 * Add a hidden field.
	 *
	 * @param string $name Field name.
	 * @param string $value Field value.
	 */
	public function hidden( string $name, string $value = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'hidden', '' )->default( $value );
	}

	/**
	 * Add a display-only information field.
	 *
	 * @param string $label Field label.
	 * @param string $value Display value.
	 */
	public function info( string $label, string $value ): Form_Field_Builder {
		return $this->add_field( 'info_' . uniqid(), 'info', $label )->default( $value );
	}

	/**
	 * Add an encrypted field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 */
	public function encrypted( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'encrypted', $label );
	}

	/**
	 * Add a custom field type.
	 *
	 * @param string $name Field name.
	 * @param string $type Field type.
	 * @param string $label Field label.
	 */
	public function add( string $name, string $type, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, $type, $label );
	}

	/**
	 * Add a repeater field group.
	 *
	 * @param string                $field_id Field identifier.
	 * @param array<string, string> $choices Available choices.
	 * @param mixed                 $persistent_data Persisted values.
	 */
	public function repeater( string $field_id, array $choices, mixed $persistent_data = null ): Form_Field_Repeater {
		return new Form_Field_Repeater( $this, $field_id, $choices, $persistent_data );
	}

	/**
	 * Add a choice field and populate its options.
	 *
	 * @param string               $name Field name.
	 * @param string               $type Field type.
	 * @param string               $label Field label.
	 * @param array<string, mixed> $options Field options.
	 */
	private function field_with_options( string $name, string $type, string $label, array $options ): Form_Field_Builder {
		$field = $this->add_field( $name, $type, $label );
		if ( ! empty( $options ) ) {
			$field->options( $options );
		}

		return $field;
	}
}
