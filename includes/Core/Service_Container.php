<?php
/**
 * Service Container for CampaignBridge Dependency Injection System.
 *
 * This class implements a comprehensive dependency injection container that
 * follows SOLID principles and provides centralized service management for
 * the entire CampaignBridge plugin. It serves as the backbone for service
 * registration, instantiation, and lifecycle management.
 *
 * This class is essential for the plugin's architecture and provides
 * the foundation for all service-based functionality.
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
	 * Register a service
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
	 * Get a service instance
	 *
	 * @param string $name Service name.
	 * @return object Service instance.
	 * @throws \InvalidArgumentException If service not found.
	 */
	public function get( string $name ) {
		if ( ! isset( $this->services[ $name ] ) ) {
			throw new \InvalidArgumentException( sprintf( 'Service \'%s\' not registered', esc_html( $name ) ) );
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

		throw new \InvalidArgumentException( 'Invalid service implementation' );
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
	 * Register core services
	 *
	 * @return void
	 */
	private function register_services(): void {
		// Core services.
		$this->register( 'notices', \CampaignBridge\Notices::class );
		$this->register( 'dispatcher', \CampaignBridge\Core\Dispatcher::class );

		// Providers - with error handling for missing classes.
		$this->register(
			'mailchimp_provider',
			function ( $container ) {
				if ( ! class_exists( '\\CampaignBridge\\Providers\\MailchimpProvider' ) ) {
					throw new \RuntimeException( 'MailchimpProvider class not found. Please ensure all dependencies are loaded.' );
				}
				return new \CampaignBridge\Providers\MailchimpProvider();
			}
		);

		$this->register(
			'html_provider',
			function ( $container ) {
				if ( ! class_exists( '\\CampaignBridge\\Providers\\HtmlProvider' ) ) {
					throw new \RuntimeException( 'HtmlProvider class not found. Please ensure all dependencies are loaded.' );
				}
				return new \CampaignBridge\Providers\HtmlProvider();
			}
		);

		// Admin services - UI class uses static methods, no instantiation needed.

		// REST API.
		$this->register(
			'rest_routes',
			function ( $container ) {
				$providers = array(
					'mailchimp' => $container->get( 'mailchimp_provider' ),
					'html'      => $container->get( 'html_provider' ),
				);
				return new \CampaignBridge\REST\Routes( $providers );
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
