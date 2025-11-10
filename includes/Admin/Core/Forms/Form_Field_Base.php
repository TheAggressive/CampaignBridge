<?php
/**
 * Form Field Base Class
 *
 * Base implementation for all form field types.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Field Base Class
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
abstract class Form_Field_Base implements Form_Field_Interface {

	/**
	 * Field configuration
	 *
	 * @var array<string, mixed>
	 */
	protected array $config;

	/**
	 * Form validator instance
	 *
	 * @var Form_Validator
	 */
	protected Form_Validator $validator;

	/**
	 * Constructor
	 *
	 * @param array<string, mixed> $config    Field configuration.
	 * @param Form_Validator       $validator Form validator instance.
	 */
	public function __construct( array $config, Form_Validator $validator ) {
		$this->config    = $config;
		$this->validator = $validator;
	}

	/**
	 * Get field configuration
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return $this->config;
	}

	/**
	 * Get field value
	 *
	 * @return mixed
	 */
	public function get_value() {
		return $this->config['value'] ?? $this->config['default'];
	}

	/**
	 * Set field value
	 *
	 * @param mixed $value Field value.
	 */
	public function set_value( $value ): void {
		$this->config['value'] = $value;
	}

	/**
	 * Check if field is required
	 *
	 * @return bool
	 */
	public function is_required(): bool {
		return (bool) ( $this->config['required'] ?? false );
	}


	/**
	 * Get field validation rules
	 *
	 * @return array<string, mixed>
	 */
	public function get_validation_rules(): array {
		return $this->config['validation'] ?? array();
	}

	/**
	 * Validate field value
	 *
	 * @param mixed $value Value to validate.
	 * @return bool|\WP_Error True if valid, \WP_Error if invalid.
	 */
	public function validate( $value ) {
		$rules = $this->get_validation_rules();
		return $this->validator->validate( $value, $rules, $this->config['label'] ?? '' );
	}

	/**
	 * Render common field attributes
	 *
	 * @return string
	 */
	protected function render_common_attributes(): string {
		$attributes = array();

		// Basic attributes.
		$attributes[] = sprintf( 'id="%s"', esc_attr( $this->config['id'] ?? '' ) );
		$attributes[] = sprintf( 'name="%s"', esc_attr( $this->config['name'] ?? '' ) );

		if ( ! empty( $this->config['class'] ) ) {
			$attributes[] = sprintf( 'class="%s"', esc_attr( $this->config['class'] ) );
		}

		if ( ! empty( $this->config['placeholder'] ) ) {
			$attributes[] = sprintf( 'placeholder="%s"', esc_attr( $this->config['placeholder'] ) );
		}

		if ( $this->is_required() ) {
			$attributes[] = 'required';
		}

		if ( ! empty( $this->config['disabled'] ) ) {
			$attributes[] = 'disabled';
		}

		if ( ! empty( $this->config['readonly'] ) ) {
			$attributes[] = 'readonly';
		}

		// HTML5 attributes.
		if ( ! empty( $this->config['min'] ) ) {
			$attributes[] = sprintf( 'min="%s"', esc_attr( $this->config['min'] ) );
		}

		if ( ! empty( $this->config['max'] ) ) {
			$attributes[] = sprintf( 'max="%s"', esc_attr( $this->config['max'] ) );
		}

		if ( ! empty( $this->config['step'] ) ) {
			$attributes[] = sprintf( 'step="%s"', esc_attr( $this->config['step'] ) );
		}

		if ( ! empty( $this->config['pattern'] ) ) {
			$attributes[] = sprintf( 'pattern="%s"', esc_attr( $this->config['pattern'] ) );
		}

		if ( ! empty( $this->config['autocomplete'] ) ) {
			$attributes[] = sprintf( 'autocomplete="%s"', esc_attr( $this->config['autocomplete'] ) );
		}

		// ARIA attributes for accessibility.
		if ( ! empty( $this->config['aria-describedby'] ) ) {
			$attributes[] = sprintf( 'aria-describedby="%s"', esc_attr( $this->config['aria-describedby'] ) );
		}

		if ( $this->is_required() ) {
			$attributes[] = 'aria-required="true"';
		}

		// Validation data attributes for real-time validation.
		$validation_rules = $this->get_validation_rules();
		if ( ! empty( $validation_rules ) ) {
			$js_validation_rules = $this->convert_to_js_validation_rules( $validation_rules );
			$validation_json     = wp_json_encode( $js_validation_rules );
			$attributes[]        = sprintf( 'data-validation="%s"', esc_attr( $validation_json ) );
		}

		// Data attributes.
		foreach ( $this->config as $key => $value ) {
			if ( str_starts_with( $key, 'data-' ) && ! empty( $value ) ) {
				$attributes[] = sprintf( '%s="%s"', esc_attr( $key ), esc_attr( $value ) );
			}
		}

		// Additional custom attributes.
		if ( ! empty( $this->config['attributes'] ) ) {
			foreach ( $this->config['attributes'] as $key => $value ) {
				$attributes[] = sprintf( '%s="%s"', esc_attr( $key ), esc_attr( $value ) );
			}
		}

		return implode( ' ', $attributes );
	}

	/**
	 * Convert PHP validation rules to JavaScript validation format
	 *
	 * @param array<string, mixed> $php_rules PHP validation rules.
	 * @return array<array<string, mixed>> JavaScript validation rules.
	 */
	private function convert_to_js_validation_rules( array $php_rules ): array {
		return $this->validator->convert_to_js_rules( $php_rules, $this->config['label'] ?? '' );
	}

	/**
	 * Render field description
	 */
	protected function render_description(): void {
		if ( ! empty( $this->config['description'] ) ) {
			$describedby_id = ( $this->config['id'] ?? '' ) . '_description';

			printf(
				'<p class="description" id="%s">%s</p>',
				esc_attr( $describedby_id ),
				wp_kses_post( $this->config['description'] )
			);

			// Add aria-describedby if not already set.
			if ( empty( $this->config['aria-describedby'] ) ) {
				$this->config['aria-describedby'] = $describedby_id;
			}
		}
	}

	/**
	 * Render before/after HTML
	 *
	 * @param string $position 'before' or 'after'.
	 */
	protected function render_html( string $position ): void {
		if ( ! empty( $this->config[ $position ] ) ) {
			echo wp_kses_post( $this->config[ $position ] );
		}
	}

	/**
	 * Render the field in table layout
	 */
	public function render_table_row(): void {
		$wrapper_class = ( $this->config['wrapper_class'] ?? '' ) ? ' class="' . esc_attr( $this->config['wrapper_class'] ) . '"' : '';

		printf( '<tr%s>', $wrapper_class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper_class is built by render_table_row() with proper escaping.

		// Label column.
		echo '<th scope="row">';
		$this->render_label();
		echo '</th>';

		// Field column.
		echo '<td>';
		$this->render_html( 'before' );
		$this->render_input();
		$this->render_description();
		$this->render_html( 'after' );
		echo '</td>';

		echo '</tr>';
	}

	/**
	 * Render the field in div layout
	 */
	public function render_div_field(): void {
		$wrapper_class = 'campaignbridge-field-wrapper';
		if ( ! empty( $this->config['wrapper_class'] ) ) {
			$wrapper_class .= ' ' . $this->config['wrapper_class'];
		}

		printf( '<div class="%s">', esc_attr( $wrapper_class ) );

		$this->render_label();
		$this->render_html( 'before' );
		$this->render_input();
		$this->render_description();
		$this->render_html( 'after' );

		echo '</div>';
	}

	/**
	 * Render field label
	 */
	protected function render_label(): void {
		$label = $this->config['label'] ?? '';

		if ( empty( $label ) ) {
			return;
		}

		$required_mark = $this->is_required() ? ' <span class="required">*</span>' : '';
		$for_attr      = sprintf( ' for="%s"', esc_attr( $this->config['id'] ?? '' ) );

		printf(
			'<label%s>%s%s</label>',
			$for_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $for_attr is built by render_label() with proper escaping.
			esc_html( $label ),
			$required_mark // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $required_mark is built by render_label() with proper escaping.
		);
	}


	/**
	 * Render the input element (to be implemented by child classes)
	 */
	abstract public function render_input(): void;
}
