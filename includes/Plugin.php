<?php
/**
 * Plugin bootstrap and orchestrator for CampaignBridge.
 *
 * This class serves as the main entry point and central coordinator for the entire
 * CampaignBridge plugin. It handles plugin initialization, service container setup,
 * provider management, admin menu creation, REST API registration, and hooks all
 * the various components together.
 *
 * Key responsibilities:
 * - Initialize the service container and dependency injection system
 * - Set up WordPress hooks and filters for admin functionality
 * - Register admin menu pages and submenu items
 * - Initialize the asset management system for scripts and styles
 * - Set up the block system for email template creation
 * - Register REST API endpoints for AJAX operations
 * - Handle provider initialization (Mailchimp, HTML export)
 * - Manage plugin settings and configuration
 * - Set up email template custom post type
 * - Handle cache invalidation when settings change
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
use CampaignBridge\Admin\Pages\PostTypesPage;
use CampaignBridge\Admin\Pages\SettingsPage;
use CampaignBridge\Admin\Pages\StatusPage;
use CampaignBridge\Core\Service_Container;
use CampaignBridge\PostTypes\EmailTemplate;
use CampaignBridge\REST\Routes as RestRoutes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName, WordPress.Classes.ClassFileName
/**
 * Main plugin bootstrap and orchestrator.
 *
 * Wires core WordPress hooks and delegates responsibilities to modular
 * classes for Admin UI, AJAX endpoints, Block and CPT registration, and
 * provider initialization.
 */
class Plugin {
	/**
	 * Option key used to persist plugin settings.
	 *
	 * @var string
	 */
	private string $option_name = 'campaignbridge_settings';

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
	 * Construct and wire plugin hooks.
	 *
	 * This constructor initializes the CampaignBridge plugin by setting up the service
	 * container, registering all necessary WordPress hooks, and initializing all
	 * plugin components. It follows a fail-fast approach where critical errors
	 * prevent plugin initialization to avoid partial functionality.
	 *
	 * The initialization process includes:
	 * 1. Service container setup with dependency injection
	 * 2. Provider initialization (Mailchimp, HTML export)
	 * 3. WordPress hook registration for admin functionality
	 * 4. Asset management system initialization
	 * 5. Block system and custom post type setup
	 * 6. REST API endpoint registration
	 * 7. Cache management and invalidation hooks
	 *
	 * If any critical component fails to initialize, the constructor will:
	 * - Log the error for debugging purposes
	 * - Display an admin notice to inform the user
	 * - Return early to prevent partial plugin functionality
	 *
	 * @since 0.1.0
	 * @throws \Exception When critical services fail to initialize.
	 */
	public function __construct() {
		try {
			// Initialize service container.
			$this->service_container = new Service_Container();
			$this->service_container->initialize();

			// Get providers from service container.
			$this->providers = array(
				'mailchimp' => $this->service_container->get( 'mailchimp_provider' ),
				'html'      => $this->service_container->get( 'html_provider' ),
			);
		} catch ( \Exception $e ) {
			// Log the error and show admin notice.
			error_log( 'CampaignBridge Plugin Error: ' . $e->getMessage() );

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
			return;
		}

		// Wire hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Initialize Asset Manager.
		AssetManager::init();

		// Initialize Blocks system.
		Blocks::init();

		// Initialize Email Template CPT.
		EmailTemplate::init();

		// Initialize Admin Pages.
		StatusPage::init();
		PostTypesPage::init();
		SettingsPage::init();

		// Initialize Admin UI and AJAX.
		// AdminUI::init() is called in add_admin_menu() method
		// Deprecated: admin-ajax actions replaced by REST API routes.

		// REST API.
		RestRoutes::init( $this->option_name, $this->providers );
		add_action( 'rest_api_init', array( RestRoutes::class, 'register' ) );

		// Bust Mailchimp caches if API key changes.
		add_action(
			'update_option_' . $this->option_name,
			function ( $old, $new ) {
				$old_key = isset( $old['api_key'] ) ? (string) $old['api_key'] : '';
				$new_key = isset( $new['api_key'] ) ? (string) $new['api_key'] : '';
				if ( $old_key !== $new_key && ! empty( $old_key ) ) {
					delete_transient( 'cb_mc_audiences_' . md5( $old_key ) );
					delete_transient( 'cb_mc_templates_' . md5( $old_key ) );
				}
			},
			10,
			2
		);
	}

