<?php
/**
 * Admin settings form composition and submission.
 *
 * @package CampaignBridge\Admin\Core
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Core;

use CampaignBridge\Admin\Core\Form_Builder;
use CampaignBridge\Admin\Core\Form_Config_Methods;
use CampaignBridge\Admin\Core\Form_Field_Methods;
use CampaignBridge\Admin\Core\Form_Registry;
use CampaignBridge\Admin\Core\Forms\Form_Config;
use CampaignBridge\Admin\Core\Forms\Form_Security;
use CampaignBridge\Admin\Core\Forms\Form_Conditional_Manager;

/**
 * Forms shared by the settings and post type screens.
 *
 * @package CampaignBridge\Admin\Core
 */
class Form {
	use Form_Field_Methods;
	use Form_Config_Methods;

	/**
	 * Form configuration
	 *
	 * @var Form_Config
	 */
	private Form_Config $config;

	/**
	 * Form builder for fluent API
	 *
	 * @var Form_Builder
	 */
	private Form_Builder $builder;

	/**
	 * Track rendered fields for validation
	 *
	 * @var array<string>
	 */
	protected array $rendered_fields = array();

	/**
	 * Security handler for form validation and protection
	 *
	 * @var Form_Security
	 */
	private Form_Security $security;

	/**
	 * Per-form validation, shared with submission and rendering.
	 *
	 * @var Forms\Form_Validator
	 */
	private Forms\Form_Validator $validator;

	/**
	 * Whether the form has been initialized
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Form data manager
	 *
	 * @var \CampaignBridge\Admin\Core\Forms\Form_Data_Manager
	 */
	private ?\CampaignBridge\Admin\Core\Forms\Form_Data_Manager $data_manager = null;

	/**
	 * Form handler
	 *
	 * @var \CampaignBridge\Admin\Core\Forms\Form_Handler
	 */
	private ?\CampaignBridge\Admin\Core\Forms\Form_Handler $handler = null;

	/**
	 * Form renderer
	 *
	 * @var \CampaignBridge\Admin\Core\Forms\Form_Renderer
	 */
	private ?\CampaignBridge\Admin\Core\Forms\Form_Renderer $renderer = null;

	/**
	 * Create a new form instance
	 *
	 * @param string               $form_id Unique form identifier.
	 * @param array<string, mixed> $config  Optional initial configuration.
	 * @return Form
	 */
	public static function make( string $form_id, array $config = array() ): self {
		return new self( $form_id, $config );
	}

	/**
	 * Constructor
	 *
	 * @param string               $form_id   Form ID.
	 * @param array<string, mixed> $config    Initial config.
	 */
	private function __construct( string $form_id, array $config = array() ) {
		$this->config = new Form_Config( $config );
		$this->config->set( 'form_id', $form_id );
		$this->builder = new Form_Builder( $this->config, $this );
		// Initialize submission services after callers have declared the fields.
	}

	/**
	 * Ensure the form is initialized (lazy initialization)
	 */
	public function ensure_initialized(): void {
		if ( ! $this->initialized ) {
			// Initialize services FIRST to capture all fields that were added.
			$this->initialize_services();

			// Then run user init hooks.
			$this->init();

			$this->initialized = true;
		}
	}

	/**
	 * Ensure renderer is initialized (lazy initialization)
	 * Always recreates renderer to ensure it has the latest data after form submission.
	 */
	public function ensure_renderer(): void {
		$this->ensure_initialized();
		assert( null !== $this->data_manager, 'Data manager must be initialized' );
		assert( null !== $this->handler, 'Handler must be initialized' );

		$this->renderer = new Forms\Form_Renderer(
			$this->config->all(),
			$this->config->get_fields(),
			$this->data_manager->get_data(),
			$this->security,
			$this->validator
		);
	}

	/**
	 * Initialize services
	 */
	private function initialize_services(): void {
		$form_id = $this->config->get( 'form_id' );
		$fields  = $this->config->get_fields();

		$this->security     = new Form_Security( $form_id );
		$this->validator    = new Forms\Form_Validator();
		$this->data_manager = new Forms\Form_Data_Manager( $this, $this->config->all(), $fields );
		$this->handler      = new Forms\Form_Handler(
			$this,
			$this->config,
			$fields,
			$this->security,
			$this->validator,
			new Forms\Form_Notice_Handler()
		);

		// Set up conditional manager if form has conditional fields.
		if ( $this->has_conditional_fields() ) {
			$conditional_manager = new Form_Conditional_Manager( $fields );
			$this->handler->set_conditional_manager( $conditional_manager );
			$this->validator->set_conditional_manager( $conditional_manager );
		}

		// Validate form configuration for potential issues.
		$this->validate_configuration();
	}

