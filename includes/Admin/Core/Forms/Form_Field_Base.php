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
	 * Constructor
	 *
	 * @param array<string, mixed> $config Field configuration.
	 */
	public function __construct( array $config ) {
		$this->config = $config;
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
	 * Check if field has validation errors
	 *
	 * @return bool
	 */
	public function has_errors(): bool {
		$errors = $this->config['errors'] ?? array();
		return ! empty( $errors );
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

		// Check required.
		if ( $this->is_required() && empty( $value ) ) {
			return new \WP_Error(
				'field_required',
				sprintf(
					/* translators: %s: field label */
					__( '%s is required.', 'campaignbridge' ),
					$this->config['label']
				)
			);
		}

		// Apply custom validation rules.
		foreach ( $rules as $rule => $rule_config ) {
			$validation_result = $this->validate_rule( $rule, $value, $rule_config );

			if ( is_wp_error( $validation_result ) ) {
				return $validation_result;
			}
		}

		return true;
	}

	/**
	 * Validate a specific rule
	 *
	 * @param string $rule        Rule name.
	 * @param mixed  $value       Value to validate.
	 * @param mixed  $rule_config Rule configuration.
	 * @return bool|\WP_Error
	 */
	protected function validate_rule( string $rule, $value, $rule_config ) {
		switch ( $rule ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					return new \WP_Error(
						'invalid_email',
						__( 'Please enter a valid email address.', 'campaignbridge' )
					);
				}
				break;

			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return new \WP_Error(
						'invalid_url',
						__( 'Please enter a valid URL.', 'campaignbridge' )
					);
				}
				break;

			case 'min_length':
				if ( strlen( $value ) < $rule_config ) {
					return new \WP_Error(
						'min_length',
						sprintf(
							/* translators: %d: minimum length */
							__( 'Minimum length is %d characters.', 'campaignbridge' ),
							$rule_config
						)
					);
				}
				break;

			case 'max_length':
				if ( strlen( $value ) > $rule_config ) {
					return new \WP_Error(
						'max_length',
						sprintf(
							/* translators: %d: maximum length */
							__( 'Maximum length is %d characters.', 'campaignbridge' ),
							$rule_config
						)
					);
				}
				break;

			case 'pattern':
				if ( ! preg_match( $rule_config, $value ) ) {
					return new \WP_Error(
						'invalid_pattern',
						__( 'Value does not match required format.', 'campaignbridge' )
					);
				}
				break;

			case 'numeric':
				if ( ! is_numeric( $value ) ) {
					return new \WP_Error(
						'not_numeric',
						__( 'Please enter a valid number.', 'campaignbridge' )
					);
				}
				break;

			case 'min':
				if ( is_numeric( $value ) && $value < $rule_config ) {
					return new \WP_Error(
						'value_too_low',
						sprintf(
							/* translators: %s: minimum value */
							__( 'Value must be at least %s.', 'campaignbridge' ),
							$rule_config
						)
					);
				}
				break;

			case 'max':
				if ( is_numeric( $value ) && $value > $rule_config ) {
					return new \WP_Error(
						'value_too_high',
						sprintf(
							/* translators: %s: maximum value */
							__( 'Value must be no more than %s.', 'campaignbridge' ),
							$rule_config
						)
					);
				}
				break;
		}

		return true;
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

		// Add error state attributes if field has errors.
		if ( $this->has_errors() ) {
			$attributes[] = 'aria-invalid="true"';
			$error_id     = ( $this->config['id'] ?? '' ) . '_error';
			$attributes[] = sprintf( 'aria-errormessage="%s"', esc_attr( $error_id ) );
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

		printf( '<tr%s>', $wrapper_class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

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
			$for_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $label ),
			$required_mark // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Render field error messages
	 */
	protected function render_errors(): void {
		if ( ! $this->has_errors() ) {
			return;
		}

		$errors   = $this->config['errors'] ?? array();
		$error_id = ( $this->config['id'] ?? '' ) . '_error';

		echo '<div class="field-errors" id="' . esc_attr( $error_id ) . '" role="alert" aria-live="polite">';
		foreach ( $errors as $error ) {
			echo '<span class="field-error">' . esc_html( $error ) . '</span>';
		}
		echo '</div>';
	}

	/**
	 * Render the input element (to be implemented by child classes)
	 */
	abstract public function render_input(): void;
}
