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

use CampaignBridge\Admin\Asset_Manager;
use CampaignBridge\Blocks\Blocks;
use CampaignBridge\Admin\Pages\Post_Types;
use CampaignBridge\Admin\Pages\Settings;
use CampaignBridge\Admin\Pages\Status;
use CampaignBridge\Admin\Pages\Editor;
use CampaignBridge\Core\Api_Key_Encryption;
use CampaignBridge\Core\Service_Container;
use CampaignBridge\Core\Settings_Handler;
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
	 * Default email service provider.
	 */
	private const DEFAULT_PROVIDER = 'example';

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
	 * @var Settings_Handler
	 */
	private Settings_Handler $settings_handler;

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
			$this->settings_handler = new Settings_Handler();

			// Get providers from service container.
			$this->providers = array(
				'example' => $this->service_container->get( 'example_provider' ),
				'html'    => $this->service_container->get( 'html_provider' ),
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

		// Make plugin instance globally accessible
		global $campaignbridge_plugin;
		$campaignbridge_plugin = $this;

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
		Post_Types::init_shared_state( self::OPTION_NAME, $this->providers );
		Settings::init_shared_state( self::OPTION_NAME, $this->providers );
		Status::init_shared_state( self::OPTION_NAME, $this->providers );

		// Add main menu page.
		add_menu_page(
			'CampaignBridge',
			'CampaignBridge',
			self::ADMIN_CAPABILITY,
			'campaignbridge',
			array( Post_Types::class, 'render' ),
			self::MENU_ICON,
			self::MENU_POSITION
		);

		// Add submenu pages.
		add_submenu_page(
			'campaignbridge',
			'Post Types',
			'Post Types',
			self::ADMIN_CAPABILITY,
			Post_Types::get_page_slug(),
			array( Post_Types::class, 'render' )
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
	 * Run security migration for existing plaintext API keys.
	 *
	 * This method checks for and migrates any existing plaintext API keys
	 * to encrypted storage. It runs once per plugin version to ensure
	 * backward compatibility and enhanced security.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function run_security_migration(): void {
		// Only run migration if encryption is available and we haven't migrated yet
		if ( ! Api_Key_Encryption::security_check()['secure'] ) {
			return;
		}

		$migration_version = '1.0.0'; // Version when encryption was implemented
		$migrated_version  = get_option( 'campaignbridge_migration_version', '0.0.0' );

		if ( version_compare( $migrated_version, $migration_version, '>=' ) ) {
			return; // Already migrated
		}

		// Run the migration
		$result = Api_Key_Encryption::migrate_plaintext_keys( self::OPTION_NAME );

		if ( ! empty( $result['migrated_fields'] ) ) {
			// Log successful migration
			error_log(
				sprintf(
					'CampaignBridge: Security migration completed. Migrated fields: %s',
					implode( ', ', $result['migrated_fields'] )
				)
			);

			// Mark migration as complete
			update_option( 'campaignbridge_migration_version', $migration_version );

			// Show admin notice about migration
			if ( current_user_can( 'manage_options' ) ) {
				add_action(
					'admin_notices',
					function () use ( $result ) {
						?>
					<div class="notice notice-success is-dismissible">
						<p>
							<strong>CampaignBridge Security Update:</strong>
							<?php
							printf(
								esc_html__( 'Successfully migrated %d sensitive fields to encrypted storage for enhanced security.', 'campaignbridge' ),
								$result['success']
							);
							?>
						</p>
					</div>
						<?php
					}
				);
			}
		}

		// Handle any migration errors
		if ( ! empty( $result['errors'] ) ) {
			error_log(
				sprintf(
					'CampaignBridge: Security migration errors: %s',
					implode( '; ', $result['errors'] )
				)
			);
		}
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
		// Main settings option
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

		// General settings tab
		register_setting(
			'campaignbridge_general',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'autoload'          => false,
				'sanitize_callback' => array( $this->settings_handler, 'sanitize' ),
				'default'           => array(),
			)
		);

		// Provider settings tab
		register_setting(
			'campaignbridge_providers',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'autoload'          => false,
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
		Asset_Manager::init();

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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'run_security_migration' ) );

		// Initialize Admin Pages.
		Status::init();
		Post_Types::init();
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
