<?php
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
	 * @var array<string, mixed>
	 */
	private array $data = array();

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
	 * @return array<string, mixed>
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
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		$this->data['post_types'] = array();

		foreach ( $post_types as $post_type ) {
			// Only load the label since that's all the template uses.
			$this->data['post_types'][ $post_type->name ] = array(
				'label' => $post_type->label ? $post_type->label : $post_type->name,
			);
		}
	}

	/**
	 * Load enabled post types from settings
	 *
	 * @return void
	 */
	private function load_enabled_types(): void {
		// Load from separate option (better performance than nested arrays).
		$enabled_types = \CampaignBridge\Core\Storage::get_option( 'campaignbridge_included_post_types', array() );

		// Ensure enabled types is an array.
		if ( ! is_array( $enabled_types ) ) {
			$enabled_types = array();
		}

		$this->data['enabled_types'] = $enabled_types;
	}
}
