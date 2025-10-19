<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Settings Entry Point for CampaignBridge Admin Interface.
 *
 * Main entry point for the plugin settings page that handles tab navigation,
 * form submission, and delegates to specific tab implementations.
 *
 * @package CampaignBridge\Admin\Pages
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages;

use CampaignBridge\Admin\Pages\Admin;
use CampaignBridge\Admin\Pages\Tabs\Settings_Tab_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings: Main entry point for the plugin settings configuration interface.
 *
 * This class serves as the entry point for the settings page and handles:
 * - Page initialization and asset loading
 * - Form submission and processing using WordPress Settings API
 * - Tab navigation and rendering
 * - Delegation to specific tab implementations
 */
class Settings extends Admin {

	// === CONSTANTS ===

	/**
	 * Page slug for this admin page.
	 *
	 * @var string
	 */
	protected static string $page_slug = 'campaignbridge-settings';

	/**
	 * Submit button text.
	 */
	private const SUBMIT_BUTTON_TEXT = 'Save Settings';

	/**
	 * Required capability for settings management.
	 */
	private const REQUIRED_CAPABILITY = 'manage_options';

	// === INITIALIZATION ===

	/**
	 * Initialize the Settings page.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init(): void {
		// Initialize default tabs.
		Settings_Tab_Manager::init_default_tabs();

		// Register WordPress hooks.
		self::register_hooks();
	}

	/**
	 * Register all WordPress hooks for the settings page.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private static function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_debug_info' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings_sections' ) );
	}

	// === ASSET MANAGEMENT ===

	/**
	 * Enqueue settings page assets.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( ! \CampaignBridge\Admin\Page_Utils::is_current_page( static::get_page_slug() ) ) {
			return;
		}

		wp_enqueue_style( 'campaignbridge-settings' );
		wp_enqueue_script( 'campaignbridge-settings' );
	}


	/**
	 * Render the Settings configuration page.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'campaignbridge' ) );
		}

		self::render_settings_page();
	}

	/**
	 * Add debug information to settings page when WP_DEBUG is enabled.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function add_debug_info(): void {
		// Debug: Show current settings (only on CampaignBridge settings page)
		if ( isset( $_GET['page'] ) && 'campaignbridge-settings' === $_GET['page'] && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$current_settings = Settings_Manager::get_settings();
			$raw_settings = \CampaignBridge\Core\Storage::get_option( Settings_Manager::get_option_name(), array() );

			// Add raw API key to the debug object
			$current_settings['saved_database_api_key'] = $raw_settings['api_key'] ?? '';

			add_settings_error(
				'campaignbridge_messages', // Use the same group that gets displayed
				'debug_info',
				'DEBUG - Current settings: ' . wp_json_encode( $current_settings ),
				'info'
			);
		}
	}

	/**
	 * Register settings sections and fields using WordPress Settings API.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register_settings_sections(): void {
		// Register all settings groups for all tabs
		self::register_settings_groups();

		// Register sections based on current tab.
		$current_tab = Settings_Tab_Manager::get_current_tab();

		// Get the tab class and register its settings.
		$tab_class = Settings_Tab_Manager::get_tab_class( $current_tab );
		if ( $tab_class && class_exists( $tab_class ) ) {
			$tab_class::register_settings();
		}
	}

	/**
	 * Register settings groups for all tabs using WordPress Settings API.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private static function register_settings_groups(): void {
		// Register the main settings option once
		register_setting(
			'campaignbridge_settings',
			Settings_Manager::get_option_name(),
			array(
				'type' => 'array',
				'sanitize_callback' => array( Settings_Manager::class, 'sanitize_settings' ),
				'default' => array(),
			)
		);
	}

	// === PAGE RENDERING ===

	/**
	 * Render the complete settings page.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private static function render_settings_page(): void {
		$current_tab  = Settings_Tab_Manager::get_current_tab();
		$nonce_action = Settings_Manager::get_nonce_action( $current_tab );

		// Render page header and navigation
		self::render_page_header();

		// Render settings form
		self::render_settings_form( $current_tab, $nonce_action );
	}

	/**
	 * Render the page header with title and messages.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private static function render_page_header(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( self::get_page_title() ); ?></h1>

		<?php
		// Display success messages.
		self::display_messages();

		// Display save error if present.
		if ( isset( $_GET['settings-error'] ) && 'true' === $_GET['settings-error'] ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Settings could not be saved. Please try again.', 'campaignbridge' ) . '</p></div>';
		}
		?>
		</div>
		<?php
	}

	/**
	 * Render the settings form with tabs and content using WordPress Settings API.
	 *
	 * @since 0.1.0
	 * @param string $current_tab Current active tab.
	 * @param string $nonce_action Nonce action for form security.
	 * @return void
	 */
	private static function render_settings_form( string $current_tab, string $nonce_action ): void {
		?>
		<div class="wrap">
			<form method="post" action="options.php">
				<?php
				// Output security fields for the registered setting
				settings_fields( 'campaignbridge_settings' );

				// Render tab navigation.
				Settings_Tab_Manager::render_navigation();

				// Render current tab content using Settings API
				Settings_Tab_Manager::render_current_tab();
				?>

				<?php submit_button( self::SUBMIT_BUTTON_TEXT ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get the page title.
	 *
	 * @since 0.1.0
	 * @return string The localized page title.
	 */
	public static function get_page_title(): string {
		return __( 'CampaignBridge Settings', 'campaignbridge' );
	}
}
