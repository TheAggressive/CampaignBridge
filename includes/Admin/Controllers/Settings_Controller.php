<?php // phpcs:ignore WordPress.Files.FileName
/**
 * Settings Controller
 *
 * Auto-discovered and attached to settings/ screen by naming convention:
 * - settings/ folder → Settings_Controller class
 * - email_templates/ folder → Email_Templates_Controller class
 * - dashboard.php file → Dashboard_Controller class
 *
 * @package CampaignBridge\Admin\Controllers
 */

namespace CampaignBridge\Admin\Controllers;

/**
 * Settings Controller class.
 *
 * Auto-discovered and attached to settings/ screen by naming convention.
 *
 * @package CampaignBridge\Admin\Controllers
 */
class Settings_Controller {

	/**
	 * Controller data array.
	 *
	 * @var array<string, mixed>
	 */
	private array $data = array();

	/**
	 * Constructor - Initialize controller data.
	 */
	public function __construct() {
		// Initialize - load data needed by all tabs.
		$this->load_settings_data();
		$this->load_integration_status();
	}

	/**
	 * Get data for views (available in all tabs via $screen->get())
	 *
	 * @return array<string, mixed>
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Handle requests (called before any tab renders)
	 * Perfect place for form processing that affects multiple tabs
	 *
	 * @return void
	 */
	public function handle_request(): void {
		// Global settings actions can be handled here.
		if ( isset( $_POST['reset_all_settings'] ) ) {
			$this->handle_reset_all_settings();
		}

		if ( isset( $_POST['export_settings'] ) ) {
			$this->handle_export_settings();
		}

		if ( isset( $_POST['import_settings'] ) ) {
			$this->handle_import_settings();
		}
	}

	/**
	 * Load settings data.
	 *
	 * @return void
	 */
	private function load_settings_data(): void {
		$this->data = array(
			// General settings data.
			'from_name'           => \CampaignBridge\Core\Storage::get_option( 'cb_from_name', get_bloginfo( 'name' ) ),
			'from_email'          => \CampaignBridge\Core\Storage::get_option( 'cb_from_email', get_option( 'admin_email' ) ),
			'reply_to'            => \CampaignBridge\Core\Storage::get_option( 'cb_reply_to', get_option( 'admin_email' ) ),

			// Mailchimp integration data.
			'mailchimp_api_key'   => \CampaignBridge\Core\Storage::get_option( 'cb_mailchimp_api_key', '' ),
			'mailchimp_audience'  => \CampaignBridge\Core\Storage::get_option( 'cb_mailchimp_audience', '' ),
			'mailchimp_connected' => $this->is_mailchimp_connected(),

			// Advanced settings data.
			'debug_mode'          => \CampaignBridge\Core\Storage::get_option( 'cb_debug_mode', false ),
			'log_level'           => \CampaignBridge\Core\Storage::get_option( 'cb_log_level', 'info' ),
			'cache_duration'      => \CampaignBridge\Core\Storage::get_option( 'cb_cache_duration', 3600 ),
			'rate_limit'          => \CampaignBridge\Core\Storage::get_option( 'cb_rate_limit', 100 ),

			// System info.
			'plugin_version'      => defined( 'CAMPAIGNBRIDGE_VERSION' ) ? \CampaignBridge_Plugin::VERSION : '1.0.0',
			'wordpress_version'   => get_bloginfo( 'version' ),
			'php_version'         => PHP_VERSION,

			// Statistics.
			'total_subscribers'   => $this->get_total_subscribers(),
			'total_campaigns'     => $this->get_total_campaigns(),
			'last_sync'           => \CampaignBridge\Core\Storage::get_option( 'cb_last_sync', 'Never' ),
		);
	}

	/**
	 * Load integration status for all providers
	 *
	 * @return void
	 */
	private function load_integration_status(): void {
		$this->data['integrations'] = array(
			'mailchimp' => array(
				'connected' => $this->is_mailchimp_connected(),
				'status'    => $this->get_mailchimp_status(),
				'last_test' => \CampaignBridge\Core\Storage::get_option( 'cb_mailchimp_last_test', 'Never tested' ),
			),
			'sendgrid'  => array(
				'connected' => false,
				'status'    => 'Not configured',
				'last_test' => 'Never tested',
			),
		);
	}

	/**
	 * Check if Mailchimp is properly connected
	 */
	private function is_mailchimp_connected(): bool {
		$api_key = \CampaignBridge\Core\Storage::get_option( 'cb_mailchimp_api_key', '' );
		return ! empty( $api_key ) && strlen( $api_key ) > 20;
	}

