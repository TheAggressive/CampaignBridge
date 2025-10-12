<?php
/**
 * Form Builder - Fluent API for building forms
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

use CampaignBridge\Admin\Core\Forms\Form_Config;
use CampaignBridge\Admin\Core\Forms\Form_Field_Builder;
use CampaignBridge\Admin\Core\Forms\Form_Field_Manager;

/**
 * Form Builder - Provides fluent API for form configuration
 *
 * @package CampaignBridge\Admin\Core
 */
class Form_Builder {

	/**
	 * Form configuration instance
	 *
	 * @var Form_Config
	 */
	private Form_Config $config;

	/**
	 * Parent form instance
	 *
	 * @var \CampaignBridge\Admin\Core\Form
	 */
	private \CampaignBridge\Admin\Core\Form $form;

	/**
	 * Form field manager
	 *
	 * @var Form_Field_Manager
	 */
	private Form_Field_Manager $field_manager;

	/**
	 * Constructor
	 *
	 * @param Form_Config                     $config Form configuration instance.
	 * @param \CampaignBridge\Admin\Core\Form $form   Parent form instance.
	 */
	public function __construct( Form_Config $config, \CampaignBridge\Admin\Core\Form $form ) {
		$this->config        = $config;
		$this->form          = $form;
		$this->field_manager = new Form_Field_Manager( $config, $this );
	}

	/**
	 * Set form method
	 *
	 * @param string $method POST or GET.
	 * @return self
	 */
	public function method( string $method ): self {
		$this->config->set_method( $method );
		return $this;
	}

	/**
	 * Set form action
	 *
	 * @param string $action Form action URL.
	 * @return self
	 */
	public function action( string $action ): self {
		$this->config->set_action( $action );
		return $this;
	}

	/**
	 * Set data source
	 *
	 * @param string $source options, post_meta, custom.
	 * @return self
	 */
	public function source( string $source ): self {
		$this->config->set_source( $source );
		return $this;
	}

	/**
	 * Save to WordPress options
	 *
	 * @param string $prefix Option prefix.
	 * @return self
	 */
	/**
	 * Save form data to WordPress Options API
	 *
	 * @param string $prefix Optional prefix for option keys to prevent naming conflicts.
	 * @return self
	 */
	public function save_to_options( string $prefix = '' ): self {
		$this->config->set_save_method( 'options' );
		if ( $prefix ) {
			$this->config->set_prefix( $prefix );
		}
		return $this;
	}


	/**
	 * Save to post meta
	 *
	 * @param int $post_id Post ID.
	 * @return self
	 */
	/**
	 * Save form data to post meta
	 *
	 * @param int $post_id Optional post ID. Uses current post if not specified.
	 * @return self
	 */
	public function save_to_post_meta( int $post_id = 0 ): self {
		$this->config->set_save_method( 'post_meta' );
		if ( $post_id ) {
			$this->config->set_post_id( $post_id );
		}
		return $this;
	}


	/**
	 * Save form data to WordPress Settings API
	 *
	 * @param string $settings_group Settings group name for registering settings.
	 * @return self
	 */
	public function save_to_settings_api( string $settings_group = '' ): self {
		$this->config->set_save_method( 'settings' );
		$this->config->set( 'data_source', 'settings' );

		if ( $settings_group ) {
			$this->config->set( 'settings_group', $settings_group );
		}

		return $this;
	}

	/**
	 * Save form data using custom callback function
	 *
	 * The callback receives sanitized and validated form data and should return boolean success.
	 * Use this for external APIs, custom databases, or any non-standard storage.
	 *
	 * @param callable $callback Function that receives (array $data): bool.
	 * @return self
	 */
	public function save_to_custom( callable $callback ): self {
		$this->config->set_save_method( 'custom' );
		$this->config->add_hook( 'save_data', $callback );

		return $this;
	}

	/**
	 * Set table layout
	 *
	 * @return self
	 */
	public function table(): self {
		$this->config->set_layout( 'table' );
		return $this;
	}

	/**
	 * Set div layout
	 *
	 * @return self
	 */
	public function div(): self {
		$this->config->set_layout( 'div' );
		return $this;
	}

