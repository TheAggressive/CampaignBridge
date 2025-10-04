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
 * - Form submission and processing
 * - Tab navigation and rendering
 * - Delegation to specific tab implementations
 * - AJAX request handling for dynamic updates
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
	 * AJAX action for settings saving.
	 */
	private const AJAX_ACTION_SAVE = 'campaignbridge_save_settings';

	/**
	 * Nonce action for settings saving.
	 */
	private const NONCE_ACTION_SAVE = 'campaignbridge_save_settings';

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
		add_action( 'admin_init', array( __CLASS__, 'handle_form_submission' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings_sections' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_SAVE, array( __CLASS__, 'handle_ajax_save' ) );
	}

	// === ASSET MANAGEMENT ===

	/**
	 * Enqueue settings page assets and localize scripts.
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

		// Localize script with AJAX data for dynamic saving
		wp_localize_script( 'campaignbridge-settings', 'campaignbridgeSettings', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_ACTION_SAVE ),
			'action'  => self::AJAX_ACTION_SAVE,
		) );
	}

	// === FORM HANDLING ===

	/**
	 * Handle AJAX settings save request with enhanced security and validation.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function handle_ajax_save(): void {
		try {
			// Enhanced security checks
			self::validate_ajax_request();

			// Get and sanitize input with comprehensive validation
			$input_data = self::sanitize_and_validate_input();

			// Validate API key format with detailed error messages
			self::validate_api_key_format( $input_data['api_key'] );

			// Save settings with atomic operation
			$save_result = self::save_settings_atomically( $input_data );

			// Send success response with masked key for security
			wp_send_json_success( array(
				'message'    => __( 'API key saved successfully.', 'campaignbridge' ),
				'masked_key' => self::mask_api_key_for_response( $input_data['api_key'] ),
				'timestamp'  => current_time( 'mysql' ),
			) );

		} catch ( \Exception $e ) {
			// Log error for debugging
			self::log_error( 'AJAX settings save failed', array(
				'error'     => $e->getMessage(),
				'user_id'   => get_current_user_id(),
				'tab'       => $_POST['tab'] ?? 'unknown',
			) );

			// Send user-friendly error response
			wp_send_json_error( array(
				'message' => self::get_user_friendly_error_message( $e->getMessage() ),
			) );
		}
	}

	/**
	 * Validate AJAX request security and permissions.
	 *
	 * @since 0.1.0
	 * @throws \Exception If validation fails.
	 * @return void
	 */
	private static function validate_ajax_request(): void {
		// Verify nonce for CSRF protection
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', self::NONCE_ACTION_SAVE ) ) {
			throw new \Exception( 'Security check failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
			throw new \Exception( 'Insufficient permissions' );
		}

		// Validate request method
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			throw new \Exception( 'Invalid request method' );
		}

		// Check for required fields
		if ( empty( $_POST['api_key'] ) ) {
			throw new \Exception( 'API key is required' );
		}
	}

	/**
	 * Sanitize and validate input data.
	 *
	 * @since 0.1.0
	 * @throws \Exception If validation fails.
	 * @return array Sanitized input data.
	 */
	private static function sanitize_and_validate_input(): array {
		$api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
		$tab     = sanitize_text_field( $_POST['tab'] ?? 'providers' );

		// Validate tab parameter
		$valid_tabs = array( 'general', 'providers' );
		if ( ! in_array( $tab, $valid_tabs, true ) ) {
			throw new \Exception( 'Invalid tab parameter' );
		}

		// Validate API key length and characters
		if ( strlen( $api_key ) < 10 ) {
			throw new \Exception( 'API key too short' );
		}

		if ( strlen( $api_key ) > 100 ) {
			throw new \Exception( 'API key too long' );
		}

		return array(
			'api_key' => $api_key,
			'tab'     => $tab,
		);
	}

	/**
	 * Validate API key format with detailed error reporting.
	 *
	 * @since 0.1.0
	 * @param string $api_key The API key to validate.
	 * @throws \Exception If validation fails.
	 * @return void
	 */
	private static function validate_api_key_format( string $api_key ): void {
		// Mailchimp API key pattern validation
		$mailchimp_pattern = '/^[a-f0-9]{32}-us[0-9]+$/';

		if ( ! preg_match( $mailchimp_pattern, $api_key ) ) {
			throw new \Exception(
				'Invalid Mailchimp API key format. ' .
				'Expected format: 32 hexadecimal characters followed by -us and numbers ' .
				'(e.g., abc1234567890abcdef1234567890ab-us1)'
			);
		}

		// Additional security checks
		if ( preg_match( '/[<>"\'\\\\]/', $api_key ) ) {
			throw new \Exception( 'API key contains invalid characters' );
		}
	}

	/**
	 * Save settings with atomic operation and error handling.
	 *
	 * @since 0.1.0
	 * @param array $input_data Sanitized input data.
	 * @throws \Exception If save operation fails.
	 * @return bool Success status.
	 */
	private static function save_settings_atomically( array $input_data ): bool {
		// Get current settings
		$current_settings = get_option( 'campaignbridge_settings', array() );

		// Update only the API key field
		$current_settings['api_key'] = $input_data['api_key'];

		// Use atomic operation with error checking
		$success = update_option( 'campaignbridge_settings', $current_settings );

		if ( ! $success ) {
			throw new \Exception( 'Database update failed' );
		}

		// Log successful save for audit trail
		self::log_info( 'API key updated successfully', array(
			'user_id' => get_current_user_id(),
			'tab'     => $input_data['tab'],
		) );

		return true;
	}

	/**
	 * Generate masked API key for secure response.
	 *
	 * @since 0.1.0
	 * @param string $api_key The original API key.
	 * @return string Masked API key for display.
	 */
	private static function mask_api_key_for_response( string $api_key ): string {
		if ( strlen( $api_key ) <= 8 ) {
			return str_repeat( '•', strlen( $api_key ) );
		}

		return str_repeat( '•', strlen( $api_key ) - 4 ) . substr( $api_key, -4 );
	}

	/**
	 * Get user-friendly error message from technical error.
	 *
	 * @since 0.1.0
	 * @param string $technical_error Technical error message.
	 * @return string User-friendly error message.
	 */
	private static function get_user_friendly_error_message( string $technical_error ): string {
		$error_map = array(
			'Security check failed'           => __( 'Security verification failed. Please refresh the page and try again.', 'campaignbridge' ),
			'Insufficient permissions'        => __( 'You do not have permission to save settings.', 'campaignbridge' ),
			'Invalid request method'          => __( 'Invalid request. Please try again.', 'campaignbridge' ),
			'API key is required'             => __( 'API key is required.', 'campaignbridge' ),
			'API key too short'               => __( 'API key is too short.', 'campaignbridge' ),
			'API key too long'                => __( 'API key is too long.', 'campaignbridge' ),
			'Invalid Mailchimp API key format' => __( 'Invalid API key format. Please check your Mailchimp API key.', 'campaignbridge' ),
			'API key contains invalid characters' => __( 'API key contains invalid characters.', 'campaignbridge' ),
			'Database update failed'          => __( 'Failed to save settings. Please try again.', 'campaignbridge' ),
		);

		return $error_map[ $technical_error ] ?? __( 'An unexpected error occurred. Please try again.', 'campaignbridge' );
	}

	/**
	 * Log error for debugging purposes.
	 *
	 * @since 0.1.0
	 * @param string $message Error message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	private static function log_error( string $message, array $context = array() ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'[CampaignBridge Settings] %s | Context: %s',
					$message,
					wp_json_encode( $context )
				)
			);
		}
	}

	/**
	 * Log info for audit trail.
	 *
	 * @since 0.1.0
	 * @param string $message Info message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	private static function log_info( string $message, array $context = array() ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'[CampaignBridge Settings] %s | Context: %s',
					$message,
					wp_json_encode( $context )
				)
			);
		}
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
	 * Handle form submission and validation.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function handle_form_submission(): void {
		if ( ! isset( $_POST['submit'] ) ) {
			return;
		}

		// Get current tab for context-aware processing.
		$current_tab = Settings_Tab_Manager::get_current_tab();

		// Get submitted settings.
		$new_settings = $_POST[ Settings_Manager::get_option_name() ] ?? array();

		// Update settings through the manager (which handles nonce verification).
		$success = Settings_Manager::update_settings( $new_settings, $current_tab );

		if ( $success ) {
			// Redirect to avoid form resubmission and preserve current tab.
			$redirect_url = add_query_arg(
				array(
					'page'             => static::get_page_slug(),
					'tab'              => $current_tab,
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			);
			wp_redirect( $redirect_url );
			exit;
		} else {
			// Settings save failed - redirect back to settings page with error and preserve tab.
			$redirect_url = add_query_arg(
				array(
					'page'           => static::get_page_slug(),
					'tab'            => $current_tab,
					'settings-error' => 'true',
				),
				admin_url( 'admin.php' )
			);
			wp_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Register settings sections and fields using WordPress Settings API.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register_settings_sections(): void {
		// Register sections based on current tab.
		$current_tab = Settings_Tab_Manager::get_current_tab();

		// Get the tab class and register its settings.
		$tab_class = Settings_Tab_Manager::get_tab_class( $current_tab );
		if ( $tab_class && class_exists( $tab_class ) ) {
			$tab_class::register_settings();
		}
	}

	// === PAGE RENDERING ===

	/**
	 * Sanitize settings input.
	 *
	 * This method is a compatibility wrapper that delegates to the Settings_Manager.
	 *
	 * @since 0.1.0
	 * @param array $input Raw input data from the form.
	 * @return array Sanitized settings data.
	 */
	public static function sanitize_settings( array $input ): array {
		// Delegate sanitization to the Settings_Manager.
		return Settings_Manager::sanitize_settings( $input );
	}

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
			// Display validation errors if any.
			Settings_Manager::display_validation_errors();

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
	 * Render the settings form with tabs and content.
	 *
	 * @since 0.1.0
	 * @param string $current_tab Current active tab.
	 * @param string $nonce_action Nonce action for form security.
	 * @return void
	 */
	private static function render_settings_form( string $current_tab, string $nonce_action ): void {
		?>
		<div class="wrap">
			<form method="post" action="">
				<?php wp_nonce_field( $nonce_action, 'campaignbridge_settings_nonce' ); ?>

				<?php
				// Set option_page for compatibility with old Settings_Handler.
				$option_page = 'general' === $current_tab ? 'campaignbridge_general' : 'campaignbridge_providers';
				?>
				<input type="hidden" name="option_page" value="<?php echo esc_attr( $option_page ); ?>" />

				<?php
				// Render tab navigation.
				Settings_Tab_Manager::render_navigation();

				// Render current tab content.
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
