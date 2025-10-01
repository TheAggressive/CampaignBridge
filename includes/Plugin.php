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

use CampaignBridge\Admin\AssetManager;
use CampaignBridge\Blocks\Blocks;
use CampaignBridge\Admin\Pages\PostTypes;
use CampaignBridge\Admin\Pages\Settings;
use CampaignBridge\Admin\Pages\Status;
use CampaignBridge\Admin\Pages\Editor;
use CampaignBridge\Core\Service_Container;
use CampaignBridge\Core\SettingsHandler;
use CampaignBridge\PostTypes\EmailTemplate;
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
	 * Default email service provider.
	 */
	private const DEFAULT_PROVIDER = 'mailchimp';

	/**
	 * Required capability for admin access.
	 */
	private const ADMIN_CAPABILITY = 'manage_options';

	/**
	 * Menu position in WordPress admin.
	 */
	private const MENU_POSITION = 30;

	/**
	 * Admin menu icon.
	 */
	private const MENU_ICON = 'dashicons-email-alt';

	/**
	 * API key minimum length.
	 */
	private const API_KEY_MIN_LENGTH = 10;

	/**
	 * API key maximum length.
	 */
	private const API_KEY_MAX_LENGTH = 100;

	/**
	 * Cache prefix for Mailchimp data.
	 */
	private const CACHE_PREFIX = 'cb_mc_';

	/**
	 * Option key used to persist plugin settings.
	 *
	 * @var string
	 */
	private string $option_name = self::OPTION_NAME;

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
	 * Settings handler instance.
	 *
	 * @var SettingsHandler
	 */
	private SettingsHandler $settings_handler;

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

			// Initialize settings handler.
			$this->settings_handler = new SettingsHandler();

			// No providers registered yet.
			$this->providers = array();
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
		if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
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

		// Initialize core systems.
		$this->init_core_systems();

		// Initialize admin interface.
		$this->init_admin_interface();

		// Initialize REST API.
		$this->init_rest_api();

		// Set up cache management.
		$this->setup_cache_management();
	}

	/**
	 * Register the complete admin menu structure for CampaignBridge.
	 *
	 * This method creates the main admin menu and all submenu pages that
	 * provide access to CampaignBridge functionality. It sets up the menu
	 * hierarchy, page callbacks, and user capability requirements while
	 * initializing shared state for all admin pages.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function add_admin_menu(): void {
		// Initialize shared state for all admin pages.
		PostTypes::init_shared_state( self::OPTION_NAME, $this->providers );
		Settings::init_shared_state( self::OPTION_NAME, $this->providers );
		Status::init_shared_state( self::OPTION_NAME, $this->providers );

		// Add main menu page.
		add_menu_page(
			'CampaignBridge',
			'CampaignBridge',
			self::ADMIN_CAPABILITY,
			'campaignbridge',
			array( PostTypes::class, 'render' ),
			self::MENU_ICON,
			self::MENU_POSITION
		);

		// Add submenu pages.
		add_submenu_page(
			'campaignbridge',
			'Post Types',
			'Post Types',
			self::ADMIN_CAPABILITY,
			PostTypes::get_page_slug(),
			array( PostTypes::class, 'render' )
		);

		add_submenu_page(
			'campaignbridge',
			'Settings',
			'Settings',
			self::ADMIN_CAPABILITY,
			Settings::get_page_slug(),
			array( Settings::class, 'render' )
		);

		// Add Status submenu page.
		add_submenu_page(
			'campaignbridge',
			'Status',
			'Status',
			self::ADMIN_CAPABILITY,
			Status::get_page_slug(),
			array( Status::class, 'render' )
		);

		add_submenu_page(
			'campaignbridge',
			'Template Editor',
			'Template Editor',
			self::ADMIN_CAPABILITY,
			Editor::get_page_slug(),
			array( Editor::class, 'render' )
		);
	}

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
	public function register_settings(): void {
		register_setting(
			'campaignbridge',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'autoload'          => false, // [SECURE] Prevent sensitive data from being loaded on every request
				'sanitize_callback' => array( $this->settings_handler, 'sanitize' ),
				'default'           => array(),
			)
		);
	}


	/**
	 * Initialize core plugin systems.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function init_core_systems(): void {
		// Initialize Asset Manager.
		AssetManager::init();

		// Initialize Blocks system.
		Blocks::init();

		// Initialize Email Template CPT.
		EmailTemplate::init();
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
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Initialize Admin Pages.
		Status::init();
		PostTypes::init();
		Settings::init();
		Editor::init();
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

	/**
	 * Set up cache management and invalidation hooks.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function setup_cache_management(): void {
		// Bust Mailchimp caches if API key changes.
		add_action(
			'update_option_' . self::OPTION_NAME,
			function ( $old, $new ) {
				$old_key = isset( $old['api_key'] ) ? (string) $old['api_key'] : '';
				$new_key = isset( $new['api_key'] ) ? (string) $new['api_key'] : '';
				if ( $old_key !== $new_key && ! empty( $old_key ) ) {
					delete_transient( self::CACHE_PREFIX . 'audiences_' . md5( $old_key ) );
					delete_transient( self::CACHE_PREFIX . 'templates_' . md5( $old_key ) );
				}
			},
			10,
			2
		);
	}
}