	/**
	 * Set custom layout
	 *
	 * @param callable $renderer Custom render function.
	 * @return self
	 */
	/**
	 * Add custom rendering callback for advanced layouts
	 *
	 * @param callable $renderer Custom rendering function.
	 * @return self
	 */
	public function render_custom( callable $renderer ): self {
		$this->config->set_layout( 'custom' );

		// Store render sequence for custom layouts.
		$render_sequence   = $this->config->get( 'render_sequence', array() );
		$render_sequence[] = array(
			'type'     => 'custom',
			'renderer' => $renderer,
		);
		$this->config->set( 'render_sequence', $render_sequence );

		return $this;
	}

	/**
	 * Set success message
	 *
	 * @param string $message Success message.
	 * @return self
	 */
	public function success( string $message ): self {
		$this->config->set_success_message( $message );
		return $this;
	}

	/**
	 * Set error message
	 *
	 * @param string $message Error message.
	 * @return self
	 */
	public function error( string $message ): self {
		$this->config->set_error_message( $message );
		return $this;
	}

	/**
	 * Set option prefix
	 *
	 * @param string $prefix Prefix for option keys.
	 * @return self
	 */
	public function prefix( string $prefix ): self {
		$this->config->set_prefix( $prefix );
		return $this;
	}

	/**
	 * Set option suffix
	 *
	 * @param string $suffix Suffix for option keys.
	 * @return self
	 */
	public function suffix( string $suffix ): self {
		$this->config->set_suffix( $suffix );
		return $this;
	}

	/**
	 * Set submit button
	 *
	 * @param string $text Button text.
	 * @param string $type Button type (primary, secondary).
	 * @return self
	 */
	public function submit( string $text = 'Save', string $type = 'primary' ): self {
		$this->config->set_submit_button( $text, $type );
		return $this;
	}

	/**
	 * Add a lifecycle hook
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Hook callback.
	 * @return self
	 */
	public function on( string $hook, callable $callback ): self {
		$this->config->add_hook( $hook, $callback );
		return $this;
	}

	/**
	 * Add before save hook
	 *
	 * @param callable $callback Hook callback.
	 * @return self
	 */
	public function before_save( callable $callback ): self {
		return $this->on( 'before_save', $callback );
	}

	/**
	 * Add after save hook
	 *
	 * @param callable $callback Hook callback.
	 * @return self
	 */
	public function after_save( callable $callback ): self {
		return $this->on( 'after_save', $callback );
	}

	/**
	 * Add before validate hook
	 *
	 * @param callable $callback Hook callback.
	 * @return self
	 */
	public function before_validate( callable $callback ): self {
		return $this->on( 'before_validate', $callback );
	}

	/**
	 * Add after validate hook
	 *
	 * @param callable $callback Hook callback.
	 * @return self
	 */
	public function after_validate( callable $callback ): self {
		return $this->on( 'after_validate', $callback );
	}

	/**
	 * Add on success hook
	 *
	 * @param callable $callback Hook callback.
	 * @return self
	 */
	public function on_success( callable $callback ): self {
		return $this->on( 'on_success', $callback );
	}

	/**
	 * Add on error hook
	 *
	 * @param callable $callback Hook callback.
	 * @return self
	 */
	public function on_error( callable $callback ): self {
		return $this->on( 'on_error', $callback );
	}

	/**
	 * Set form description
	 *
	 * @param string $description Form description text.
	 * @return self
	 */
	public function description( string $description ): self {
		$this->config->set_description( $description );
		return $this;
	}

	/**
	 * Enable file uploads by setting multipart form encoding
	 *
	 * @return self
	 */
	public function enable_file_uploads(): self {
		$this->config->set_multipart_encoding();
		return $this;
	}


	/**
	 * Add a text field
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function text( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'text', $label );
	}

	/**
	 * Add an email field
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function email( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'email', $label );
	}

	/**
	 * Add a password field
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function password( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'password', $label );
	}

	/**
	 * Add a URL field
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function url( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'url', $label );
	}

	/**
	 * Add a number field
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function number( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'number', $label );
	}

	/**
	 * Add a textarea field
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function textarea( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'textarea', $label );
	}

	/**
	 * Add a select field
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function select( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'select', $label );
	}

	/**
	 * Add a radio field
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function radio( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'radio', $label );
	}

	/**
	 * Add a checkbox field
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function checkbox( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'checkbox', $label );
	}

	/**
	 * Add a file field
	 *
	 * @param string      $name Field name.
	 * @param string      $label Field label.
	 * @param string|null $accept Optional accept attribute for file types.
	 * @return Form_Field_Builder
	 */
	public function file( string $name, string $label = '', ?string $accept = null ): Form_Field_Builder {
		// Automatically enable file uploads (multipart encoding) when file fields are added.
		$this->enable_file_uploads();
		$field_builder = $this->add_field( $name, 'file', $label );

		// If accept parameter provided, set it automatically for convenience.
		if ( null !== $accept ) {
			$field_builder->accept( $accept );
		}

		return $field_builder;
	}

