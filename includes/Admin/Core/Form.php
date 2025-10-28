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
use CampaignBridge\Admin\Core\Form_Registry;
use CampaignBridge\Admin\Core\Forms\Form_Config;
use CampaignBridge\Admin\Core\Forms\Form_Container;
use CampaignBridge\Admin\Core\Forms\Form_Cache;
use CampaignBridge\Admin\Core\Forms\Form_Query_Optimizer;
use CampaignBridge\Admin\Core\Forms\Form_Asset_Optimizer;
use CampaignBridge\Admin\Core\Forms\Form_Security;
use CampaignBridge\Admin\Core\Forms\Form_Conditional_Manager;

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
	 * Form cache for performance optimization
	 *
	 * @var Form_Cache
	 */
	private Form_Cache $cache;

	/**
	 * Track rendered fields for validation
	 *
	 * @var array<string>
	 */
	protected array $rendered_fields = array();


	/**
	 * Query optimizer for database performance
	 *
	 * @var Form_Query_Optimizer
	 */
	private Form_Query_Optimizer $query_optimizer;

	/**
	 * Asset optimizer for loading performance
	 *
	 * @var Form_Asset_Optimizer
	 */
	private Form_Asset_Optimizer $asset_optimizer;

	/**
	 * Security handler for form validation and protection
	 *
	 * @var Form_Security
	 */
	private Form_Security $security;

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
	 * @param Form_Container       $container Dependency injection container.
	 */
	private function __construct( string $form_id, array $config = array(), ?Form_Container $container = null ) {
		$this->container = $container ?? new Form_Container();

		// Initialize configuration.
		$this->config = new Form_Config( $config );
		$this->config->set( 'form_id', $form_id );

		// Initialize form builder.
		$this->builder = new Form_Builder( $this->config, $this );

		// Initialize cache for performance optimization.
		$this->cache = $this->container->create_form_cache();

		// Initialize query optimizer for database performance.
		$this->query_optimizer = $this->container->create_query_optimizer();

		// Initialize asset optimizer for loading performance.
		$this->asset_optimizer = $this->container->create_asset_optimizer();

		// Services will be initialized lazily to capture all fields.
		// DO NOT call initialize_services() here - fields are added after construction!
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

		$this->renderer = $this->container->create_form_renderer(
			$this,
			$this->config,
			$this->config->get_fields(),
			$this->data_manager->get_data(),
			$this->handler
		);
	}

	/**
	 * Initialize services
	 */
	private function initialize_services(): void {
		$form_id = $this->config->get( 'form_id' );
		$fields  = $this->config->get_fields();

		// Get security instance configured for this form.
		$this->security = $this->container->get( 'form_security' );
		$this->security->set_form_id( $form_id );

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

		// Set up conditional manager if form has conditional fields.
		if ( $this->has_conditional_fields() ) {
			$conditional_manager = $this->container->create_form_conditional_manager( $fields );
			$this->handler->set_conditional_manager( $conditional_manager );
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
				$notice_handler = $this->container->get( 'form_notice_handler' );
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
	private function prepare_for_rendering(): void {
		// Ensure services are initialized.
		assert( null !== $this->data_manager, 'Data manager must be initialized' );
		assert( null !== $this->handler, 'Handler must be initialized' );

		// Handle form submission if this is a POST request and form hasn't been submitted yet.
		if ( 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) && ! $this->handler->is_submitted() ) {
			$this->handler->handle_submission();
		}

		// Ensure renderer is initialized AFTER submission handling to get updated data.
		$this->ensure_renderer();

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
		$this->renderer->render_form_open();
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
	 * Add a file field to the form.
	 *
	 * @param string      $name   Field name.
	 * @param string      $label  Field label.
	 * @param string|null $accept Accepted file types.
	 * @return \CampaignBridge\Admin\Core\Forms\Form_Field_Builder
	 */
	public function file( string $name, string $label = '', ?string $accept = null ): \CampaignBridge\Admin\Core\Forms\Form_Field_Builder {
		return $this->builder->file( $name, $label, $accept );
	}

	/**
	 * Set the form description.
	 *
	 * @param string $description Form description.
	 * @return \CampaignBridge\Admin\Core\Form_Builder
	 */
	public function description( string $description ): \CampaignBridge\Admin\Core\Form_Builder {
		return $this->builder->description( $description );
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
		throw new \BadMethodCallException( "Method '{$method}' does not exist on " . __CLASS__ ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Get the form cache instance for performance optimization.
	 *
	 * @return Form_Cache Form cache instance.
	 */
	public function get_cache(): Form_Cache {
		return $this->cache;
	}

	/**
	 * Cache the current form configuration.
	 *
	 * @param int $expiry Cache expiry time in seconds (default: 1 hour).
	 * @return bool True on success, false on failure.
	 */
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
	 * Cache the current form configuration.
	 *
	 * @param int $expiry Cache expiry time in seconds (default: 1 hour).
	 * @return bool True on success, false on failure.
	 */
	public function cache_config( int $expiry = HOUR_IN_SECONDS ): bool {
		$form_id          = $this->config->get( 'form_id' );
		$config_cache_key = "config_{$form_id}";

		return $this->cache->set_form_config( $config_cache_key, $this->config->all(), $expiry );
	}

	/**
	 * Cache the current form fields.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function cache_fields(): bool {
		$form_id = $this->config->get( 'form_id' );
		$fields  = $this->config->get_fields();

		return $this->cache->set_form_fields( $form_id, $fields );
	}

	/**
	 * Clear all cache entries for this form.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$form_id = $this->config->get( 'form_id' );
		$this->cache->invalidate_form_cache( $form_id );
	}

	/**
	 * Get the query optimizer instance for database performance.
	 *
	 * @return Form_Query_Optimizer Query optimizer instance.
	 */
	public function get_query_optimizer(): Form_Query_Optimizer {
		return $this->query_optimizer;
	}

	/**
	 * Monitor performance of a form operation.
	 *
	 * @param string   $operation_name Name of the operation.
	 * @param callable $operation      The operation to monitor.
	 * @return mixed The result of the operation.
	 */
	public function monitor_performance( string $operation_name, callable $operation ) {
		return $this->query_optimizer->monitor_query_performance( $operation_name, $operation );
	}

	/**
	 * Get database performance recommendations.
	 *
	 * @return array<int|string, mixed> Array of performance recommendations.
	 */
	public function get_performance_recommendations(): array {
		return $this->query_optimizer->get_performance_recommendations();
	}

	/**
	 * Get the asset optimizer instance for loading performance.
	 *
	 * @return Form_Asset_Optimizer Asset optimizer instance.
	 */
	public function get_asset_optimizer(): Form_Asset_Optimizer {
		return $this->asset_optimizer;
	}

	/**
	 * Enqueue optimized script for this form.
	 *
	 * @param string        $handle    Script handle.
	 * @param string        $src       Script source URL.
	 * @param array<string> $deps      Dependencies.
	 * @param string        $version   Version string.
	 * @param bool          $in_footer Whether to load in footer.
	 * @return void
	 */
	public function enqueue_script( string $handle, string $src, array $deps = array(), string $version = '', bool $in_footer = true ): void {
		$this->asset_optimizer->enqueue_script( $handle, $src, $deps, $version, $in_footer );
	}

	/**
	 * Enqueue optimized style for this form.
	 *
	 * @param string        $handle  Style handle.
	 * @param string        $src     Style source URL.
	 * @param array<string> $deps    Dependencies.
	 * @param string        $version Version string.
	 * @param string        $media   Media type.
	 * @return void
	 */
	public function enqueue_style( string $handle, string $src, array $deps = array(), string $version = '', string $media = 'all' ): void {
		$this->asset_optimizer->enqueue_style( $handle, $src, $deps, $version, $media );
	}

	/**
	 * Add conditional asset loading rule.
	 *
	 * @param string   $asset_handle Asset handle.
	 * @param callable $condition   Condition function.
	 * @param string   $type        Asset type ('script' or 'style').
	 * @return void
	 */
	public function add_asset_condition( string $asset_handle, callable $condition, string $type = 'script' ): void {
		$this->asset_optimizer->add_conditional_rule( $asset_handle, $condition, $type );
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
}
