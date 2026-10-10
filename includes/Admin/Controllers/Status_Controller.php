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
		$this->load_health_info();
		$this->load_encryption_info();
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
	 * Load CampaignBridge health from stored state.
	 *
	 * Every figure is read from the site: the provider connection, published
	 * templates, campaign states, and the schema. Nothing is assumed or fixed.
	 *
	 * @return void
	 */
	private function load_health_info(): void {
		$connection = ( new \CampaignBridge\Repository\Provider_Connection_Repository() )->get( 'mailchimp' );
		$templates  = wp_count_posts( \CampaignBridge\Post_Types\Post_Type_Email_Template::POST_TYPE );
		$schema     = \CampaignBridge\Repository\Schema_Manager::is_current();
		$counts     = $schema ? ( new \CampaignBridge\Repository\Campaign_Repository() )->state_counts() : array();

		$this->data['health'] = array(
			'schema_current'      => $schema,
			'provider_connected'  => null !== $connection,
			'provider_verified'   => null !== $connection && $connection->is_verified(),
			'last_verified_at'    => null === $connection ? null : $connection->last_verified_at(),
			'default_audience'    => null !== $connection && '' !== $connection->audience_id(),
			'published_templates' => (int) ( $templates->publish ?? 0 ),
			'campaign_states'     => $counts,
			'campaign_total'      => array_sum( $counts ),
			'unknown_campaigns'   => $counts[ \CampaignBridge\Domain\Campaign\Campaign_State::UNKNOWN ] ?? 0,
			'jobs'                => $this->job_health( $schema ),
		);
	}

	/**
	 * Background job counts and work that should have run but has not.
	 *
	 * A job is overdue once it has waited five ticks past its run time.
	 *
	 * @param bool $schema Whether the schema is current.
	 * @return array{counts: array<string, int>, overdue: int, expired_leases: int, oldest_overdue: string|null, scheduled: bool}
	 */
	private function job_health( bool $schema ): array {
		$jobs    = new \CampaignBridge\Repository\Job_Repository();
		$now     = ( new \CampaignBridge\Workflow\Campaign\System_Clock() )->now();
		$stalled = $schema
			? $jobs->stalled( \CampaignBridge\Workflow\Job\Job_Time::after( $now, -5 * MINUTE_IN_SECONDS ), $now )
			: array(
				'overdue'        => 0,
				'expired_leases' => 0,
				'oldest_overdue' => null,
			);

		return array(
			'counts'    => $schema ? $jobs->counts_by_state() : array(),
			'scheduled' => false !== wp_next_scheduled( \CampaignBridge\Cron\Job_Dispatcher::HOOK ),
		) + $stalled;
	}

	/**
	 * Load credential encryption status.
	 *
	 * Reports only the key source and whether the stored credential uses the
	 * current key; never key IDs, key material, or ciphertext.
	 *
	 * @return void
	 */
	private function load_encryption_info(): void {
		$keyring    = \CampaignBridge\Core\Encryption_Keyring::configured();
		$connection = ( new \CampaignBridge\Repository\Provider_Connection_Repository() )->get( 'mailchimp' );

		$this->data['encryption'] = array(
			'source'     => $keyring->is_valid() ? $keyring->source() : 'invalid',
			'credential' => null === $connection ? 'none' : \CampaignBridge\Core\Encryption::key_status( $connection->api_key(), $keyring ),
		);
	}
}