	/**
	 * Add a WYSIWYG field
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function wysiwyg( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'wysiwyg', $label );
	}

	/**
	 * Add a switch/toggle field (styled checkbox)
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function switch( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'switch', $label );
	}

	/**
	 * Add a toggle field (alias for switch)
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function toggle( string $name, string $label = '' ): Form_Field_Builder {
		return $this->switch( $name, $label );
	}

	/**
	 * Add a range/slider field
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function range( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'range', $label );
	}

	/**
	 * Add a slider field (alias for range)
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function slider( string $name, string $label = '' ): Form_Field_Builder {
		return $this->range( $name, $label );
	}

	/**
	 * Add a color picker field
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function color( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'color', $label );
	}

	/**
	 * Add a date picker field
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function date( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'date', $label );
	}

	/**
	 * Add a time picker field
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function time( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'time', $label );
	}

	/**
	 * Add a datetime picker field
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function datetime( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'datetime-local', $label );
	}

	/**
	 * Add a telephone field
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function tel( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'tel', $label );
	}

	/**
	 * Add a search field
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function search( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'search', $label );
	}

	/**
	 * Add a hidden field
	 *
	 * @param string $name  Field name.
	 * @param string $value Field value.
	 * @return Form_Field_Builder
	 */
	public function hidden( string $name, string $value = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'hidden', '' )->default( $value );
	}

	/**
	 * Add a field with custom type
	 *
	 * @param string $name Field name.
	 * @param string $type Field type.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function add( string $name, string $type, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, $type, $label );
	}

	/**
	 * Create multiple fields with smart state management (repeater pattern)
	 *
	 * @param string $field_id             Base field name.
	 * @param array  $populate_all_choices All possible options [key => label].
	 * @param mixed  $persistent_data      Current state data (string, array, or null).
	 * @return Forms\Form_Field_Repeater
	 * @throws \InvalidArgumentException When validation fails.
	 */
	public function repeater( string $field_id, array $populate_all_choices, $persistent_data = null ): Forms\Form_Field_Repeater {
		return new Forms\Form_Field_Repeater( $this, $field_id, $populate_all_choices, $persistent_data );
	}

	/**
	 * Add a field (internal)
	 *
	 * @param string $name Field name.
	 * @param string $type Field type.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	private function add_field( string $name, string $type, string $label = '' ): Form_Field_Builder {
		return $this->field_manager->add_field( $name, $type, $label );
	}

	/**
	 * Check if form was submitted
	 *
	 * @return bool
	 */
	public function submitted(): bool {
		return $this->form->submitted();
	}

	/**
	 * Check if form is valid
	 *
	 * @return bool
	 */
	public function valid(): bool {
		return $this->form->valid();
	}

	/**
	 * Get form errors
	 *
	 * @return array
	 */
	public function errors(): array {
		return $this->form->errors();
	}

	/**
	 * Get form success messages
	 *
	 * @return array
	 */
	public function messages(): array {
		return $this->form->messages();
	}

	/**
	 * Get form data
	 *
	 * @param string $key Optional field key.
	 * @return mixed
	 */
	public function data( string $key = '' ) {
		return $this->form->data( $key );
	}

	/**
	 * Render the form
	 *
	 * @return void
	 */
	public function render(): void {
		// Auto-detect multipart encoding based on field types.
		$this->config->auto_detect_multipart_encoding();

		$this->form->render();
	}

	/**
	 * Get the form configuration
	 *
	 * @return Form_Config
	 */
	public function get_config(): Form_Config {
		return $this->config;
	}


	/**
	 * Get the parent form instance
	 *
	 * @return \CampaignBridge\Admin\Core\Form
	 */
	public function get_form(): \CampaignBridge\Admin\Core\Form {
		return $this->form;
	}
}
