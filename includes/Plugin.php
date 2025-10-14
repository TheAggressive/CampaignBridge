<?php
/**
 * Plugin bootstrap and orchestrator for CampaignBridge.
 *
 * This class serves as the main entry point and central coordinator for the entire
 * CampaignBridge plugin. It handles plugin initialization, service container setup,
 * provider management, admin menu creation, REST API registration, and hooks all
 * the various components together.
 *
 * The class follows the singleton pattern and is instantiated once when the plugin
 * is loaded. It acts as a facade that provides access to all plugin functionality
 * while maintaining clean separation of concerns.
 *
 * @package CampaignBridge
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge;

use CampaignBridge\Blocks\Blocks;
use CampaignBridge\Core\Api_Key_Encryption;
use CampaignBridge\Core\Service_Container;
use CampaignBridge\Post_Types\Post_Type_Email_Template;
use CampaignBridge\REST\Routes as RestRoutes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin bootstrap and orchestrator.
 *
 * Wires core WordPress hooks and delegates responsibilities to modular
 * classes for Admin UI, AJAX endpoints, Block and CPT registration, and
 * provider initialization.
 */
class Plugin {
	/**
	 * Plugin option name for settings persistence.
	 */
	private const OPTION_NAME = 'campaignbridge_settings';





	/**
	 * Map of provider slug => provider instance.
	 *
	 * @var array<string,object>
	 */
	private array $providers = array();

	/**
	 * Service container instance.
	 *
	 * @var Service_Container
	 */
	private Service_Container $service_container;


	/**
	 * Initialize service container and providers.
	 *
	 * Sets up the core service container and initializes email service providers.
	 * If initialization fails, the plugin will display an admin notice and stop.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function initialize_services(): void {
		try {
			// Initialize service container.
			$this->service_container = new Service_Container();
			$this->service_container->initialize();

			// Get providers from service container.
			$this->providers = array(
				'html'      => $this->service_container->get( 'html_provider' ),
				'mailchimp' => $this->service_container->get( 'mailchimp_provider' ),
			);
		} catch ( \Exception $e ) {
			$this->handle_initialization_error( $e );
		}
	}

	/**
	 * Handle critical plugin initialization errors.
	 *
	 * Logs the error and displays an admin notice when service container
	 * or provider initialization fails.
	 *
	 * @since 0.1.0
	 * @param \Exception $e The exception that occurred during initialization.
	 * @return void
	 */
	private function handle_initialization_error( \Exception $e ): void {
		// Log the error and show admin notice.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'CampaignBridge Plugin Error: ' . $e->getMessage() );
		}

		// Add admin notice about the error.
		add_action(
			'admin_notices',
			function () use ( $e ) {
				echo '<div class="notice notice-error"><p>';
				echo '<strong>CampaignBridge Error:</strong> ' . esc_html( $e->getMessage() );
				echo '<br><small>Check the error logs for more details.</small>';
				echo '</p></div>';
			}
		);

		// Don't continue with plugin initialization if there's a critical error.
		exit;
	}

	/**
	 * Construct and wire plugin hooks.
	 *
	 * This constructor initializes the CampaignBridge plugin by setting up the service
	 * container, registering all necessary WordPress hooks, and initializing all
	 * plugin components. It follows a fail-fast approach where critical errors
	 * prevent plugin initialization to avoid partial functionality.
	 *
	 * @since 0.1.0
	 * @throws \Exception When critical services fail to initialize.
	 */
	public function __construct() {
		// Initialize service container and providers.
		$this->initialize_services();

		// Make plugin instance globally accessible.
		global $campaignbridge_plugin;
		$campaignbridge_plugin = $this;

		// Initialize core systems.
		$this->init_core_systems();

		// Initialize admin interface.
		$this->init_admin_interface();

		// Initialize REST API.
		$this->init_rest_api();
	}

	/**
	 * Legacy admin menu method - NO LONGER USED
	 *
	 * Menu creation is now handled by the new file-based admin system
	 * in includes/Admin/Admin.php. This method is kept for reference
	 * but does nothing to avoid duplicate menus.
	 *
	 * @since 0.1.0
	 * @deprecated Replaced by file-based admin system
	 * @return void
	 */
	public function add_admin_menu(): void {
		// NO-OP: Menu creation is now handled by the new file-based admin system
		// The new system auto-discovers screens and creates menus automatically
		// See includes/Admin/Admin.php for the new implementation.
	}

	/**
	 * Run security migration for existing plaintext API keys.
	 *
	 * This method checks for and migrates any existing plaintext API keys
	 * to encrypted storage. It runs once per plugin version to ensure
	 * backward compatibility and enhanced security.
	 *
	 * @since 0.1.0
	 * @return void
	 */

	/**
	 * Register plugin settings with WordPress options API and sanitization.
	 *
	 * This method registers the CampaignBridge settings with WordPress's
	 * options system, providing a secure and standardized way to store
	 * plugin configuration. It includes comprehensive sanitization and
	 * validation to ensure data integrity and security.
	 *
	 * @since 0.1.0
	 * @return void
	 */


	/**
	 * Initialize core plugin systems.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function init_core_systems(): void {

		// Initialize Blocks system.
		Blocks::init();

		// Initialize Email Template CPT.
		Post_Type_Email_Template::init();
	}

	/**
	 * Initialize admin interface components.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function init_admin_interface(): void {
		// Wire admin hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Initialize NEW file-based admin system.
		\CampaignBridge\Admin\Admin::get_instance();
	}

	/**
	 * Initialize REST API endpoints.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function init_rest_api(): void {
		// Initialize REST routes with providers.
		RestRoutes::init( self::OPTION_NAME, $this->providers );

		// Register REST routes when REST API is initialized.
		add_action( 'rest_api_init', array( RestRoutes::class, 'register' ) );
	}
}
