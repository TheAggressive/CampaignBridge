<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Status Controller
 *
 * Auto-discovered and attached to status.php screen by naming convention:
 * - status.php file → Status_Controller class
 *
 * @package CampaignBridge\Admin\Controllers
 */

namespace CampaignBridge\Admin\Controllers;

/**
 * Status Controller class.
 *
 * Auto-discovered and attached to status.php screen by naming convention.
 *
 * @package CampaignBridge\Admin\Controllers
 */
class Status_Controller {

	/**
	 * Controller data array.
	 *
	 * @var array
	 */
	private array $data = [];

	/**
	 * Constructor - Initialize controller data.
	 */
	public function __construct() {
		// Initialize - load data needed by status screen.
		$this->load_system_info();
		$this->load_integration_status();
		$this->load_campaign_stats();
	}

	/**
	 * Get data for views (available via $screen->get())
   *
   * @return array
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Handle requests (called before render)
   *
   * @return void
	 */
	public function handle_request(): void {
		// Handle status-specific actions
		if ( isset( $_POST['refresh_stats'] ) ) {
			$this->handle_refresh_stats();
		}

		if ( isset( $_POST['clear_cache'] ) ) {
			$this->handle_clear_cache();
		}
	}

	/**
	 * Load system information
   *
   * @return void
   *
	 */
	private function load_system_info(): void {
		$this->data['system_info'] = [
			'plugin_version'     => defined( 'CAMPAIGNBRIDGE_VERSION' ) ? \CampaignBridge_Plugin::VERSION : '1.0.0',
			'wordpress_version'  => get_bloginfo( 'version' ),
			'php_version'        => PHP_VERSION,
			'server_software'    => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
			'memory_limit'       => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'debug_mode'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
		];
	}

	/**
	 * Load integration status for all providers
   *
   * @return void
	 */
	private function load_integration_status(): void {
		$this->data['integrations'] = [
			'mailchimp' => [
				'connected' => $this->is_mailchimp_connected(),
				'status'    => $this->get_mailchimp_status(),
				'last_test' => get_option( 'cb_mailchimp_last_test', 'Never tested' ),
			],
			'sendgrid'  => [
				'connected' => false,
				'status'    => 'Not configured',
				'last_test' => 'Never tested',
			],
		];
	}

	/**
	 * Load campaign statistics
   *
   * @return void
	 */
	private function load_campaign_stats(): void {
		$this->data['stats'] = [
			'total_campaigns' => $this->get_total_campaigns(),
			'total_sent'      => $this->get_total_sent(),
			'subscribers'     => $this->get_total_subscribers(),
			'open_rate'       => $this->get_open_rate(),
			'click_rate'      => $this->get_click_rate(),
		];
	}

	/**
	 * Check if Mailchimp is properly connected
   *
   * @return bool
	 */
	private function is_mailchimp_connected(): bool {
		$api_key = get_option( 'cb_mailchimp_api_key', '' );
		return ! empty( $api_key ) && strlen( $api_key ) > 20;
	}

	/**
	 * Get Mailchimp connection status
   *
   * @return string
	 */
	private function get_mailchimp_status(): string {
		if ( ! $this->is_mailchimp_connected() ) {
			return 'Not connected';
		}
		return 'Connected';
	}

	/**
	 * Get total campaigns count
   *
   * @return int
	 */
	private function get_total_campaigns(): int {
		// Mock data - in real implementation, query campaigns table
		return 42 + rand( 0, 5 );
	}

	/**
	 * Get total emails sent
   *
   * @return int
	 */
	private function get_total_sent(): int {
		// Mock data - in real implementation, sum from campaigns
		return 15637 + rand( 0, 100 );
	}

	/**
	 * Get total subscribers
   *
   * @return int
	 */
	private function get_total_subscribers(): int {
		// Mock data - in real implementation, count from subscriber tables
		return 1205 + rand( 0, 20 );
	}

	/**
	 * Get open rate
   *
   * @return string
	 */
	private function get_open_rate(): string {
		// Mock data - in real implementation, calculate from actual data
		$rate = 24.7 + ( rand( -20, 20 ) / 10 );
		return number_format( $rate, 1 ) . '%';
	}

	/**
	 * Get click rate
   *
   * @return string
	 */
	private function get_click_rate(): string {
		// Mock data - in real implementation, calculate from actual data
		$rate = 3.2 + ( rand( -10, 10 ) / 10 );
		return number_format( $rate, 1 ) . '%';
	}

	/**
	 * Handle refresh stats request
   *
   * @return void
	 */
	private function handle_refresh_stats(): void {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'cb_refresh_stats' ) ) {
			wp_die( 'Security check failed' );
		}

		// Clear any cached stats
		delete_transient( 'cb_dashboard_stats' );

		// Reload fresh data
		$this->load_campaign_stats();

		wp_redirect(add_query_arg([
			'page'      => 'campaignbridge-status',
			'refreshed' => 'success',
		],
		admin_url( 'admin.php' )));
		exit;
	}

	/**
	 * Handle clear cache request
   *
   * @return void
	 */
	private function handle_clear_cache(): void {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'cb_clear_cache' ) ) {
			wp_die( 'Security check failed' );
		}

		// Clear plugin caches
		wp_cache_flush_group( 'campaignbridge' );

		// Clear WordPress object cache if available
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		wp_redirect(add_query_arg([
			'page'          => 'campaignbridge-status',
			'cache_cleared' => 'success',
		],
		admin_url( 'admin.php' )));
		exit;
	}
}
