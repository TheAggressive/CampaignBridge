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
use CampaignBridge\Admin\Core\Forms\Form_Builder_Intelligence;
use CampaignBridge\Admin\Core\Forms\Form_Builder_Fields;

/**
 * Form Builder - Provides fluent API for form configuration
 *
 * @package CampaignBridge\Admin\Core
 */
class Form_Builder {
	use Form_Builder_Fields;

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
		$layout = Form_Builder_Intelligence::layout( $this->config );
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
			$message = Form_Builder_Intelligence::success_message( $this->config );
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
			$message = Form_Builder_Intelligence::error_message( $this->config );
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
			$text = Form_Builder_Intelligence::submit_text( $this->config );
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
		$detected_type = Form_Builder_Intelligence::field_type( $name, $type );
		if ( $detected_type !== $type ) {
			$type = $detected_type;
		}

		$field_builder       = $this->field_manager->add_field( $name, $type, $label );
		$this->current_field = $field_builder;

		// Auto-generate label if not provided.
		if ( empty( $label ) ) {
			$label = Form_Builder_Intelligence::label( $name );
			$field_builder->label( $label );
		}

		// Auto-add smart validation rules.
		Form_Builder_Intelligence::add_validation( $name, $type, $field_builder );

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
}
