<?php
/**
 * Form Dependency Injection Container
 *
 * Manages dependencies for the Form system components.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Admin\Core\Form_Builder;

/**
 * Form Dependency Injection Container
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Container {

	/**
	 * Service definitions
	 *
	 * @var array<string, callable>
	 */
	private array $services = array();

	/**
	 * Shared service instances
	 *
	 * @var array<string, bool>
	 */
	private array $shared = array();

	/**
	 * Shared service instances storage
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Constructor - Register default services
	 */
	public function __construct() {
		$this->register_default_services();
	}

	/**
	 * Register a service
	 *
	 * @param string   $key     Service key.
	 * @param callable $factory Factory function.
	 * @param bool     $shared  Whether to share the instance.
	 * @return self
	 */
	public function register( string $key, callable $factory, bool $shared = false ): self {
		$this->services[ $key ] = $factory;
		$this->shared[ $key ]   = $shared;
		return $this;
	}

	/**
	 * Get a service instance
	 *
	 * @param string $key Service key.
	 * @return mixed Service instance.
	 *
	 * @throws \InvalidArgumentException If service not found.
	 */
	public function get( string $key ) {
		if ( isset( $this->shared[ $key ] ) && $this->shared[ $key ] && isset( $this->instances[ $key ] ) ) {
			return $this->instances[ $key ];
		}

		if ( ! isset( $this->services[ $key ] ) ) {
			throw new \InvalidArgumentException( sprintf( esc_html__( "Service '%s' not registered", 'campaignbridge' ), esc_html( $key ) ) );
		}

		$instance = $this->services[ $key ]( $this );

		if ( isset( $this->shared[ $key ] ) && $this->shared[ $key ] ) {
			$this->instances[ $key ] = $instance;
		}

		return $instance;
	}

	/**
	 * Check if a service is registered
	 *
	 * @param string $key Service key.
	 * @return bool True if registered.
	 */
	public function has( string $key ): bool {
		return isset( $this->services[ $key ] );
	}

	/**
	 * Create a Form instance
	 *
	 * @param string               $form_id Form ID.
	 * @param array<string, mixed> $config  Initial configuration.
	 * @return \CampaignBridge\Admin\Core\Form
	 */
	public function create_form( string $form_id, array $config = array() ): \CampaignBridge\Admin\Core\Form {
		return \CampaignBridge\Admin\Core\Form::make( $form_id, $config );
	}

	/**
	 * Create a Form_Builder instance
	 *
	 * @param Form_Config                     $config Form configuration.
	 * @param \CampaignBridge\Admin\Core\Form $form   Parent form instance.
	 *
	 * @return Form_Builder
	 */
	public function create_form_builder( Form_Config $config, \CampaignBridge\Admin\Core\Form $form ): Form_Builder {
		return new Form_Builder( $config, $form );
	}

	/**
	 * Register default services
	 */
	private function register_default_services(): void {
		// Form_Config factory.
		$this->register(
			'form_config',
			function () {
				return new Form_Config();
			}
		);

		// Form_Security factory (not shared - each form needs its own).
		$this->register(
			'form_security',
			function ( $_container ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required for consistent factory signature
				return new Form_Security( 'default' ); // Will be set by form.
			},
			false
		);

		// Form_Validator factory (shared).
		$this->register(
			'form_validator',
			function () {
				return new Form_Validator();
			},
			true
		);

		// Form_Conditional_Manager factory (not shared, needs per-form data).
		$this->register(
			'form_conditional_manager',
			function ( array $fields = array(), array $data = array() ) {
				return new Form_Conditional_Manager( $fields, $data );
			},
			false
		);

		// Form_Field_Factory factory (shared).
		$this->register(
			'form_field_factory',
			function ( $container ) {
				return new Form_Field_Factory( $container->get( 'form_validator' ) );
			},
			true
		);

		// Form_Handler factory.
		$this->register(
			'form_handler',
			function ( $container ) {
				return new Form_Handler(
					null, // Will be set by form.
					new Form_Config(), // Will be set by form.
					array(), // Will be set by form.
					$container->get( 'form_security' ),
					$container->get( 'form_validator' ),
					new Form_Notice_Handler()
				);
			}
		);
	}

	/**
	 * Create a configured Form_Handler
	 *
	 * @param \CampaignBridge\Admin\Core\Form $form      Form instance.
	 * @param Form_Config                     $config    Form configuration.
	 * @param array<string, mixed>            $fields    Form fields.
	 * @param Form_Validator                  $validator Validator instance.
	 *
	 * @return Form_Handler
	 */
	public function create_form_handler(
		\CampaignBridge\Admin\Core\Form $form,
		Form_Config $config,
		array $fields,
		Form_Validator $validator
	): Form_Handler {
		$security       = new Form_Security( $config->get( 'form_id', 'form' ) );
		$notice_handler = new Form_Notice_Handler();

		return new Form_Handler( $form, $config, $fields, $security, $validator, $notice_handler );
	}

	/**
	 * Create a configured Form_Data_Manager
	 *
	 * @param \CampaignBridge\Admin\Core\Form $form   Form instance.
	 * @param Form_Config                     $config Form configuration.
	 * @param array<string, mixed>            $fields Form fields.
	 * @return Form_Data_Manager
	 */
	public function create_form_data_manager(
		\CampaignBridge\Admin\Core\Form $form,
		Form_Config $config,
		array $fields
	): Form_Data_Manager {
		return new Form_Data_Manager( $form, $config->all(), $fields );
	}

	/**
	 * Create a configured Form_Renderer
	 *
	 * @param \CampaignBridge\Admin\Core\Form $form    Form instance.
	 * @param Form_Config                     $config  Form configuration.
	 * @param array<string, mixed>            $fields  Form fields.
	 * @param array<string, mixed>            $data    Form data.
	 * @param Form_Handler                    $handler Form handler.
	 * @return Form_Renderer
	 */
	public function create_form_renderer(
		\CampaignBridge\Admin\Core\Form $form,
		Form_Config $config,
		array $fields,
		array $data,
		Form_Handler $handler
	): Form_Renderer {
		// Use service container for security to enable proper dependency injection.
		$form_id = $config->get( 'form_id', 'form' );
		if ( ! $this->has( "security_{$form_id}" ) ) {
			$this->register( "security_{$form_id}", fn() => new Form_Security( $form_id ), false );
		}

		$security = $this->get( "security_{$form_id}" );

		// Get validator.
		$validator = $this->get( 'form_validator' );

		// Create handler.
		$handler = $this->create_form_handler( $form, $config, $fields, $validator );

		return new Form_Renderer( $config->all(), $fields, $data, $security, $validator );
	}

	/**
	 * Create a form cache service for performance optimization.
	 *
	 * @return Form_Cache
	 */
	public function create_form_cache(): Form_Cache {
		if ( ! $this->has( Form_Cache::class ) ) {
			$this->register( Form_Cache::class, fn() => new Form_Cache(), true );
		}

		return $this->get( Form_Cache::class );
	}

	/**
	 * Create a form query optimizer for database performance.
	 *
	 * @return Form_Query_Optimizer
	 */
	public function create_query_optimizer(): Form_Query_Optimizer {
		if ( ! $this->has( Form_Query_Optimizer::class ) ) {
			$this->register( Form_Query_Optimizer::class, fn() => new Form_Query_Optimizer(), true );
		}

		return $this->get( Form_Query_Optimizer::class );
	}

	/**
	 * Create a form asset optimizer for loading performance.
	 *
	 * @return Form_Asset_Optimizer
	 */
	public function create_asset_optimizer(): Form_Asset_Optimizer {
		if ( ! $this->has( Form_Asset_Optimizer::class ) ) {
			$this->register( Form_Asset_Optimizer::class, fn() => new Form_Asset_Optimizer(), true );
		}

		return $this->get( Form_Asset_Optimizer::class );
	}

	/**
	 * Create a form conditional manager instance.
	 *
	 * @param array<string, mixed> $fields    Form fields configuration.
	 * @param array<string, mixed> $form_data Current form data.
	 * @return Form_Conditional_Manager
	 */
	public function create_form_conditional_manager( array $fields, array $form_data = array() ): Form_Conditional_Manager {
		return new Form_Conditional_Manager( $fields, $form_data );
	}
}
