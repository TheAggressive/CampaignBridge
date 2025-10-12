<?php
/**
 * Form - The Most Developer Friendly Form API
 *
 * A fluent, intuitive API for creating forms with minimal code.
 * Makes form building as easy as writing sentences.
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

use CampaignBridge\Admin\Core\Form_Builder;
use CampaignBridge\Admin\Core\Forms\Form_Config;
use CampaignBridge\Admin\Core\Forms\Form_Container;

/**
 * Form Facade - The most developer friendly form API
 *
 * @package CampaignBridge\Admin\Core
 */
class Form {

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
	 * Dependency injection container
	 *
	 * @var Form_Container
	 */
	private Form_Container $container;

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
	 * @param string $form_id Unique form identifier.
	 * @param array  $config  Optional initial configuration.
	 * @return Form
	 */
	public static function make( string $form_id, array $config = array() ): self {
		return new self( $form_id, $config );
	}


	/**
	 * Constructor
	 *
	 * @param string         $form_id   Form ID.
	 * @param array          $config    Initial config.
	 * @param Form_Container $container Dependency injection container.
	 */
	private function __construct( string $form_id, array $config = array(), ?Form_Container $container = null ) {
		$this->container = $container ?? new Form_Container();

		// Initialize configuration.
		$this->config = new Form_Config( $config );
		$this->config->set( 'form_id', $form_id );

		// Initialize form builder.
		$this->builder = new Form_Builder( $this->config, $this );

		// Services will be initialized lazily to capture all fields.
		// DO NOT call initialize_services() here - fields are added after construction!
	}

	/**
	 * Ensure the form is initialized (lazy initialization)
	 */
	private function ensure_initialized(): void {
		if ( ! $this->initialized ) {
			// Initialize services FIRST to capture all fields that were added.
			$this->initialize_services();

			// Then run user init hooks.
			$this->init();

			$this->initialized = true;
		}
	}

	/**
	 * Initialize services
	 */
	private function initialize_services(): void {
		$form_id = $this->config->get( 'form_id' );
		$fields  = $this->config->get_fields();

		// Get security instance configured for this form.
		$security = $this->container->get( 'form_security' );
		$security->set_form_id( $form_id );

		// Get validator instance.
		$validator = $this->container->get( 'form_validator' );

		// Create configured services.
		$this->data_manager = $this->container->create_form_data_manager(
			$this,
			$this->config,
			$fields
		);

		$this->handler = $this->container->create_form_handler(
			$this,
			$this->config,
			$fields,
			$validator
		);

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
				$notice_handler = $this->container->get( 'form_notice_handler' );
				$form_id        = $this->config->get( 'form_id', 'form' );

				$notice_handler->trigger_warning(
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

		// Handle submission.
		$this->handler->handle_submission();
	}

	/**
	 * Render the form HTML.
	 *
	 * @param bool $include_messages Whether to include messages in the output (deprecated - messages now auto-display via Screen_Context).
	 * @return void
	 */
	public function render( bool $include_messages = true ): void {
		$this->ensure_initialized();
		// Ensure services are initialized.
		assert( null !== $this->data_manager, 'Data manager must be initialized' );
		assert( null !== $this->handler, 'Handler must be initialized' );

		// Create renderer lazily with current data.
		if ( ! $this->renderer ) {
			$this->renderer = $this->container->create_form_renderer(
				$this,
				$this->config,
				$this->config->get_fields(),
				$this->data_manager->get_data(),
				$this->handler
			);
		}

		// Handle form submission if this is a POST request and form hasn't been submitted yet.
		if ( 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) && ! $this->handler->is_submitted() ) {
			$this->handler->handle_submission();
		}

		$this->renderer->render_form_open();
		$this->renderer->render_switch_styles();

		// Only render messages if explicitly requested (for backward compatibility).
		if ( $include_messages ) {
			$this->renderer->render_messages();
		}

		$this->renderer->render_fields();
		$this->renderer->render_submit_button();
		$this->renderer->render_form_close();
	}

