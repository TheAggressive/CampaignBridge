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
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * Form fields configuration
	 *
	 * @var array<string, mixed>
	 */
	private array $fields;

	/**
	 * Form data storage
	 *
	 * @var array<string, mixed>
	 */
	private array $data = array();

	/**
	 * Constructor
	 *
	 * @param Form                 $form   Parent form instance.
	 * @param array<string, mixed> $config Form configuration.
	 * @param array<string, mixed> $fields Form fields.
	 */
	public function __construct( Form $form, array $config, array $fields ) {
		$this->form   = $form;
		$this->config = $config;
		$this->fields = $fields;
	}

	/**
	 * Get the parent form instance
	 *
	 * @return Form Parent form instance.
	 */
	public function get_form(): Form {
		return $this->form;
	}

	/**
	 * Load form data for editing
	 */
	public function load_form_data(): void {
		// Load from options, post meta, or custom source.
		$data_source = $this->config['data_source'] ?? 'options';

		switch ( $data_source ) {
			case 'options':
				$this->load_from_options();
				break;
			case 'post_meta':
				$this->load_from_post_meta();
				break;
			case 'settings':
				$this->load_from_settings();
				break;
			default:
				$this->load_custom_data();
				break;
		}
	}

	/**
	 * Reload form data (clears cache and reloads from source)
	 */
	public function reload(): void {
		// Clear existing data.
		$this->data = array();

		// Reload from source.
		$this->load_form_data();
	}

	/**
	 * Load data from WordPress options
	 */
	private function load_from_options(): void {
		// Track which base names we've already loaded to avoid duplicate queries.
		$loaded_base_names = array();

		foreach ( $this->fields as $field_id => $field_config ) {
			// Handle repeater fields with ___ separator.
			if ( strpos( $field_id, '___' ) !== false ) {
				list( $base_name, $key ) = explode( '___', $field_id, 2 );

				// Only load the base option once for all repeater fields.
				if ( ! isset( $loaded_base_names[ $base_name ] ) ) {
					$option_key                      = $this->config['prefix'] . $base_name . $this->config['suffix'];
					$loaded_base_names[ $base_name ] = \CampaignBridge\Core\Storage::get_option( $option_key, array() );
				}

				// Check if this specific key is in the saved array.
				$saved_array             = $loaded_base_names[ $base_name ];
				$this->data[ $field_id ] = is_array( $saved_array ) && in_array( $key, $saved_array, true );
			} else {
				// Regular field - load normally.
				$option_key  = $this->config['prefix'] . $field_id . $this->config['suffix'];
				$saved_value = \CampaignBridge\Core\Storage::get_option( $option_key, null );

				$this->data[ $field_id ] = $saved_value ?? $field_config['default'] ?? '';
			}
		}
	}

	/**
	 * Load data from WordPress Settings API
	 */
	private function load_from_settings(): void {
		$settings_group = $this->config['settings_group'] ?? $this->config['form_id'] ?? 'settings';
		$settings_data  = \CampaignBridge\Core\Storage::get_option( $settings_group, array() );

		// Load individual field values from the settings array.
		foreach ( $this->fields as $field_id => $field_config ) {
			$this->data[ $field_id ] = $settings_data[ $field_id ] ?? $field_config['default'] ?? '';
		}
	}

	/**
	 * Load data from post meta
	 */
	private function load_from_post_meta(): void {
		$post_id = $this->config['post_id'] ?? \get_the_ID();

		foreach ( $this->fields as $field_id => $field_config ) {
			$this->data[ $field_id ] = \CampaignBridge\Core\Storage::get_post_meta( $post_id, $field_id, true );
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
	 * @param array<string, mixed> $data Data to update.
	 */
	public function update_data( array $data ): void {
		$this->data = array_merge( $this->data, $data );
	}

	/**
	 * Clear form data
	 */
	public function clear_data(): void {
		$this->data = array();
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
