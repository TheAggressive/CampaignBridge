<?php
/**
 * Form Field Manager - Manages form fields collection and configuration
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Admin\Core\Form_Builder;

/**
 * Form Field Manager - Handles field collection management
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Field_Manager {

	/**
	 * Form configuration instance
	 *
	 * @var Form_Config
	 */
	private Form_Config $config;

	/**
	 * Form builder instance
	 *
	 * @var Form_Builder
	 */
	private Form_Builder $builder;

	/**
	 * Constructor
	 *
	 * @param Form_Config  $config  Form configuration.
	 * @param Form_Builder $builder Form builder.
	 */
	public function __construct( Form_Config $config, Form_Builder $builder ) {
		$this->config  = $config;
		$this->builder = $builder;
	}

	/**
	 * Add a field to the form
	 *
	 * @param string               $name   Field name.
	 * @param string               $type   Field type.
	 * @param string               $label  Field label.
	 * @param array<string, mixed> $config Additional field configuration.
	 * @return Form_Field_Builder
	 */
	public function add_field( string $name, string $type, string $label = '', array $config = array() ): Form_Field_Builder {
		// Merge type into config.
		$config['type'] = $type;

		// Set label if provided.
		if ( $label ) {
			$config['label'] = $label;
		}

		$this->config->add_field( $name, $config );

		// For custom layouts, also add to render sequence.
		if ( $this->config->get( 'layout' ) === 'custom' ) {
			$render_sequence   = $this->config->get( 'render_sequence', array() );
			$render_sequence[] = array(
				'type' => 'field',
				'name' => $name,
			);
			$this->config->set( 'render_sequence', $render_sequence );
		}

		return new Form_Field_Builder( $this->builder, $name );
	}

	/**
	 * Remove a field from the form
	 *
	 * @param string $name Field name.
	 * @return bool True if field was removed, false if not found.
	 */
	public function remove_field( string $name ): bool {
		if ( ! $this->has_field( $name ) ) {
			return false;
		}

		$this->config->remove_field( $name );

		// Also remove from render sequence if it exists.
		$render_sequence = $this->config->get( 'render_sequence', array() );
		$render_sequence = array_filter(
			$render_sequence,
			function ( $item ) use ( $name ) {
				return ! ( isset( $item['type'] ) && 'field' === $item['type'] && isset( $item['name'] ) && $name === $item['name'] );
			}
		);
		$this->config->set( 'render_sequence', array_values( $render_sequence ) );

		return true;
	}

	/**
	 * Check if a field exists
	 *
	 * @param string $name Field name.
	 * @return bool True if field exists.
	 */
	public function has_field( string $name ): bool {
		return $this->config->has( $name );
	}

	/**
	 * Get a field's configuration
	 *
	 * @param string $name Field name.
	 * @return array<string, mixed>|null Field configuration or null if not found.
	 */
	public function get_field( string $name ): ?array {
		return $this->config->get_field( $name );
	}

	/**
	 * Get all fields
	 *
	 * @return array<string, mixed> All fields configuration.
	 */
	public function get_fields(): array {
		return $this->config->get_fields();
	}

	/**
	 * Update a field's configuration
	 *
	 * @param string               $name   Field name.
	 * @param array<string, mixed> $config New field configuration.
	 * @return bool True if field was updated, false if not found.
	 */
	public function update_field( string $name, array $config ): bool {
		if ( ! $this->has_field( $name ) ) {
			return false;
		}

		$this->config->update_field( $name, $config );
		return true;
	}

	/**
	 * Get fields by type
	 *
	 * @param string $type Field type.
	 * @return array<string, mixed> Fields of the specified type.
	 */
	public function get_fields_by_type( string $type ): array {
		$fields = $this->get_fields();
		return array_filter(
			$fields,
			function ( $config ) use ( $type ) {
				return isset( $config['type'] ) && $config['type'] === $type;
			}
		);
	}

	/**
	 * Get required fields
	 *
	 * @return array<string, mixed> Required fields.
	 */
	public function get_required_fields(): array {
		$fields = $this->get_fields();
		return array_filter(
			$fields,
			function ( $config ) {
				return isset( $config['required'] ) && $config['required'];
			}
		);
	}

	/**
	 * Get fields with validation rules
	 *
	 * @return array<string, mixed> Fields with validation rules.
	 */
	public function get_validated_fields(): array {
		$fields = $this->get_fields();
		return array_filter(
			$fields,
			function ( $config ) {
				return isset( $config['validation'] ) && ! empty( $config['validation'] );
			}
		);
	}

	/**
	 * Get field names
	 *
	 * @return array<string> List of field names.
	 */
	public function get_field_names(): array {
		return array_keys( $this->get_fields() );
	}

	/**
	 * Count total fields
	 *
	 * @return int Number of fields.
	 */
	public function count_fields(): int {
		return count( $this->get_fields() );
	}

	/**
	 * Clear all fields
	 *
	 * @return self
	 */
	public function clear_fields(): self {
		$fields = $this->get_fields();
		foreach ( array_keys( $fields ) as $name ) {
			$this->remove_field( $name );
		}
		return $this;
	}

	/**
	 * Import fields from array
	 *
	 * @param array<string, mixed> $fields Fields configuration array.
	 * @return self
	 */
	public function import_fields( array $fields ): self {
		foreach ( $fields as $name => $config ) {
			$this->config->add_field( $name, $config );
		}
		return $this;
	}

	/**
	 * Export fields to array
	 *
	 * @return array<string, mixed> Fields configuration array.
	 */
	public function export_fields(): array {
		return $this->get_fields();
	}

	/**
	 * Validate field configuration
	 *
	 * @param string               $name   Field name.
	 * @param array<string, mixed> $config Field configuration.
	 * @return bool True if valid.
	 */
	public function validate_field_config( string $name, array $config ): bool {
		// Check required fields.
		if ( empty( $name ) ) {
			return false;
		}

		// Check type is valid.
		if ( ! isset( $config['type'] ) || ! is_string( $config['type'] ) ) {
			return false;
		}

		// Check label if provided.
		if ( isset( $config['label'] ) && ! is_string( $config['label'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Create a field builder for chaining
	 *
	 * @param string $name  Field name.
	 * @param string $type  Field type.
	 * @param string $label Field label.
	 * @return Form_Field_Builder
	 */
	public function create_field_builder( string $name, string $type, string $label = '' ): Form_Field_Builder {
		return $this->add_field( $name, $type, $label );
	}
}
