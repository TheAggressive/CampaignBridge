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
	public function options( string $prefix = '' ): self {
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
	public function meta( int $post_id = 0 ): self {
		$this->config->set_save_method( 'post_meta' );
		if ( $post_id ) {
			$this->config->set_post_id( $post_id );
		}
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
	public function custom( callable $renderer ): self {
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
	 * Set form enctype for file uploads
	 *
	 * @return self
	 */
	public function multipart(): self {
		$this->config->set_multipart();
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
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function file( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'file', $label );
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
