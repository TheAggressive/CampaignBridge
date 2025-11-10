<?php
/**
 * Form Configuration Manager - Manages form configuration state
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Configuration Manager
 *
 * Manages form configuration state and provides methods to get/set configuration values.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Config {

	/**
	 * Form configuration data
	 *
	 * @var array<string, mixed>
	 */
	private array $config = array();

	/**
	 * Default configuration values
	 *
	 * @var array<string, mixed>
	 */
	private array $defaults = array(
		'method'          => 'POST',
		'action'          => '',
		'enctype'         => 'application/x-www-form-urlencoded',
		'classes'         => array( 'campaignbridge-form' ),
		'attributes'      => array(),
		'fields'          => array(),
		'hooks'           => array(),
		'validation'      => array(),
		'submit_button'   => array(
			'text' => 'Save Changes',
			'type' => 'primary',
		),
		'layout'          => 'table', // table, div, custom.
		'description'     => '',
		'prefix'          => '', // Field name prefix.
		'suffix'          => '', // Field name suffix.
		'data_source'     => 'options', // options, post_meta, custom.
		'post_id'         => 0, // For post_meta data source.
		'save_method'     => 'options', // options, post_meta, custom.
		'success_message' => 'Saved successfully!',
		'error_message'   => 'Error occurred.',
	);

	/**
	 * Constructor
	 *
	 * @param array<string, mixed> $config Initial configuration array.
	 */
	public function __construct( array $config = array() ) {
		$this->config = $this->normalize_config( $config );
	}

	/**
	 * Get a configuration value
	 *
	 * @param string $key      Configuration key.
	 * @param mixed  $fallback Fallback value if key doesn't exist.
	 * @return mixed Configuration value or fallback.
	 */
	public function get( string $key, $fallback = null ) {
		return $this->config[ $key ] ?? $fallback;
	}

	/**
	 * Set a configuration value
	 *
	 * @param string $key   Configuration key.
	 * @param mixed  $value Configuration value.
	 * @return self
	 */
	public function set( string $key, $value ): self {
		$this->config[ $key ] = $value;
		return $this;
	}

	/**
	 * Check if a configuration key exists
	 *
	 * @param string $key Configuration key.
	 * @return bool True if key exists.
	 */
	public function has( string $key ): bool {
		return isset( $this->config[ $key ] );
	}

	/**
	 * Get all configuration
	 *
	 * @return array<string, mixed> All configuration data.
	 */
	public function all(): array {
		return $this->config;
	}

	/**
	 * Merge configuration with existing config
	 *
	 * @param array<string, mixed> $config Configuration to merge.
	 * @return self
	 */
	public function merge( array $config ): self {
		$this->config = array_replace_recursive( $this->config, $config );
		return $this;
	}

	/**
	 * Reset configuration to defaults
	 *
	 * @return self
	 */
	public function reset(): self {
		$this->config = $this->defaults;
		return $this;
	}

	/**
	 * Set form submission method
	 *
	 * @param string $method HTTP method (GET, POST, etc.).
	 * @return self
	 */
	public function set_method( string $method ): self {
		return $this->set( 'method', strtoupper( $method ) );
	}

	/**
	 * Set form action
	 *
	 * @param string $action Form action URL.
	 * @return self
	 */
	public function set_action( string $action ): self {
		return $this->set( 'action', $action );
	}

	/**
	 * Set data source
	 *
	 * @param string $source options, post_meta, custom.
	 * @return self
	 */
	public function set_source( string $source ): self {
		return $this->set( 'data_source', $source );
	}

	/**
	 * Set save method
	 *
	 * @param string $method options, post_meta, custom.
	 * @return self
	 */
	public function set_save_method( string $method ): self {
		return $this->set( 'save_method', $method );
	}

	/**
	 * Set form layout
	 *
	 * @param string $layout Layout type (table, div, custom).
	 * @return self
	 */
	public function set_layout( string $layout ): self {
		return $this->set( 'layout', $layout );
	}

	/**
	 * Set a prefix for form field names and IDs
	 *
	 * @param string $prefix Prefix string.
	 * @return self
	 */
	public function set_prefix( string $prefix ): self {
		return $this->set( 'prefix', $prefix );
	}

	/**
	 * Set suffix
	 *
	 * @param string $suffix Field name suffix.
	 * @return self
	 */
	public function set_suffix( string $suffix ): self {
		return $this->set( 'suffix', $suffix );
	}

	/**
	 * Set post ID for post_meta operations
	 *
	 * @param int $post_id Post ID.
	 * @return self
	 */
	public function set_post_id( int $post_id ): self {
		return $this->set( 'post_id', $post_id );
	}

	/**
	 * Set success message
	 *
	 * @param string $message Success message.
	 * @return self
	 */
	public function set_success_message( string $message ): self {
		return $this->set( 'success_message', $message );
	}

	/**
	 * Set error message
	 *
	 * @param string $message Error message.
	 * @return self
	 */
	public function set_error_message( string $message ): self {
		return $this->set( 'error_message', $message );
	}

	/**
	 * Set form description
	 *
	 * @param string $description Form description.
	 * @return self
	 */
	public function set_description( string $description ): self {
		return $this->set( 'description', $description );
	}

	/**
	 * Set submit button configuration
	 *
	 * @param string $text Button text.
	 * @param string $type Button type.
	 * @return self
	 */
	public function set_submit_button( string $text = 'Save', string $type = 'primary' ): self {
		return $this->set(
			'submit_button',
			array(
				'text' => $text,
				'type' => $type,
			)
		);
	}

	/**
	 * Add a form field
	 *
	 * @param string               $name   Field name.
	 * @param array<string, mixed> $config Field configuration.
	 * @return self
	 */
	public function add_field( string $name, array $config ): self {
		$fields          = $this->get( 'fields', array() );
		$fields[ $name ] = $config;
		return $this->set( 'fields', $fields );
	}

	/**
	 * Add a heading element
	 *
	 * @param string $level Heading level (h1, h2, h3, etc.).
	 * @param string $text  Heading text.
	 * @return self
	 */
	public function add_heading( string $level, string $text ): self {
		$headings   = $this->get( 'headings', array() );
		$headings[] = array(
			'level' => $level,
			'text'  => $text,
		);
		return $this->set( 'headings', $headings );
	}

	/**
	 * Get all headings
	 *
	 * @return array<array{level: string, text: string}> All headings.
	 */
	public function get_headings(): array {
		return $this->get( 'headings', array() );
	}

	/**
	 * Get a field configuration
	 *
	 * @param string $name Field name.
	 * @return array<string, mixed>|null Field configuration or null if not found.
	 */
	public function get_field( string $name ): ?array {
		$fields = $this->get( 'fields', array() );
		return $fields[ $name ] ?? null;
	}

	/**
	 * Update a field configuration
	 *
	 * @param string               $name   Field name.
	 * @param array<string, mixed> $config Field configuration updates.
	 * @return self
	 */
	public function update_field( string $name, array $config ): self {
		$fields = $this->get( 'fields', array() );
		if ( isset( $fields[ $name ] ) ) {
			$fields[ $name ] = array_merge( $fields[ $name ], $config );
			$this->set( 'fields', $fields );
		}
		return $this;
	}

	/**
	 * Remove a field
	 *
	 * @param string $name Field name.
	 * @return self
	 */
	public function remove_field( string $name ): self {
		$fields = $this->get( 'fields', array() );
		unset( $fields[ $name ] );
		return $this->set( 'fields', $fields );
	}

	/**
	 * Get all fields
	 *
	 * @return array<string, mixed> All fields configuration.
	 */
	public function get_fields(): array {
		return $this->get( 'fields', array() );
	}

	/**
	 * Add a hook
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Hook callback.
	 * @return self
	 */
	public function add_hook( string $hook, callable $callback ): self {
		$hooks          = $this->get( 'hooks', array() );
		$hooks[ $hook ] = $callback;
		return $this->set( 'hooks', $hooks );
	}

	/**
	 * Get a hook
	 *
	 * @param string $hook Hook name.
	 * @return callable|null Hook callback or null if not found.
	 */
	public function get_hook( string $hook ): ?callable {
		$hooks = $this->get( 'hooks', array() );
		return $hooks[ $hook ] ?? null;
	}

	/**
	 * Get all hooks
	 *
	 * @return array<string, mixed> All hooks.
	 */
	public function get_hooks(): array {
		return $this->get( 'hooks', array() );
	}

	/**
	 * Add classes to the form
	 *
	 * @param string|array<string> $classes CSS classes to add.
	 * @return self
	 */
	public function add_classes( $classes ): self {
		$current_classes = $this->get( 'classes', array() );
		$new_classes     = is_array( $classes ) ? $classes : explode( ' ', $classes );
		$all_classes     = array_unique( array_merge( $current_classes, $new_classes ) );
		return $this->set( 'classes', $all_classes );
	}

	/**
	 * Set enctype for file uploads
	 *
	 * @return self
	 */
	public function set_multipart_encoding(): self {
		return $this->set( 'enctype', 'multipart/form-data' );
	}

	/**
	 * Auto-detect if multipart encoding is needed based on field types
	 *
	 * @return self
	 */
	public function auto_detect_multipart_encoding(): self {
		$has_file_fields = false;
		$fields          = $this->get( 'fields', array() );

		foreach ( $fields as $field_config ) {
			if ( isset( $field_config['type'] ) && 'file' === $field_config['type'] ) {
				$has_file_fields = true;
				break;
			}
		}

		if ( $has_file_fields ) {
			$this->set_multipart_encoding();
		}

		return $this;
	}

	/**
	 * Normalize configuration array
	 *
	 * @param array<string, mixed> $config Raw configuration.
	 * @return array<string, mixed> Normalized configuration.
	 */
	private function normalize_config( array $config ): array {
		return \wp_parse_args( $config, $this->defaults );
	}
}
