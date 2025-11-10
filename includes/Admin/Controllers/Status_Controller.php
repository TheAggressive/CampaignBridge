<?php
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
	 * @var array<string, mixed>
	 */
	private array $data = array();

	/**
	 * Constructor - Initialize controller data.
	 */
	public function __construct() {
		// Initialize - load data needed by status screen.
		$this->load_system_info();
		$this->load_plugin_info();
		$this->load_integrations_info();
		$this->load_stats_info();
	}

	/**
	 * Get data for views (available via $screen->get())
	 *
	 * @return array<string, mixed>
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Load system information
	 *
	 * @return void
	 */
	private function load_system_info(): void {
		global $wp_version;

		$this->data['system_info'] = array(
			'wordpress_version'  => $wp_version,
			'php_version'        => PHP_VERSION,
			'server_software'    => sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ) ),
			'memory_limit'       => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'upload_max_size'    => ini_get( 'upload_max_filesize' ),
			'post_max_size'      => ini_get( 'post_max_size' ),
		);
	}

	/**
	 * Load plugin information
	 *
	 * @return void
	 */
	private function load_plugin_info(): void {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = \CampaignBridge_Plugin::path() . 'campaignbridge.php';
		$plugin_data = get_plugin_data( $plugin_file );

		$this->data['plugin_info'] = array(
			'name'        => $plugin_data['Name'],
			'version'     => $plugin_data['Version'],
			'author'      => $plugin_data['Author'],
			'text_domain' => $plugin_data['TextDomain'],
		);
	}

	/**
	 * Load integrations information
	 *
	 * @return void
	 */
	private function load_integrations_info(): void {
		$this->data['integrations'] = array(
			'mailchimp' => array(
				'active'     => class_exists( 'CampaignBridge\\Providers\\Mailchimp_Provider' ),
				'configured' => false, // Would check if API key is set.
				'version'    => '1.0.0',
			),
			'html'      => array(
				'active'     => class_exists( 'CampaignBridge\\Providers\\Html_Provider' ),
				'configured' => true, // HTML export always works.
				'version'    => '1.0.0',
			),
		);
	}

	/**
	 * Load statistics information
	 *
	 * @return void
	 */
	private function load_stats_info(): void {
		$this->data['stats'] = array(
			'total_users'       => count_users()['total_users'],
			'total_posts'       => wp_count_posts()->publish ?? 0,
			'total_pages'       => wp_count_posts( 'page' )->publish ?? 0,
			'plugin_version'    => \CampaignBridge_Plugin::VERSION,
			'php_version'       => PHP_VERSION,
			'wordpress_version' => get_bloginfo( 'version' ),
		);
	}
}
