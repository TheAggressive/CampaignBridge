<?php
/**
 * Service Container for CampaignBridge Dependency Injection System.
 *
 * Provides centralized service management, registration, and instantiation
 * following SOLID principles for the entire plugin architecture.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service Container for dependency injection and service management
 */
class Service_Container {
	/**
	 * Service name constants
	 */
	private const SERVICE_NOTICES       = 'notices';
	private const SERVICE_DISPATCHER    = 'dispatcher';
	private const SERVICE_REST_ROUTES   = 'rest_routes';
	private const SERVICE_PERFORMANCE   = 'performance_optimizer';
	private const SERVICE_ERROR_HANDLER = 'error_handler';

	/**
	 * Error message constants
	 */
	private const ERROR_SERVICE_NOT_FOUND      = 'Service \'%s\' not registered';
	private const ERROR_INVALID_IMPLEMENTATION = 'Invalid service implementation';

	/**
	 * Registered services
	 *
	 * @var array<string, mixed>
	 */
	private array $services = array();

	/**
	 * Service instances cache
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Whether services have been initialized
	 *
	 * @var bool
	 */
	private bool $initialized = false;


	/**
	 * Register a service.
	 *
	 * @param string $name Service name.
	 * @param mixed  $implementation Service implementation or factory.
	 * @param bool   $singleton Whether to cache the instance.
	 * @return void
	 */
	public function register( string $name, mixed $implementation, bool $singleton = true ): void {
		$this->services[ $name ] = array(
			'implementation' => $implementation,
			'singleton'      => $singleton,
		);
	}

	/**
	 * Get a service instance.
	 *
	 * @param string $name Service name.
	 * @return object Service instance.
	 * @throws \InvalidArgumentException If service not found.
	 */
	public function get( string $name ) {
		if ( ! isset( $this->services[ $name ] ) ) {
			throw new \InvalidArgumentException( sprintf( esc_html( self::ERROR_SERVICE_NOT_FOUND ), esc_html( $name ) ) );
		}

		$service = $this->services[ $name ];

		if ( $service['singleton'] && isset( $this->instances[ $name ] ) ) {
			return $this->instances[ $name ];
		}

		$instance = $this->create_instance( $service['implementation'] );

		if ( $service['singleton'] ) {
			$this->instances[ $name ] = $instance;
		}

		return $instance;
	}

	/**
	 * Check if service is registered
	 *
	 * @param string $name Service name.
	 * @return bool
	 */
	public function has( string $name ): bool {
		return isset( $this->services[ $name ] );
	}

	/**
	 * Create service instance
	 *
	 * @param mixed $implementation Service implementation.
	 * @return object
	 * @throws \InvalidArgumentException If implementation is invalid.
	 */
	private function create_instance( mixed $implementation ): object {
		if ( is_callable( $implementation ) ) {
			return call_user_func( $implementation, $this );
		}

		if ( is_string( $implementation ) && class_exists( $implementation ) ) {
			return new $implementation();
		}

		if ( is_object( $implementation ) ) {
			return $implementation;
		}

		throw new \InvalidArgumentException( esc_html( self::ERROR_INVALID_IMPLEMENTATION ) );
	}

	/**
	 * Initialize all services
	 *
	 * @return void
	 */
	public function initialize(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->register_services();
		$this->initialized = true;
	}

	/**
	 * Register core services.
	 *
	 * @return void
	 */
	private function register_services(): void {
		// ========== CORE SERVICES ==========
		$this->register( self::SERVICE_NOTICES, \CampaignBridge\Notices::class );
		$this->register( self::SERVICE_DISPATCHER, \CampaignBridge\Core\Dispatcher::class );
		$this->register( self::SERVICE_PERFORMANCE, \CampaignBridge\Core\Performance_Optimizer::class );
		$this->register( self::SERVICE_ERROR_HANDLER, \CampaignBridge\Core\Error_Handler::class );

		// ========== EMAIL PROVIDERS ==========
		$this->register(
			'mailchimp_provider',
			\CampaignBridge\Providers\Mailchimp_Provider::class
		);

		$this->register(
			'html_provider',
			\CampaignBridge\Providers\Html_Provider::class
		);

		// ========== REST API ==========
		$this->register(
			self::SERVICE_REST_ROUTES,
			function ( $container ) {
				$providers = array(
					'mailchimp' => $container->get( 'mailchimp_provider' ),
					'html'      => $container->get( 'html_provider' ),
				);
				\CampaignBridge\REST\Routes::init( 'campaignbridge_settings', $providers );
				return \CampaignBridge\REST\Routes::class;
			}
		);
	}

	/**
	 * Get all registered services
	 *
	 * @return array<string>
	 */
	public function get_registered_services(): array {
		return array_keys( $this->services );
	}

	/**
	 * Clear service cache
	 *
	 * @param string|null $name Optional specific service to clear.
	 * @return void
	 */
	public function clear_cache( ?string $name = null ): void {
		if ( $name ) {
			unset( $this->instances[ $name ] );
		} else {
			$this->instances = array();
		}
	}
}
