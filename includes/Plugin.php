<?php
/**
 * Plugin bootstrap and orchestrator.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge;

use CampaignBridge\Admin\AssetManager;
use CampaignBridge\Blocks\Blocks;
use CampaignBridge\Admin\PostTypesPage;
use CampaignBridge\Admin\SettingsPage;
use CampaignBridge\Admin\StatusPage;
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
		add_action( 'admin_enqueue_scripts', array( AssetManager::class, 'enqueue_admin_assets' ) );

		// Initialize Blocks system.
		Blocks::init();

		// Initialize Email Template CPT.
		EmailTemplate::init();

		// Initialize Status Page.
		StatusPage::init();

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
	 * Register top-level admin menu and submenu pages.
	 *
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
	 * Register settings and sanitization callback for plugin options.
	 *
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
	 * Sanitize submitted settings before persistence.
	 *
	 * @param array $input Raw submitted values.
	 * @return array Cleaned settings array.
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