	/**
	 * Check if form was submitted
	 *
	 * @return bool
	 */
	public function submitted(): bool {
		$this->ensure_initialized();
		assert( null !== $this->handler, 'Handler must be initialized' );
		return $this->handler->is_submitted();
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
	 * Get form errors
	 *
	 * @return array
	 */
	public function errors(): array {
		assert( null !== $this->handler, 'Handler must be initialized' );
		return $this->handler->get_errors();
	}

	/**
	 * Get form success messages
	 *
	 * @return array
	 */
	public function messages(): array {
		assert( null !== $this->handler, 'Handler must be initialized' );
		return $this->handler->get_messages();
	}

	/**
	 * Configure data persistence methods
	 */

	/**
	 * Save to WordPress options
	 *
	 * @param string $prefix Option prefix.
	 * @return Form
	 */
	public function save_to_options( string $prefix = '' ): self {
		$this->builder->save_to_options( $prefix );
		return $this;
	}

	/**
	 * Save to post meta
	 *
	 * @param int $post_id Post ID.
	 * @return Form
	 */
	public function save_to_post_meta( int $post_id = 0 ): self {
		$this->builder->save_to_post_meta( $post_id );
		return $this;
	}

	/**
	 * Save to WordPress Settings API
	 *
	 * @param string $group Settings group.
	 * @return Form
	 */
	public function save_to_settings_api( string $group = '' ): self {
		$this->builder->save_to_settings_api( $group );
		return $this;
	}

	/**
	 * Save to custom callback
	 *
	 * @param callable $callback Save callback.
	 * @return Form
	 */
	public function save_to_custom( callable $callback ): self {
		$this->builder->save_to_custom( $callback );
		return $this;
	}


	/**
	 * Configure form layout and appearance
	 */

	/**
	 * Set table layout
	 *
	 * @return Form
	 */
	public function table(): self {
		$this->builder->table();
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
	 * Configure messages
	 */

	/**
	 * Set success message
	 *
	 * @param string $message Success message.
	 * @return Form
	 */
	public function success( string $message ): self {
		$this->builder->success( $message );
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
	 * Configure field naming and submit button
	 */

	/**
	 * Set option prefix for field keys
	 *
	 * @param string $prefix Prefix for option keys.
	 * @return Form
	 */
	public function prefix( string $prefix ): self {
		$this->builder->prefix( $prefix );
		return $this;
	}

	/**
	 * Set option suffix for field keys
	 *
	 * @param string $suffix Suffix for option keys.
	 * @return Form
	 */
	public function suffix( string $suffix ): self {
		$this->builder->suffix( $suffix );
		return $this;
	}

	/**
	 * Set submit button configuration
	 *
	 * @param string $text Button text.
	 * @param string $type Button type (primary, secondary).
	 * @return Form
	 */
	public function submit( string $text = 'Save', string $type = 'primary' ): self {
		$this->builder->submit( $text, $type );
		return $this;
	}

	/**
	 * Configure lifecycle hooks and callbacks
	 */

	/**
	 * Add a generic lifecycle hook
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Hook callback.
	 * @return Form
	 */
	public function on( string $hook, callable $callback ): self {
		$this->builder->on( $hook, $callback );
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
	 * Add on success hook
	 *
	 * @param callable $callback Hook callback.
	 * @return Form
	 */
	public function on_success( callable $callback ): self {
		$this->builder->on_success( $callback );
		return $this;
	}

	/**
	 * Add on error hook
	 *
	 * @param callable $callback Hook callback.
	 * @return Form
	 */
	public function on_error( callable $callback ): self {
		$this->builder->on_error( $callback );
		return $this;
	}


	/**
	 * Data access and manipulation methods
	 */

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
	 * @param string $method Method name.
	 * @param array  $args   Method arguments.
	 * @throws \BadMethodCallException If method does not exist.
	 * @return mixed
	 */
	public function __call( string $method, array $args ) {
		// Delegate to builder if method exists.
		if ( method_exists( $this->builder, $method ) ) {
			return $this->builder->{$method}( ...$args );
		}

		// Method not found.
		throw new \BadMethodCallException( "Method '{$method}' does not exist on " . __CLASS__ ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}
}