	/**
	 * Validate form configuration and show warnings for potential issues
	 */
	private function validate_configuration(): void {
		$save_method = $this->config->get( 'save_method' );

		// Check if custom saving is configured but no callback is provided.
		if ( 'custom' === $save_method ) {
			$hooks = $this->config->get( 'hooks', array() );
			if ( ! isset( $hooks['save_data'] ) || ! is_callable( $hooks['save_data'] ) ) {
				// Get notice handler and show warning.
				$notice_handler = new Forms\Form_Notice_Handler();
				$form_id        = $this->config->get( 'form_id', 'form' );

				$notice_handler->trigger_warning(
					$this->config,
					sprintf(
						/* translators: %s: Form ID */
						__( 'Form "%s" is configured to use custom saving but no save callback is provided. Data will not be persisted. Please provide a callback to the save_to_custom() method.', 'campaignbridge' ),
						$form_id
					)
				);
			}
		}
	}

	/**
	 * Initialize the form
	 */
	private function init(): void {
		// Ensure services are initialized.
		assert( null !== $this->data_manager, 'Data manager must be initialized' );
		assert( null !== $this->handler, 'Handler must be initialized' );

		// Load form data if editing.
		$this->data_manager->load_form_data();
	}

	/**
	 * Render the form HTML.
	 *
	 * @return void
	 */
	public function render(): void {
		$this->ensure_initialized();

		// Handle form submission and prepare for rendering.
		$this->prepare_for_rendering();

		// Perform the actual rendering.
		$this->perform_rendering();
	}

	/**
	 * Prepare form for rendering (handle submission, register AJAX, etc.)
	 *
	 * @return void
	 */
	public function prepare_for_rendering(): void {
		// Ensure services are initialized.
		assert( null !== $this->data_manager, 'Data manager must be initialized' );
		assert( null !== $this->handler, 'Handler must be initialized' );

		// Handle form submission if this is a POST request and form hasn't been submitted yet.
		if ( 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) && ! $this->handler->is_submitted() ) {
			$this->handler->handle_submission();
		}

		// Ensure renderer is initialized AFTER submission handling to get updated data.
		$this->ensure_renderer();
		assert( null !== $this->renderer, 'Renderer must be initialized' );

