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

use CampaignBridge\Domain\Email\Brand_Kit;
use CampaignBridge\Domain\Email\Theme_Brand_Mapper;
use CampaignBridge\Core\Encryption;
use CampaignBridge\Core\Storage;
use CampaignBridge\Providers\Mailchimp_Provider;
use CampaignBridge\Repository\Brand_Kit_Repository;
use CampaignBridge\Repository\Theme_Style_Reader;

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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in individual handler methods
		$reset_settings = isset( $_POST['reset_all_settings'] ) ? sanitize_text_field( wp_unslash( $_POST['reset_all_settings'] ) ) : '';
		if ( ! empty( $reset_settings ) ) {
			$this->handle_reset_all_settings();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in individual handler methods
		$export_settings = isset( $_POST['export_settings'] ) ? sanitize_text_field( wp_unslash( $_POST['export_settings'] ) ) : '';
		if ( ! empty( $export_settings ) ) {
			$this->handle_export_settings();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in individual handler methods
		$import_settings = isset( $_POST['import_settings'] ) ? sanitize_text_field( wp_unslash( $_POST['import_settings'] ) ) : '';
		if ( ! empty( $import_settings ) ) {
			$this->handle_import_settings();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in individual handler methods
		$import_brand = isset( $_POST['import_brand_kit'] ) ? sanitize_text_field( wp_unslash( $_POST['import_brand_kit'] ) ) : '';
		if ( ! empty( $import_brand ) ) {
			$this->handle_import_brand_kit();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification handled in individual handler methods
		$restore_brand = isset( $_POST['restore_brand_kit'] ) ? sanitize_text_field( wp_unslash( $_POST['restore_brand_kit'] ) ) : '';
		if ( ! empty( $restore_brand ) ) {
			$this->handle_restore_brand_kit();
		}
	}

	/**
	 * Load settings data.
	 *
	 * @return void
	 */
	private function load_settings_data(): void {
		$admin_email          = get_bloginfo( 'admin_email' );
		$mailchimp_connection = $this->get_mailchimp_connection();
		$mailchimp_audiences  = $this->get_mailchimp_audiences( $mailchimp_connection['connected'] );
		$this->data           = array(
			// General settings data.
			'from_name'                => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_from_name', get_bloginfo( 'name' ) ),
			'from_email'               => Storage::get_option( 'campaignbridge_from_email', $admin_email ),
			'reply_to'                 => Storage::get_option( 'campaignbridge_reply_to', $admin_email ),
			'default_footer'           => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_default_footer', '' ),
			'enable_preview_text'      => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_enable_preview_text', true ),
			'featured_image_size'      => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_featured_image_size', 'large' ),
			'excerpt_length'           => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_excerpt_length', 120 ),
			'cta_label'                => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_cta_label', __( 'Read more', 'campaignbridge' ) ),

			// Mailchimp integration data.
			'mailchimp_api_key'        => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_mailchimp_api_key', '' ),
			'mailchimp_audience'       => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_mailchimp_audience', '' ),
			'mailchimp_connected'      => $mailchimp_connection['connected'],
			'mailchimp_status'         => $mailchimp_connection['status'],
			'mailchimp_last_test'      => $mailchimp_connection['checked_at'],
			'mailchimp_audiences'      => $mailchimp_audiences['options'],
			'mailchimp_audience_error' => $mailchimp_audiences['error'],

			// Advanced settings data.
			'debug_mode'               => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_debug_mode', false ),
			'log_level'                => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_log_level', 'info' ),
			'cache_duration'           => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_cache_duration', 3600 ),
			'rate_limit'               => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_rate_limit', 100 ),

			// System info.
			'plugin_version'           => defined( 'CAMPAIGNBRIDGE_VERSION' ) ? \CampaignBridge_Plugin::VERSION : '1.0.0',
			'wordpress_version'        => get_bloginfo( 'version' ),
			'php_version'              => PHP_VERSION,

		);
	}

	/**
	 * Get cached, normalized Mailchimp audience choices.
	 *
	 * @param bool $connected Whether the stored credential was verified.
	 * @return array{options: array<string, string>, error: string}
	 */
	private function get_mailchimp_audiences( bool $connected ): array {
		if ( ! $connected ) {
			return array(
				'options' => array(),
				'error'   => '',
			);
		}

		$stored_key = Storage::get_option( 'campaignbridge_mailchimp_api_key', '' );
		try {
			$api_key = is_string( $stored_key ) ? Encryption::decrypt( $stored_key ) : '';
		} catch ( \Throwable $error ) {
			return array(
				'options' => array(),
				'error'   => __( 'Reconnect Mailchimp to load audiences.', 'campaignbridge' ),
			);
		}

		$cache_key = 'mailchimp_audiences_' . hash( 'sha256', $api_key );
		$cached    = Storage::get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return array(
				'options' => $cached,
				'error'   => '',
			);
		}

		$result = ( new Mailchimp_Provider() )->get_audiences( array( 'api_key' => $api_key ) );
		if ( is_wp_error( $result ) ) {
			return array(
				'options' => array(),
				'error'   => $result->get_error_message(),
			);
		}

		Storage::set_transient( $cache_key, $result, 15 * MINUTE_IN_SECONDS );
		return array(
			'options' => $result,
			'error'   => '',
		);
	}

	/**
	 * Load integration status for all providers
	 *
	 * @return void
	 */
	private function load_integration_status(): void {
		$connection                 = array(
			'connected'  => (bool) ( $this->data['mailchimp_connected'] ?? false ),
			'status'     => (string) ( $this->data['mailchimp_status'] ?? __( 'Not configured', 'campaignbridge' ) ),
			'checked_at' => $this->data['mailchimp_last_test'] ?? null,
		);
		$this->data['integrations'] = array(
			'mailchimp' => $connection,
			'sendgrid'  => array(
				'connected' => false,
				'status'    => 'Not configured',
				'last_test' => 'Never tested',
			),
		);
	}

	/**
	 * Get a credential-specific, verified Mailchimp connection result.
	 *
	 * @return array{connected: bool, status: string, checked_at: string|null}
	 */
	private function get_mailchimp_connection(): array {
		$stored_key = Storage::get_option( 'campaignbridge_mailchimp_api_key', '' );
		if ( ! is_string( $stored_key ) || '' === $stored_key ) {
			return array(
				'connected'  => false,
				'status'     => __( 'Not configured', 'campaignbridge' ),
				'checked_at' => null,
			);
		}

		try {
			$api_key = Encryption::decrypt( $stored_key );
		} catch ( \Throwable $error ) {
			return array(
				'connected'  => false,
				'status'     => __( 'Stored credentials could not be read', 'campaignbridge' ),
				'checked_at' => null,
			);
		}

		$provider = new Mailchimp_Provider();
		if ( ! $provider->is_configured( array( 'api_key' => $api_key ) ) ) {
			return array(
				'connected'  => false,
				'status'     => __( 'Invalid API key format', 'campaignbridge' ),
				'checked_at' => null,
			);
		}

		$cache_key = 'mailchimp_connection_' . hash( 'sha256', $api_key );
		$cached    = Storage::get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['connected'], $cached['status'], $cached['checked_at'] ) ) {
			return $cached;
		}

		$checked_at = current_time( 'mysql' );
		$result     = $provider->verify_connection( array( 'api_key' => $api_key ) );
		$connection = is_wp_error( $result )
			? array(
				'connected'  => false,
				'status'     => $result->get_error_message(),
				'checked_at' => $checked_at,
			)
			: array(
				'connected'  => true,
				'status'     => __( 'Connected', 'campaignbridge' ),
				'checked_at' => $checked_at,
			);

		Storage::set_transient( $cache_key, $connection, 5 * MINUTE_IN_SECONDS );
		return $connection;
	}

	/**
	 * Handle reset all settings
	 *
	 * @return void
	 */
	private function handle_reset_all_settings(): void {
		// Double security: sanitize input before nonce verification.
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'campaignbridge_reset_all' ) ) {
			wp_die( esc_html__( 'Security check failed', 'campaignbridge' ) );
		}

		// Rate limiting for destructive actions.
		$rate_limit_key = 'reset_settings_' . get_current_user_id();
		$last_reset     = \CampaignBridge\Core\Storage::get_transient( $rate_limit_key );

		if ( $last_reset && ( time() - $last_reset ) < 300 ) { // 5 minutes
			wp_die( 'Please wait 5 minutes before resetting settings again.' );
		}

		// Reset all plugin options.
		$options_to_reset = array(
			'campaignbridge_from_name',
			'campaignbridge_from_email',
			'campaignbridge_reply_to',
			'campaignbridge_default_footer',
			'campaignbridge_enable_preview_text',
			'campaignbridge_featured_image_size',
			'campaignbridge_excerpt_length',
			'campaignbridge_cta_label',
			'campaignbridge_mailchimp_api_key',
			'campaignbridge_mailchimp_audience',
			'campaignbridge_debug_mode',
			'campaignbridge_log_level',
			'campaignbridge_cache_duration',
			'campaignbridge_rate_limit',
			'campaignbridge_brand_kit',
		);

		foreach ( $options_to_reset as $option ) {
			\CampaignBridge\Core\Storage::delete_option( $option );
		}

		// Set rate limiting transient.
		\CampaignBridge\Core\Storage::set_transient( $rate_limit_key, time(), 300 ); // 5 minutes

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'campaignbridge-settings',
					'reset' => 'success',
				),
				\admin_url( 'admin.php' )
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
		// Double security: sanitize input before nonce verification.
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'campaignbridge_export_settings' ) ) {
			wp_die( esc_html__( 'Security check failed', 'campaignbridge' ) );
		}

		// Check user capabilities.
		if ( ! \current_user_can( 'campaignbridge_manage' ) ) {
			wp_die( 'You do not have permission to export settings.' );
		}

		$settings = array(
			'from_name'      => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_from_name' ),
			'from_email'     => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_from_email' ),
			'debug_mode'     => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_debug_mode' ),
			'cache_duration' => \CampaignBridge\Core\Storage::get_option( 'campaignbridge_cache_duration' ),
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
		// Double security: sanitize input before nonce verification.
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'campaignbridge_import_settings' ) ) {
			wp_die( esc_html__( 'Security check failed', 'campaignbridge' ) );
		}

		// Check user capabilities.
		if ( ! \current_user_can( 'campaignbridge_manage' ) ) {
			wp_die( 'You do not have permission to import settings.' );
		}

		$import_file_error = isset( $_FILES['import_file']['error'] ) ? intval( $_FILES['import_file']['error'] ) : UPLOAD_ERR_OK;
		if ( UPLOAD_ERR_OK !== $import_file_error ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'campaignbridge-settings',
						'import'  => 'error',
						'message' => 'File upload failed',
					),
					\admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Validate file upload security.
		$allowed_mime_types = array( 'application/json', 'text/plain' );
		$uploaded_file_name = isset( $_FILES['import_file']['name'] ) ? sanitize_file_name( $_FILES['import_file']['name'] ) : '';
		$uploaded_file_type = wp_check_filetype( $uploaded_file_name );

		if ( ! in_array( $uploaded_file_type['type'], $allowed_mime_types, true ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'campaignbridge-settings',
						'import'  => 'error',
						'message' => 'Invalid file type. Only JSON files are allowed.',
					),
					\admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Check file size (max 1MB for settings).
		$max_size           = 1024 * 1024; // 1MB
		$uploaded_file_size = isset( $_FILES['import_file']['size'] ) ? intval( $_FILES['import_file']['size'] ) : 0;
		if ( $uploaded_file_size > $max_size ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'campaignbridge-settings',
						'import'  => 'error',
						'message' => 'File too large. Maximum size is 1MB.',
					),
					\admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Secure file reading for uploaded files.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES tmp_name is system-generated and safe for upload validation
		$uploaded_file_tmp_name = isset( $_FILES['import_file']['tmp_name'] ) ? $_FILES['import_file']['tmp_name'] : '';
		if ( ! is_uploaded_file( $uploaded_file_tmp_name ) ) {
			wp_die( 'Invalid file upload' );
		}

		$content = file_get_contents( $uploaded_file_tmp_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,CampaignBridge.Standard.Sniffs.Http.DirectHttpRequest.DirectHttpFunction,WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Reading a locally validated PHP upload temporary file, never a remote URL.
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
					\admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// Import valid settings.
		$valid_options = array( 'from_name', 'from_email', 'debug_mode', 'cache_duration' );
		foreach ( $valid_options as $option ) {
			if ( isset( $settings[ $option ] ) ) {
				\CampaignBridge\Core\Storage::update_option( 'campaignbridge_' . $option, $settings[ $option ] );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'campaignbridge-settings',
					'import' => 'success',
				),
				\admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Copy portable theme colours into the stored brand kit.
	 *
	 * @return void
	 */
	private function handle_import_brand_kit(): void {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'campaignbridge_import_brand' ) ) {
			wp_die( esc_html__( 'Security check failed', 'campaignbridge' ) );
		}

		if ( ! current_user_can( 'campaignbridge_manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to update the brand kit.', 'campaignbridge' ) );
		}

		$repository = new Brand_Kit_Repository();
		$current    = $repository->get();
		$imported   = Theme_Brand_Mapper::from_theme( ( new Theme_Style_Reader() )->extract() );
		$kit        = Brand_Kit::from_colors(
			$imported->to_array()['colors'],
			Brand_Kit::SOURCE_THEME,
			$imported->theme_fingerprint(),
			$current->fonts(),
			$current->custom_font()
		);
		$saved      = $repository->save( $kit );
		$result     = $saved ? array( 'imported' => 'theme' ) : array( 'brand_error' => 'import' );

		wp_safe_redirect(
			add_query_arg(
				array_merge(
					array(
						'page' => 'campaignbridge-settings',
						'tab'  => 'brand',
					),
					$result
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Remove the stored kit so CampaignBridge defaults return.
	 *
	 * @return void
	 */
	private function handle_restore_brand_kit(): void {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'campaignbridge_restore_brand' ) ) {
			wp_die( esc_html__( 'Security check failed', 'campaignbridge' ) );
		}

		if ( ! current_user_can( 'campaignbridge_manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to update the brand kit.', 'campaignbridge' ) );
		}

		$cleared = ( new Brand_Kit_Repository() )->clear();
		$result  = $cleared ? array( 'restored' => '1' ) : array( 'brand_error' => 'restore' );

		wp_safe_redirect(
			add_query_arg(
				array_merge(
					array(
						'page' => 'campaignbridge-settings',
						'tab'  => 'brand',
					),
					$result
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
