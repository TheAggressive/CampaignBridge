<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Post Types Controller
 *
 * Auto-discovered and attached to post-types.php screen by naming convention:
 * - post-types.php file → Post_Types_Controller class
 *
 * @package CampaignBridge\Admin\Controllers
 */

namespace CampaignBridge\Admin\Controllers;

/**
 * Post Types Controller class.
 *
 * Auto-discovered and attached to post-types.php screen by naming convention.
 *
 * @package CampaignBridge\Admin\Controllers
 */
class Post_Types_Controller {

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
		// Initialize - load data needed by post types screen.
		$this->load_post_types_data();
		$this->load_enabled_types();
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
	 * Load available post types data
	 *
	 * @return void
	 */
	private function load_post_types_data(): void {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );

		$this->data['post_types'] = [];

		foreach ( $post_types as $post_type ) {
			// Only load the label since that's all the template uses
			$this->data['post_types'][ $post_type->name ] = [
				'label' => $post_type->label ?: $post_type->name,
			];
		}
	}

	/**
	 * Load enabled post types from settings
	 *
	 * @return void
	 */
	private function load_enabled_types(): void {
		$settings = get_option( 'campaignbridge_post_types', [] );
		$enabled_types = $settings['included_post_types'] ?? [];

		// Ensure enabled types is an array
		if ( ! is_array( $enabled_types ) ) {
			$enabled_types = [];
		}

		$this->data['enabled_types'] = $enabled_types;
	}
}