	/**
	 * Get Mailchimp connection status
	 */
	private function get_mailchimp_status(): string {
		if ( ! $this->is_mailchimp_connected() ) {
			return 'Not connected';
		}

		// In a real implementation, you'd test the API connection.
		return 'Connected';
	}

	/**
	 * Get total subscribers count
	 */
	private function get_total_subscribers(): int {
		// Mock data - in real implementation, aggregate from all lists.
		return 1299;
	}

	/**
	 * Get total campaigns count
	 */
	private function get_total_campaigns(): int {
		// Mock data - in real implementation, count from database.
		return 42;
	}

	/**
	 * Handle reset all settings
	 *
	 * @return void
	 */
	private function handle_reset_all_settings(): void {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'cb_reset_all' ) ) {
			wp_die( 'Security check failed' );
		}

		// Rate limiting for destructive actions
		$rate_limit_key = 'reset_settings_' . get_current_user_id();
		$last_reset     = \CampaignBridge\Core\Storage::get_transient( $rate_limit_key );

		if ( $last_reset && ( time() - $last_reset ) < 300 ) { // 5 minutes
			wp_die( 'Please wait 5 minutes before resetting settings again.' );
		}

		// Reset all plugin options.
		$options_to_reset = array(
			'cb_from_name',
			'cb_from_email',
			'cb_mailchimp_api_key',
			'cb_mailchimp_audience',
			'cb_debug_mode',
			'cb_log_level',
			'cb_cache_duration',
			'cb_rate_limit',
		);

		foreach ( $options_to_reset as $option ) {
			\CampaignBridge\Core\Storage::delete_option( $option );
		}

		// Set rate limiting transient
		\CampaignBridge\Core\Storage::set_transient( $rate_limit_key, time(), 300 ); // 5 minutes

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'campaignbridge-settings',
					'reset' => 'success',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle settings export.
	 *
	 * @return void
	 */
	private function handle_export_settings(): void {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'cb_export_settings' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to export settings.' );
		}

		$settings = array(
			'from_name'      => \CampaignBridge\Core\Storage::get_option( 'cb_from_name' ),
			'from_email'     => \CampaignBridge\Core\Storage::get_option( 'cb_from_email' ),
			'debug_mode'     => \CampaignBridge\Core\Storage::get_option( 'cb_debug_mode' ),
			'cache_duration' => \CampaignBridge\Core\Storage::get_option( 'cb_cache_duration' ),
			'exported_at'    => current_time( 'mysql' ),
			'exported_by'    => wp_get_current_user()->user_login,
		);

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="campaignbridge-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $settings, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Handle settings import
	 *
	 * @return void
	 */
	private function handle_import_settings(): void {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'cb_import_settings' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to import settings.' );
		}

		if ( ! isset( $_FILES['import_file'] ) || UPLOAD_ERR_OK !== $_FILES['import_file']['error'] ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'campaignbridge-settings',
						'import'  => 'error',
						'message' => 'File upload failed',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Validate file upload security
		$allowed_mime_types = array( 'application/json', 'text/plain' );
		$uploaded_file_type = wp_check_filetype( $_FILES['import_file']['name'] );

		if ( ! in_array( $uploaded_file_type['type'], $allowed_mime_types, true ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'campaignbridge-settings',
						'import'  => 'error',
						'message' => 'Invalid file type. Only JSON files are allowed.',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Check file size (max 1MB for settings)
		$max_size = 1024 * 1024; // 1MB
		if ( $_FILES['import_file']['size'] > $max_size ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'campaignbridge-settings',
						'import'  => 'error',
						'message' => 'File too large. Maximum size is 1MB.',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Secure file reading for uploaded files
		if ( ! is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
			wp_die( 'Invalid file upload' );
		}

		$content = file_get_contents( $_FILES['import_file']['tmp_name'] );
		if ( false === $content ) {
			wp_die( 'Failed to read uploaded file' );
		}

		$settings = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'campaignbridge-settings',
						'import'  => 'error',
						'message' => 'Invalid JSON file',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Import valid settings.
		$valid_options = array( 'from_name', 'from_email', 'debug_mode', 'cache_duration' );
		foreach ( $valid_options as $option ) {
			if ( isset( $settings[ $option ] ) ) {
				\CampaignBridge\Core\Storage::update_option( 'cb_' . $option, $settings[ $option ] );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'campaignbridge-settings',
					'import' => 'success',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
