<?php
/**
 * Form Data Manager - Handles form data loading and management
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Admin\Core\Form;

/**
 * Form Data Manager - Handles form data loading and management
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Data_Manager {

	/**
	 * Parent form instance
	 *
	 * @var Form
	 */
	private Form $form;

	/**
	 * Form configuration
	 *
	 * @var array
	 */
	private array $config;

	/**
	 * Form fields configuration
	 *
	 * @var array
	 */
	private array $fields;

	/**
	 * Form data storage
	 *
	 * @var array
	 */
	private array $data = [];

	/**
	 * Constructor
	 *
	 * @param Form  $form   Parent form instance.
	 * @param array $config Form configuration.
	 * @param array $fields Form fields.
	 */
	public function __construct( Form $form, array $config, array $fields ) {
		$this->form   = $form;
		$this->config = $config;
		$this->fields = $fields;
	}

	/**
	 * Load form data for editing
	 */
	public function load_form_data(): void {
		// Load from options, post meta, or custom source
		$data_source = $this->config['data_source'] ?? 'options';

		switch ( $data_source ) {
			case 'options':
				$this->load_from_options();
				break;
			case 'post_meta':
				$this->load_from_post_meta();
				break;
			default:
				$this->load_custom_data();
				break;
		}
	}

	/**
	 * Load data from WordPress options
	 */
	private function load_from_options(): void {
		foreach ( $this->fields as $field_id => $field_config ) {
			$option_key              = $this->config['prefix'] . $field_id . $this->config['suffix'];
			$this->data[ $field_id ] = \get_option( $option_key, $field_config['default'] ?? '' );
		}
	}

	/**
	 * Load data from post meta
	 */
	private function load_from_post_meta(): void {
		$post_id = $this->config['post_id'] ?? \get_the_ID();

		foreach ( $this->fields as $field_id => $field_config ) {
			$this->data[ $field_id ] = \get_post_meta( $post_id, $field_id, true );
		}
	}

	/**
	 * Load data from custom source
	 */
	private function load_custom_data(): void {
		if ( isset( $this->config['hooks']['load_data'] ) && is_callable( $this->config['hooks']['load_data'] ) ) {
			$this->data = call_user_func( $this->config['hooks']['load_data'], $this->fields );
		}
	}

	/**
	 * Get form data
	 *
	 * @param string $key Optional field key.
	 * @return mixed
	 */
	public function get_data( string $key = '' ) {
		if ( $key ) {
			return $this->data[ $key ] ?? null;
		}
		return $this->data;
	}

	/**
	 * Set form data
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Field value.
	 */
	public function set_data( string $key, $value ): void {
		$this->data[ $key ] = $value;
	}

	/**
	 * Update form data from array
	 *
	 * @param array $data Data to update.
	 */
	public function update_data( array $data ): void {
		$this->data = array_merge( $this->data, $data );
	}

	/**
	 * Clear form data
	 */
	public function clear_data(): void {
		$this->data = [];
	}

	/**
	 * Check if field has data
	 *
	 * @param string $key Field key.
	 * @return bool
	 */
	public function has_data( string $key ): bool {
		return isset( $this->data[ $key ] ) && ! empty( $this->data[ $key ] );
	}

	/**
	 * Get field value with fallback to default
	 *
	 * @param string $key Field key.
	 * @return mixed
	 */
	public function get_field_value( string $key ) {
		if ( isset( $this->data[ $key ] ) ) {
			return $this->data[ $key ];
		}

		return $this->fields[ $key ]['default'] ?? '';
	}
}