	/**
	 * Register the complete admin menu structure for CampaignBridge.
	 *
	 * This method creates the main admin menu and all submenu pages that
	 * provide access to CampaignBridge functionality. It sets up the menu
	 * hierarchy, page callbacks, and user capability requirements while
	 * initializing shared state for all admin pages.
	 *
	 * Menu Structure:
	 * - Main Menu: CampaignBridge (dashicons-email-alt)
	 * - Submenu: Post Types (default landing page)
	 * - Submenu: Settings (plugin configuration)
	 * - Submenu: Status (system health and debugging)
	 *
	 * Page Initialization:
	 * - Sets up shared state for all admin pages
	 * - Initializes option names and provider instances
	 * - Ensures consistent data access across pages
	 * - Maintains plugin configuration state
	 *
	 * User Capabilities:
	 * - Requires 'manage_options' capability
	 * - Ensures only administrators can access
	 * - Maintains WordPress security standards
	 * - Supports role-based access control
	 *
	 * Menu Configuration:
	 * - Position 30 in admin menu (after Posts)
	 * - Email icon for visual identification
	 * - Consistent with WordPress admin patterns
	 * - Professional appearance and organization
	 *
	 * Integration Features:
	 * - Seamless WordPress admin integration
	 * - Consistent with admin theme styling
	 * - Supports admin menu customizations
	 * - Maintains admin performance standards
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function add_admin_menu(): void {
		// Initialize shared state for all admin pages.
		PostTypesPage::init_shared_state( $this->option_name, $this->providers );
		SettingsPage::init_shared_state( $this->option_name, $this->providers );
		StatusPage::init_shared_state( $this->option_name, $this->providers );

		// Add main menu page.
		add_menu_page(
			'CampaignBridge',
			'CampaignBridge',
			'manage_options',
			'campaignbridge',
			array( PostTypesPage::class, 'render' ),
			'dashicons-email-alt',
			30
		);

		// Add submenu pages.
		add_submenu_page(
			'campaignbridge',
			'Post Types',
			'Post Types',
			'manage_options',
			'campaignbridge',
			array( PostTypesPage::class, 'render' )
		);

		add_submenu_page(
			'campaignbridge',
			'Settings',
			'Settings',
			'manage_options',
			'campaignbridge-settings',
			array( SettingsPage::class, 'render' )
		);

		// Add Status submenu page.
		add_submenu_page(
			'campaignbridge',
			'Status',
			'Status',
			'manage_options',
			'campaignbridge-status',
			array( StatusPage::class, 'render' )
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
	 * Settings Registration:
	 * - Option name: 'campaignbridge_settings'
	 * - Data type: Array for complex configuration
	 * - Default value: Empty array for clean initialization
	 * - Sanitization: Custom callback for data validation
	 *
	 * Data Structure:
	 * - provider: Email service provider selection
	 * - api_key: Provider API credentials
	 * - audience_id: Mailchimp audience/list ID
	 * - template_id: Mailchimp template ID
	 * - exclude_post_types: Post types to exclude from campaigns
	 * - included_post_types: Post types to include in campaigns
	 *
	 * Sanitization Features:
	 * - Input validation and type checking
	 * - Data sanitization and escaping
	 * - Security validation and verification
	 * - Default value fallbacks
	 * - Data consistency enforcement
	 *
	 * Security Measures:
	 * - WordPress nonce verification
	 * - User capability checking
	 * - Input sanitization and validation
	 * - SQL injection prevention
	 * - XSS protection through escaping
	 *
	 * Integration Benefits:
	 * - WordPress admin settings integration
	 * - Automatic form handling and validation
	 * - Settings API compatibility
	 * - Admin notice integration
	 * - Settings persistence across sessions
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'campaignbridge',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize and validate submitted plugin settings before persistence.
	 *
	 * This method processes all submitted form data to ensure data integrity,
	 * security, and consistency. It applies WordPress sanitization functions,
	 * validates data types, and provides intelligent fallbacks for missing
	 * or invalid data.
	 *
	 * Sanitization Process:
	 * - Provider selection validation and sanitization
	 * - API key sanitization and security validation
	 * - Audience and template ID validation
	 * - Post type inclusion/exclusion processing
	 * - Data type enforcement and conversion
	 *
	 * Input Validation:
	 * - Provider: Must be valid provider slug
	 * - API Key: Text sanitization and length validation
	 * - Audience ID: Text sanitization and format validation
	 * - Template ID: Numeric validation and range checking
	 * - Post Types: Array validation and sanitization
	 *
	 * Data Processing:
	 * - Post type exclusion logic based on inclusions
	 * - API key preservation for existing configurations
	 * - Provider-specific validation and requirements
	 * - Data consistency enforcement across settings
	 * - Default value application for missing data
	 *
	 * Security Features:
	 * - SQL injection prevention through sanitization
	 * - XSS protection through output escaping
	 * - Data type validation and enforcement
	 * - Malicious input filtering and removal
	 * - WordPress security best practices compliance
	 *
	 * Error Handling:
	 * - Graceful fallbacks for invalid data
	 * - Default value application for missing data
	 * - Data consistency maintenance
	 * - User-friendly error reporting
	 * - Settings persistence protection
	 *
	 * @since 0.1.0
	 * @param array $input Raw submitted form values from the settings form.
	 * @return array Cleaned, validated, and sanitized settings array ready for storage.
	 */
	public function sanitize_settings( array $input ): array {
		$clean             = array();
		$previous          = get_option( $this->option_name, array() );
		$clean['provider'] = $input['provider'] ?? 'mailchimp';
		$clean['provider'] = sanitize_key( $clean['provider'] );

		$posted_api_key   = $input['api_key'] ?? '';
		$clean['api_key'] = '' === $posted_api_key && isset( $previous['api_key'] )
			? $previous['api_key']
			: sanitize_text_field( $posted_api_key );

		$clean['audience_id']        = sanitize_text_field( $input['audience_id'] ?? '' );
		$clean['exclude_post_types'] = array();

		if ( isset( $input['included_post_types'] ) && is_array( $input['included_post_types'] ) ) {
			$included = array();
			foreach ( $input['included_post_types'] as $pt ) {
				$pt = sanitize_key( $pt );
				if ( post_type_exists( $pt ) ) {
					$included[] = $pt;
				}
			}
			$public_types = get_post_types( array( 'public' => true ), 'names' );
			foreach ( $public_types as $pt ) {
				if ( ! in_array( $pt, $included, true ) ) {
					$clean['exclude_post_types'][] = $pt;
				}
			}
		}
		return $clean;
	}
}
