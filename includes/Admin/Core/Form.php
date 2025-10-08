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
use CampaignBridge\Admin\Core\Forms\Form_Field_Builder;

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
	public static function make( string $form_id, array $config = [] ): self {
		return new self( $form_id, $config );
	}

	/**
	 * Create a contact form (pre-configured)
	 *
	 * @param string $form_id Form ID.
	 * @return Form
	 */
	public static function contact( string $form_id = 'contact' ): self {
		return self::make($form_id,
			array(
				'fields' => array(
					'name'    => array(
						'type'     => 'text',
						'label'    => 'Name',
						'required' => true,
					),
					'email'   => array(
						'type'     => 'email',
						'label'    => 'Email',
						'required' => true,
					),
					'subject' => array(
						'type'  => 'text',
						'label' => 'Subject',
					),
					'message' => array(
						'type'     => 'textarea',
						'label'    => 'Message',
						'required' => true,
						'rows'     => 5,
					),
				),
				'hooks'  => array(
					'after_save' => function ( $data ) {
						\wp_mail( \get_option( 'admin_email' ), $data['subject'] ?? 'Contact Form', $data['message'] );
					},
				),
			));
	}

	/**
	 * Create a user registration form
	 *
	 * @param string $form_id Form ID.
	 * @return Form
	 */
	public static function register( string $form_id = 'register' ): self {
		return self::make($form_id,
			array(
				'fields' => array(
					'username'         => array(
						'type'     => 'text',
						'label'    => 'Username',
						'required' => true,
					),
					'email'            => array(
						'type'     => 'email',
						'label'    => 'Email',
						'required' => true,
					),
					'password'         => array(
						'type'     => 'password',
						'label'    => 'Password',
						'required' => true,
					),
					'password_confirm' => array(
						'type'     => 'password',
						'label'    => 'Confirm Password',
						'required' => true,
					),
				),
				'hooks'  => array(
					'before_validate' => function ( $data ) {
						if ( $data['password'] !== $data['password_confirm'] ) {
							throw new \Exception( 'Passwords do not match' );
						}
					},
				),
			));
	}

	/**
	 * Create a settings form
	 *
	 * @param string $form_id Form ID.
	 * @return Form
	 */
	public static function settings( string $form_id = 'settings' ): self {
		return self::make( $form_id )
			->options() // Save to options.
			->table() // Table layout.
			->success( 'Settings saved successfully!' )
			->prefix( 'my_plugin_' );
	}

	/**
	 * Constructor
	 *
	 * @param string         $form_id   Form ID.
	 * @param array          $config    Initial config.
	 * @param Form_Container $container Dependency injection container.
	 */
	private function __construct( string $form_id, array $config = [], ?Form_Container $container = null ) {
		$this->container = $container ?? new Form_Container();

		// Initialize configuration.
		$this->config = new Form_Config( $config );
		$this->config->set( 'form_id', $form_id );

		// Initialize form builder.
		$this->builder = new Form_Builder( $this->config, $this );

		// Initialize services.
		$this->initialize_services();

		$this->init();
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
	}

	/**
	 * Initialize the form
	 */
	private function init(): void {
		// Ensure services are initialized
		assert( $this->data_manager !== null, 'Data manager must be initialized' );
		assert( $this->handler !== null, 'Handler must be initialized' );

		// Load form data if editing.
		$this->data_manager->load_form_data();

		// Handle submission.
		$this->handler->handle_submission();
	}




	/**
	 * Render the form HTML.
	 *
	 * @return void
	 */
	public function render(): void {
		// Ensure services are initialized
		assert( $this->data_manager !== null, 'Data manager must be initialized' );
		assert( $this->handler !== null, 'Handler must be initialized' );

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

		$this->renderer->render_form_open();
		$this->renderer->render_switch_styles();
		$this->renderer->render_messages();
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
		assert( $this->handler !== null, 'Handler must be initialized' );
		return $this->handler->is_submitted();
	}

	/**
	 * Check if form is valid
	 *
	 * @return bool
	 */
	public function valid(): bool {
		assert( $this->handler !== null, 'Handler must be initialized' );
		return $this->handler->is_valid();
	}

	/**
	 * Get form errors
	 *
	 * @return array
	 */
	public function errors(): array {
		assert( $this->handler !== null, 'Handler must be initialized' );
		return $this->handler->get_errors();
	}

	/**
	 * Get form success messages
	 *
	 * @return array
	 */
	public function messages(): array {
		assert( $this->handler !== null, 'Handler must be initialized' );
		return $this->handler->get_messages();
	}

	/**
	 * Save to WordPress options
	 *
	 * @param string $prefix Option prefix.
	 * @return Form
	 */
	public function options( string $prefix = '' ): self {
		$this->builder->options( $prefix );
		return $this;
	}

	/**
	 * Save to post meta
	 *
	 * @param int $post_id Post ID.
	 * @return Form
	 */
	public function meta( int $post_id = 0 ): self {
		$this->builder->meta( $post_id );
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
	 * Set custom layout
	 *
	 * @param callable $renderer Custom render function.
	 * @return Form
	 */
	public function custom( callable $renderer ): self {
		$this->builder->custom( $renderer );
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
	 * Set option prefix
	 *
	 * @param string $prefix Prefix for option keys.
	 * @return Form
	 */
	public function prefix( string $prefix ): self {
		$this->builder->prefix( $prefix );
		return $this;
	}

	/**
	 * Set option suffix
	 *
	 * @param string $suffix Suffix for option keys.
	 * @return Form
	 */
	public function suffix( string $suffix ): self {
		$this->builder->suffix( $suffix );
		return $this;
	}

	/**
	 * Set submit button
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
	 * Add a lifecycle hook
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
	 * Set form enctype for file uploads
	 *
	 * @return Form
	 */
	public function multipart(): self {
		$this->builder->multipart();
		return $this;
	}

	/**
	 * Get form data
	 *
	 * @param string $key Optional field key.
	 * @return mixed
	 */
	public function data( string $key = '' ) {
		assert( $this->data_manager !== null, 'Data manager must be initialized' );
		return $key ? $this->data_manager->get_data( $key ) : $this->data_manager->get_data();
	}

	/**
	 * Delegate method calls to the builder for fluent API
	 *
	 * @param string $method Method name.
	 * @param array  $args   Method arguments.
	 * @return mixed
	 */
	public function __call( string $method, array $args ) {
		// Delegate to builder if method exists
		if ( method_exists( $this->builder, $method ) ) {
			return $this->builder->{$method}( ...$args );
		}

		// Method not found
		throw new \BadMethodCallException( "Method '{$method}' does not exist on " . __CLASS__ );
	}
}
