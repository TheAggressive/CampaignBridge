<?php // phpcs:ignoreFile WordPress.Files.FileName
/**
 * Abstract Settings Tab Base Class for CampaignBridge Admin Interface.
 *
 * Abstract base class for settings tab implementations providing
 * consistent interface and standardized patterns for creating admin
 * settings tabs with proper validation and rendering.
 *
 * @package CampaignBridge\Admin\Pages
 * @since 0.1.0
 */

declare(strict_types=1);

namespace CampaignBridge\Admin\Pages\Tabs;

use CampaignBridge\Admin\Pages\Tabs\Helpers\Tab_Field_Validator;
use CampaignBridge\Admin\Pages\Tabs\Helpers\Tab_Field_Sanitizer;
use CampaignBridge\Admin\Pages\Tabs\Helpers\Tab_Field_Renderer;
use CampaignBridge\Admin\Pages\Tabs\Helpers\Tab_Utilities;
use CampaignBridge\Admin\Pages\Settings_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for settings tabs.
 *
 * Provides common functionality and standardized patterns for settings tabs
 * using helper classes for validation, sanitization, and rendering.
 */
abstract class Abstract_Settings_Tab {
	/**
	 * Get the tab slug (used as identifier and URL parameter).
	 *
	 * @since 0.1.0
	 * @return string The tab slug.
	 */
	abstract public static function get_slug(): string;

	/**
	 * Get the tab label (display name).
	 *
	 * @since 0.1.0
	 * @return string The tab label.
	 */
	abstract public static function get_label(): string;

	/**
	 * Get the tab description.
	 *
	 * @since 0.1.0
	 * @return string The tab description.
	 */
	abstract public static function get_description(): string;

	/**
	 * Register settings sections and fields for this tab.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	abstract public static function register_settings(): void;

	/**
	 * Render the tab content.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	abstract public static function render(): void;

	/**
	 * Get the fields that belong to this tab.
	 *
	 * @since 0.1.0
	 * @return array Array of field names.
	 */
	abstract public static function get_tab_fields(): array;

	/**
	 * Validate settings for this tab.
	 *
	 * @since 0.1.0
	 * @param array $settings Settings to validate.
	 * @return array Validation result with 'valid' boolean and 'errors' array.
	 */
	public static function validate_settings( array $settings ): array {
		$errors = array();

		foreach ( static::get_tab_fields() as $field_name ) {
			$field_config = static::get_field_config( $field_name );
			$value = $settings[ $field_name ] ?? '';

			$validation = Tab_Field_Validator::validate_field( $field_name, $value, $field_config );

			if ( ! $validation['valid'] ) {
				$errors = array_merge( $errors, $validation['errors'] );
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Get field configuration for validation rules.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @return array Field configuration array.
	 */
	abstract protected static function get_field_config( string $field_name ): array;

	/**
	 * Sanitize settings for this tab.
	 *
	 * @since 0.1.0
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( array $settings ): array {
		$field_types = array();
		$tab_fields = static::get_tab_fields();

		foreach ( $tab_fields as $field_name ) {
			$config = static::get_field_config( $field_name );
			$field_types[ $field_name ] = $config['type'] ?? 'text';
		}

		return Tab_Field_Sanitizer::sanitize_settings( $settings, $field_types );
	}

	/**
	 * Render a field group with label and description.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @param array  $attributes Additional attributes.
	 * @return void
	 */
	protected static function render_field_group( string $field_name, array $attributes = array() ): void {
		$config = static::get_field_config( $field_name );
		$value = $attributes['value'] ?? '';
		$has_error = $attributes['has_error'] ?? false;

		$attributes = array_merge( $attributes, array(
			'label'       => $config['label'] ?? ucfirst( str_replace( '_', ' ', $field_name ) ),
			'description' => $config['description'] ?? '',
			'required'    => $config['required'] ?? false,
			'value'       => $value,
			'has_error'   => $has_error,
		) );

		if ( isset( $config['options'] ) ) {
			$attributes['options'] = $config['options'];
		}

		Tab_Field_Renderer::render_field_group( $field_name, $config['type'] ?? 'text', $attributes );
	}

	/**
	 * Display field errors.
	 *
	 * @since 0.1.0
	 * @param string $field_name Field name.
	 * @return void
	 */
	protected static function display_field_errors( string $field_name ): void {
		Tab_Utilities::display_field_errors( $field_name );
	}

	/**
	 * Check if current tab is active.
	 *
	 * @since 0.1.0
	 * @return bool True if current tab is active.
	 */
	public static function is_active(): bool {
		return Tab_Utilities::is_tab_active( static::get_slug() );
	}

	/**
	 * Get URL for current tab.
	 *
	 * @since 0.1.0
	 * @return string Tab URL.
	 */
	public static function get_url(): string {
		return Tab_Utilities::get_tab_url( static::get_slug() );
	}
}