		// Auto-register form for AJAX access if it has conditional fields.
		if ( $this->renderer->has_conditional_fields() ) {
			Form_Registry::register( $this->config->get( 'form_id' ), $this->config );
		}
	}

	/**
	 * Perform the actual form rendering
	 *
	 * @return void
	 */
	private function perform_rendering(): void {
		assert( null !== $this->renderer, 'Renderer must be initialized' );
		$this->renderer->render_form_open();
		$this->renderer->render_fields();
		$this->renderer->render_submit_button();
		$this->renderer->render_form_close();
	}

	/**
	 * Check if form is valid
	 *
	 * @return bool
	 */
	public function valid(): bool {
		$this->ensure_initialized();
		assert( null !== $this->handler, 'Handler must be initialized' );
		return $this->handler->is_valid();
	}

	/**
	 * Check if form has been submitted
	 *
	 * @return bool
	 */
	public function submitted(): bool {
		$this->ensure_initialized();
		assert( null !== $this->handler, 'Handler must be initialized' );
		return $this->handler->is_submitted();
	}

	/**
	 * Get the form handler instance (for internal use by Form_Builder)
	 *
	 * @return \CampaignBridge\Admin\Core\Forms\Form_Handler
	 */
	public function get_handler(): \CampaignBridge\Admin\Core\Forms\Form_Handler {
		$this->ensure_initialized();
		assert( null !== $this->handler, 'Handler must be initialized' );
		return $this->handler;
	}

	/**
	 * Get the form renderer instance (for internal use by Form_Builder)
	 *
	 * @return \CampaignBridge\Admin\Core\Forms\Form_Renderer
	 */
	public function get_renderer(): \CampaignBridge\Admin\Core\Forms\Form_Renderer {
		$this->ensure_renderer();
		assert( null !== $this->renderer, 'Renderer must be initialized' );
		return $this->renderer;
	}

	/**
	 * Get the list of rendered fields
	 *
	 * @return array<string> Array of rendered field names.
	 */
	public function get_rendered_fields(): array {
		return $this->rendered_fields;
	}

	/**
	 * Add a field to the rendered fields list
	 *
	 * @param string $field_name The name of the rendered field.
	 * @return void
	 */
	public function add_rendered_field( string $field_name ): void {
		$this->rendered_fields[] = $field_name;
	}

	/**
	 * Get form fields (for testing/debugging)
	 *
	 * @return array<int|string, mixed>
	 */
	public function get_fields(): array {
		return $this->config->get_fields();
	}

	/**
	 * Add a hidden field to the form
	 *
	 * @param string $name  Field name.
	 * @param string $value Field value.
	 * @return Form
	 */
	public function hidden( string $name, string $value = '' ): self {
		$this->builder->hidden( $name, $value );
		return $this;
	}

	/**
	 * Set div layout
	 *
	 * @return Form
	 */
	public function div(): self {
		$this->builder->div();
		return $this;
	}

	/**
	 * Set custom layout renderer
	 *
	 * @param callable $renderer Custom render function.
	 * @return Form
	 */
	public function render_custom( callable $renderer ): self {
		$this->builder->render_custom( $renderer );
		return $this;
	}

	/**
	 * Set error message
	 *
	 * @param string $message Error message.
	 * @return Form
	 */
	public function error( string $message ): self {
		$this->builder->error( $message );
		return $this;
	}

	/**
	 * Add before save hook
	 *
	 * @param callable $callback Hook callback.
	 * @return Form
	 */
	public function before_save( callable $callback ): self {
		$this->builder->before_save( $callback );
		return $this;
	}

	/**
	 * Add after save hook
	 *
	 * @param callable $callback Hook callback.
	 * @return Form
	 */
	public function after_save( callable $callback ): self {
		$this->builder->after_save( $callback );
		return $this;
	}

	/**
	 * Add before validate hook
	 *
	 * @param callable $callback Hook callback.
	 * @return Form
	 */
	public function before_validate( callable $callback ): self {
		$this->builder->before_validate( $callback );
		return $this;
	}

	/**
	 * Add after validate hook
	 *
	 * @param callable $callback Hook callback.
	 * @return Form
	 */
	public function after_validate( callable $callback ): self {
		$this->builder->after_validate( $callback );
		return $this;
	}

	/**
	 * Get form data
	 *
	 * @param string $key Optional field key.
	 * @return mixed
	 */
	public function data( string $key = '' ) {
		assert( null !== $this->data_manager, 'Data manager must be initialized' );
		return $key ? $this->data_manager->get_data( $key ) : $this->data_manager->get_data();
	}

	/**
	 * Set form data
	 *
	 * @param string $key   Data key.
	 * @param mixed  $value Data value.
	 * @return Form
	 */
	public function set_data( string $key, $value ): self {
		assert( null !== $this->data_manager, 'Data manager must be initialized' );
		$this->data_manager->set_data( $key, $value );
		return $this;
	}

	/**
	 * Reload form data from source (clears cache and reloads)
	 *
	 * @return Form
	 */
	public function reload_data(): self {
		assert( null !== $this->data_manager, 'Data manager must be initialized' );
		$this->data_manager->reload();
		return $this;
	}

	/**
	 * Delegate method calls to the builder for fluent API
	 *
	 * @param string       $method Method name.
	 * @param array<mixed> $args   Method arguments.
	 * @throws \BadMethodCallException If method does not exist.
	 * @return mixed
	 */
	public function __call( string $method, array $args ) {
		// Delegate to builder if method exists.
		if ( method_exists( $this->builder, $method ) ) {
			return $this->builder->{$method}( ...$args );
		}

		// Method not found.
		throw new \BadMethodCallException( "Method '" . esc_html( $method ) . "' does not exist on " . __CLASS__ );
	}

	/**
	 * Get the form configuration (for debugging/testing purposes).
	 *
	 * @return Form_Config The form configuration.
	 */
	public function get_config(): Form_Config {
		return $this->config;
	}

	/**
	 * Check if form has conditional fields
	 *
	 * @return bool True if form has conditional fields.
	 */
	private function has_conditional_fields(): bool {
		$fields = $this->config->get_fields();

		foreach ( $fields as $field_config ) {
			if ( isset( $field_config['conditional'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the conditional manager instance for advanced conditional logic control.
	 *
	 * Useful for debugging or customizing conditional behavior:
	 * - Disable caching: $form->get_conditional_manager()->set_caching_enabled(false);
	 * - Clear cache: $form->get_conditional_manager()->clear_cache();
	 *
	 * @return Form_Conditional_Manager|null The conditional manager or null if not initialized.
	 */
	public function get_conditional_manager(): ?Form_Conditional_Manager {
		return $this->handler?->get_conditional_manager();
	}

	/**
	 * Enable security headers for this form.
	 *
	 * Adds comprehensive security headers including CSP, HSTS, and other protections
	 * to enhance security when the form is rendered.
	 *
	 * @param array<string, mixed> $options Security header options.
	 * @return self
	 */
	public function enable_security_headers( array $options = array() ): self {
		$this->on(
			'before_render',
			function () use ( $options ) {
				$this->security->set_security_headers( $options );
			}
		);

		return $this;
	}
	/**
	 * Set table layout
	 *
	 * @return static
	 */
	public function table(): self {
		$this->builder->table();
		return $this;
	}
	/**
	 * Set the form description.
	 *
	 * @param string $description Form description.
	 * @return static
	 */
	public function description( string $description ): self {
		$this->builder->description( $description );
		return $this;
	}
	/**
	 * Add a generic lifecycle hook
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Hook callback.
	 * @return static
	 */
	public function on( string $hook, callable $callback ): self {
		$this->builder->on( $hook, $callback );
		return $this;
	}
	/**
	 * Add on success hook
	 *
	 * @param callable $callback Hook callback.
	 * @return static
	 */
	public function on_success( callable $callback ): self {
		$this->builder->on_success( $callback );
		return $this;
	}
	/**
	 * Add on error hook
	 *
	 * @param callable $callback Hook callback.
	 * @return static
	 */
	public function on_error( callable $callback ): self {
		$this->builder->on_error( $callback );
		return $this;
	}
}
