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
	 * Currently open field builder (for ->end() removal)
	 *
	 * @var Form_Field_Builder|null
	 */
	private ?Form_Field_Builder $current_field = null;

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
	 * Automatically close any open field
	 *
	 * @return void
	 */
	private function auto_close_field(): void {
		if ( $this->current_field ) {
			// The field is automatically closed when we start a new field or call form methods.
			$this->current_field = null;
		}
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
	 * Save form data to WordPress Options API
	 *
	 * @param string $prefix Optional prefix for option keys. Defaults to 'campaignbridge_{form_id}_' if empty.
	 * @return self
	 */
	public function save_to_options( string $prefix = '' ): self {
		$this->auto_close_field(); // Close any open field.
		$this->config->set_save_method( 'options' );

		// If no prefix provided, use default: campaignbridge_{form_id}_.
		if ( empty( $prefix ) ) {
			$form_id = $this->config->get( 'form_id', 'form' );
			$prefix  = 'campaignbridge_' . $form_id . '_';
		}

		$this->config->set_prefix( $prefix );
		return $this;
	}

	/**
	 * Save form data to post meta
	 *
	 * @param int $post_id Optional post ID. Uses current post if not specified.
	 * @return self
	 */
	public function save_to_post_meta( int $post_id = 0 ): self {
		$this->auto_close_field(); // Close any open field.
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
		$this->auto_close_field(); // Close any open field.
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
		$this->auto_close_field(); // Close any open field.
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
	 * Auto-detect optimal layout based on context
	 *
	 * @return self
	 */
	public function auto_layout(): self {
		$layout = $this->detect_optimal_layout();
		$this->config->set_layout( $layout );
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
	public function success( string $message = '' ): self {
		$this->auto_close_field(); // Close any open field.

		// Auto-generate success message if not provided.
		if ( empty( $message ) ) {
			$message = $this->generate_smart_success_message();
		}

		$this->config->set_success_message( $message );
		return $this;
	}

	/**
	 * Set error message
	 *
	 * @param string $message Error message.
	 * @return self
	 */
	public function error( string $message = '' ): self {
		$this->auto_close_field(); // Close any open field.

		// Auto-generate error message if not provided.
		if ( empty( $message ) ) {
			$message = $this->generate_smart_error_message();
		}

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
	public function submit( string $text = '', string $type = 'primary' ): self {
		$this->auto_close_field(); // Close any open field.

		// Auto-generate submit text if not provided.
		if ( empty( $text ) ) {
			$text = $this->generate_smart_submit_text();
		}

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
	 * @param string               $name    Field name.
	 * @param string               $label   Field label.
	 * @param array<string, mixed> $options Field options (optional).
	 * @return Form_Field_Builder
	 */
	public function select( string $name, string $label = '', array $options = array() ): Form_Field_Builder {
		$field_builder = $this->add_field( $name, 'select', $label );

		// Set options if provided.
		if ( ! empty( $options ) ) {
			$field_builder->options( $options );
		}

		return $field_builder;
	}

	/**
	 * Add a radio field
	 *
	 * @param string               $name    Field name.
	 * @param string               $label   Field label.
	 * @param array<string, mixed> $options Field options (optional).
	 * @return Form_Field_Builder
	 */
	public function radio( string $name, string $label = '', array $options = array() ): Form_Field_Builder {
		$field_builder = $this->add_field( $name, 'radio', $label );

		// Set options if provided.
		if ( ! empty( $options ) ) {
			$field_builder->options( $options );
		}

		return $field_builder;
	}

	/**
	 * Add a checkbox field
	 *
	 * @param string               $name    Field name.
	 * @param string               $label   Field label.
	 * @param array<string, mixed> $options Field options (optional).
	 * @return Form_Field_Builder
	 */
	public function checkbox( string $name, string $label = '', array $options = array() ): Form_Field_Builder {
		$field_builder = $this->add_field( $name, 'checkbox', $label );

		// Set options if provided.
		if ( ! empty( $options ) ) {
			$field_builder->options( $options );
		}

		return $field_builder;
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
	 * Add an information display field (display-only)
	 *
	 * @param string $label The label text.
	 * @param string $value The value to display.
	 * @return Form_Field_Builder
	 */
	public function info( string $label, string $value ): Form_Field_Builder {
		return $this->add_field( 'info_' . uniqid(), 'info', $label )->default( $value );
	}

	/**
	 * Add an encrypted field for sensitive data
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function encrypted( string $name, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, 'encrypted', $label );
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
	 * @param string                $field_id             Base field name.
	 * @param array<string, string> $populate_all_choices All possible options [key => label].
	 * @param mixed                 $persistent_data      Current state data (string, array, or null).
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
		// Automatically close any open field before starting a new one.
		$this->auto_close_field();

		// Smart field type auto-detection.
		$detected_type = $this->auto_detect_field_type( $name, $type );
		if ( $detected_type !== $type ) {
			$type = $detected_type;
		}

		$field_builder       = $this->field_manager->add_field( $name, $type, $label );
		$this->current_field = $field_builder;

		// Auto-generate label if not provided.
		if ( empty( $label ) ) {
			$label = $this->generate_smart_label( $name );
			$field_builder->label( $label );
		}

		// Auto-add smart validation rules.
		$this->add_smart_validation( $name, $type, $field_builder );

		return $field_builder;
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

	/**
	 * Get all form fields
	 *
	 * @return array<string, mixed> Array of field configurations.
	 */
	public function get_fields(): array {
		return $this->config->get_fields();
	}

	/**
	 * Render form opening tag and initialize form
	 *
	 * Includes form opening, security setup, and message rendering.
	 *
	 * @return void
	 */
	public function form_start(): void {
		$this->form->ensure_initialized();

		// Handle form submission processing (same as automatic render()).
		$this->form->prepare_for_rendering();

		// Auto-detect multipart encoding based on field types.
		$this->config->auto_detect_multipart_encoding();

		// Render form opening tag.
		$this->form->get_renderer()->render_form_open();

		// Note: Validation moved to form_end() so it happens after render_field() calls.
	}

	/**
	 * Render a specific field by name
	 *
	 * @param string $field_name The name of the field to render.
	 * @throws \InvalidArgumentException If the field does not exist.
	 * @return void
	 */
	public function render_field( string $field_name ): void {
		$fields = $this->config->get_fields();

		if ( ! isset( $fields[ $field_name ] ) ) {
			throw new \InvalidArgumentException( esc_html( "Field '{$field_name}' does not exist in form configuration" ) );
		}

		$this->form->ensure_renderer();

		$this->form->get_renderer()->render_field( $field_name, $fields[ $field_name ] );

		// Track that this field was rendered for validation purposes.
		$this->form->add_rendered_field( $field_name );
	}

	/**
	 * Render form closing tag
	 *
	 * Closes the form element. Submit button should be rendered separately
	 * using the render_submit() method.
	 *
	 * @return void
	 */
	public function form_end(): void {
		$this->form->ensure_renderer();

		// Render form closing tag.
		$this->form->get_renderer()->render_form_close();
	}

	/**
	 * Render submit button
	 *
	 * @return void
	 */
	public function render_submit(): void {
		$this->form->ensure_renderer();
		$this->form->get_renderer()->render_submit_button();
	}

	/**
	 * Auto-detect field type based on field name patterns
	 *
	 * @param string $name Field name.
	 * @param string $current_type Current field type.
	 * @return string Detected or original field type.
	 */
	private function auto_detect_field_type( string $name, string $current_type ): string {
		// Only auto-detect if type is 'text' (generic).
		if ( 'text' !== $current_type ) {
			return $current_type;
		}

		$name_lower = strtolower( $name );

		// Field type detection patterns.
		$patterns = array(
			'email'    => array( 'email' ),
			'url'      => array( 'url', 'link' ),
			'password' => array( 'password', 'pass' ),
			'tel'      => array( 'phone', 'tel', 'mobile' ),
			'number'   => array( 'count', 'amount', 'quantity' ),
		);

		foreach ( $patterns as $type => $keywords ) {
			foreach ( $keywords as $keyword ) {
				if ( str_contains( $name_lower, $keyword ) ) {
					return $type;
				}
			}
		}

		return $current_type;
	}

	/**
	 * Generate smart label from field name
	 *
	 * @param string $name Field name.
	 * @return string Generated label.
	 */
	private function generate_smart_label( string $name ): string {
		// Convert snake_case or camelCase to Title Case.
		$label = preg_replace( '/([a-z])([A-Z])/', '$1 $2', $name );
		$label = str_replace( '_', ' ', $label );
		$label = ucwords( strtolower( $label ) );

		// Special cases.
		$replacements = array(
			'Api'   => 'API',
			'Url'   => 'URL',
			'Id'    => 'ID',
			'Smtp'  => 'SMTP',
			'Http'  => 'HTTP',
			'Https' => 'HTTPS',
			'Json'  => 'JSON',
			'Xml'   => 'XML',
			'Html'  => 'HTML',
			'Css'   => 'CSS',
			'Js'    => 'JS',
		);

		foreach ( $replacements as $search => $replace ) {
			$label = str_replace( $search, $replace, $label );
		}

		return $label;
	}

	/**
	 * Detect optimal layout based on context
	 *
	 * @return string Layout type.
	 */
	private function detect_optimal_layout(): string {
		$form_id = $this->config->get( 'form_id', '' );

		// Admin screens typically work better with div layout for flexibility.
		if ( is_admin() ) {
			return 'div';
		}

		// Settings forms often work better with table layout for alignment.
		if ( str_contains( $form_id, 'settings' ) || str_contains( $form_id, 'config' ) ) {
			return 'table';
		}

		// Default to div layout for modern, flexible styling.
		return 'div';
	}

	/**
	 * Generate smart submit button text based on form context
	 *
	 * @return string Submit button text.
	 */
	private function generate_smart_submit_text(): string {
		$form_id     = $this->config->get( 'form_id', '' );
		$save_method = $this->config->get( 'save_method', 'options' );

		// Settings forms.
		if ( str_contains( $form_id, 'settings' ) || str_contains( $form_id, 'config' ) ) {
			return __( 'Save Settings', 'campaignbridge' );
		}

		// Profile/user forms.
		if ( str_contains( $form_id, 'profile' ) || str_contains( $form_id, 'user' ) ) {
			return __( 'Update Profile', 'campaignbridge' );
		}

		// API/Integration forms.
		if ( str_contains( $form_id, 'api' ) || str_contains( $form_id, 'integration' ) ) {
			return __( 'Save Configuration', 'campaignbridge' );
		}

		// Post meta forms.
		if ( 'post_meta' === $save_method ) {
			return __( 'Save Changes', 'campaignbridge' );
		}

		// Default fallback.
		return __( 'Save', 'campaignbridge' );
	}

	/**
	 * Generate smart success message based on form context
	 *
	 * @return string Success message.
	 */
	private function generate_smart_success_message(): string {
		$form_id = $this->config->get( 'form_id', '' );

		// Settings forms.
		if ( str_contains( $form_id, 'settings' ) || str_contains( $form_id, 'config' ) ) {
			return __( 'Settings saved successfully!', 'campaignbridge' );
		}

		// Profile/user forms.
		if ( str_contains( $form_id, 'profile' ) || str_contains( $form_id, 'user' ) ) {
			return __( 'Profile updated successfully!', 'campaignbridge' );
		}

		// API/Integration forms.
		if ( str_contains( $form_id, 'api' ) || str_contains( $form_id, 'integration' ) ) {
			return __( 'Configuration saved successfully!', 'campaignbridge' );
		}

		// Default fallback.
		return __( 'Saved successfully!', 'campaignbridge' );
	}

	/**
	 * Generate smart error message based on form context
	 *
	 * @return string Error message.
	 */
	private function generate_smart_error_message(): string {
		$form_id = $this->config->get( 'form_id', '' );

		// Settings forms.
		if ( str_contains( $form_id, 'settings' ) || str_contains( $form_id, 'config' ) ) {
			return __( 'Failed to save settings. Please try again.', 'campaignbridge' );
		}

		// Profile/user forms.
		if ( str_contains( $form_id, 'profile' ) || str_contains( $form_id, 'user' ) ) {
			return __( 'Failed to update profile. Please try again.', 'campaignbridge' );
		}

		// API/Integration forms.
		if ( str_contains( $form_id, 'api' ) || str_contains( $form_id, 'integration' ) ) {
			return __( 'Failed to save configuration. Please try again.', 'campaignbridge' );
		}

		// Default fallback.
		return __( 'An error occurred. Please try again.', 'campaignbridge' );
	}

	/**
	 * Add smart validation rules based on field type and name
	 *
	 * @param string             $name Field name.
	 * @param string             $type Field type.
	 * @param Form_Field_Builder $field_builder Field builder instance.
	 * @return void
	 */
	private function add_smart_validation( string $name, string $type, Form_Field_Builder $field_builder ): void {
		$name_lower = strtolower( $name );

		// Type-based validation rules.
		$type_rules = array(
			'email'    => array( array( 'email', true ) ),
			'url'      => array( array( 'url', true ) ),
			'password' => array( array( 'min_length', 8 ) ), // Only for non-confirm passwords.
		);

		// Apply type-based rules.
		if ( isset( $type_rules[ $type ] ) ) {
			foreach ( $type_rules[ $type ] as $rule ) {
				if ( 'password' === $type && str_contains( $name_lower, 'confirm' ) ) {
					continue; // Skip password rules for confirm fields.
				}
				$field_builder->validation( $rule[0], $rule[1] );
			}
		}

		// Name-based validation rules.
		$name_rules = array(
			array( 'required', 'mandatory', array( 'required', true ) ),
			array( 'api_key', 'apikey', array( 'min_length', 10 ) ),
			array(
				'timeout',
				null,
				array(
					array( 'numeric', true ),
					array( 'min', 1 ),
					array( 'max', 300 ),
				),
			),
		);

		// Apply name-based rules.
		foreach ( $name_rules as $rule_config ) {
			list( $keyword1, $keyword2, $validations ) = $rule_config;

			$matches = str_contains( $name_lower, $keyword1 );
			if ( $keyword2 ) {
				$matches = $matches || str_contains( $name_lower, $keyword2 );
			}

			if ( $matches ) {
				if ( is_array( $validations[0] ) ) {
					// Multiple validations.
					foreach ( $validations as $validation ) {
						$field_builder->validation( $validation[0], $validation[1] );
					}
				} else {
					// Single validation.
					$field_builder->validation( $validations[0], $validations[1] );
				}
			}
		}
	}
}
