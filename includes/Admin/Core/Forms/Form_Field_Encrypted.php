<?php
/**
 * Form Field Encrypted - Secure input field for encrypted data
 *
 * Provides a masked input field for encrypted data with reveal/update functionality.
 * Shows masked values (••••••••••••abcd) and allows secure editing of encrypted fields.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

use CampaignBridge\Core\Encryption;

/**
 * Form Field Encrypted Class
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Field_Encrypted extends Form_Field_Input {

	/**
	 * Security context for this field
	 *
	 * @var string
	 */
	private string $context = 'sensitive';

	/**
	 * Whether to show reveal button
	 *
	 * @var bool
	 */
	private bool $show_reveal = true;

	/**
	 * Whether to show edit button
	 *
	 * @var bool
	 */
	private bool $show_edit = true;

	/**
	 * Constructor
	 *
	 * @param array<string, mixed> $config Field configuration.
	 */
	public function __construct( array $config ) {
		// Set field type to password for security.
		$config['type'] = 'password';

		// Extract encrypted-specific config.
		$this->context     = $config['context'] ?? 'sensitive';
		$this->show_reveal = $config['show_reveal'] ?? true;
		$this->show_edit   = $config['show_edit'] ?? true;

		parent::__construct( $config );
	}

	/**
	 * Get field attributes as array
	 *
	 * @return array<string, mixed>
	 */
	protected function get_attributes(): array {
		return array(
			'id'          => $this->config['id'] ?? '',
			'name'        => $this->config['name'] ?? '',
			'class'       => $this->config['class'] ?? '',
			'placeholder' => $this->config['placeholder'] ?? '',
			'required'    => $this->is_required(),
			'disabled'    => $this->config['disabled'] ?? false,
			'readonly'    => $this->config['readonly'] ?? false,
		);
	}

	/**
	 * Set the security context
	 *
	 * @param string $context Security context ('api_key', 'sensitive', 'personal', 'public').
	 * @return self
	 */
	public function context( string $context ): self {
		$this->context           = $context;
		$this->config['context'] = $context;
		return $this;
	}

	/**
	 * Show or hide the reveal button
	 *
	 * @param bool $show Whether to show the reveal button.
	 * @return self
	 */
	public function show_reveal( bool $show = true ): self {
		$this->show_reveal           = $show;
		$this->config['show_reveal'] = $show;
		return $this;
	}

	/**
	 * Show or hide the edit button
	 *
	 * @param bool $show Whether to show the edit button.
	 * @return self
	 */
	public function show_edit( bool $show = true ): self {
		$this->show_edit           = $show;
		$this->config['show_edit'] = $show;
		return $this;
	}

	/**
	 * Render the encrypted field
	 *
	 * @return string HTML output.
	 */
	public function render(): string {
		$value     = $this->get_value();
		$is_empty  = empty( $value );
		$is_masked = $this->is_masked_value( $value );

		// If value is empty, show normal input.
		if ( $is_empty ) {
			return $this->render_empty_field();
		}

		// If value appears to be masked (contains •), show masked field.
		if ( $is_masked ) {
			return $this->render_masked_field();
		}

		// If value is encrypted, show encrypted field with controls.
		if ( Encryption::is_encrypted_value( $value ) ) {
			return $this->render_encrypted_field();
		}

		// Fallback: show as normal input (shouldn't happen in normal usage).
		return $this->render_empty_field();
	}

	/**
	 * Render field when empty
	 *
	 * @return string HTML output.
	 */
	private function render_empty_field(): string {
		$this->config['placeholder'] = $this->config['placeholder'] ?? 'Enter value...';

		$value = $this->get_value();
		$type  = $this->config['type'];
		$attrs = $this->get_attributes();

		// Build attributes string.
		$attr_string = $this->build_attributes_string( $attrs );

		return sprintf(
			'<input type="%s" value="%s" %s />',
			esc_attr( $type ),
			esc_attr( $value ),
			$attr_string
		);
	}

	/**
	 * Render field with masked value
	 *
	 * @return string HTML output.
	 */
	private function render_masked_field(): string {
		$value = $this->get_value();
		$attrs = $this->get_attributes();

		// Create masked display.
		$field_id   = $this->config['id'] ?? '';
		$field_name = $this->config['name'] ?? '';

		$html = '<div class="campaignbridge-encrypted-field" data-field-id="' . esc_attr( $field_id ) . '">';

		// Hidden input with actual value.
		$html .= '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '" />';

		// Masked display input (readonly).
		$display_attrs             = $attrs;
		$display_attrs['type']     = 'text';
		$display_attrs['value']    = $value;
		$display_attrs['readonly'] = 'readonly';
		$display_attrs['class']    = ( $display_attrs['class'] ?? '' ) . ' campaignbridge-encrypted-display';

		$html .= '<input ' . $this->build_attributes_string( $display_attrs ) . ' />';

		// Control buttons.
		$html .= '<div class="campaignbridge-encrypted-controls">';

		if ( $this->show_reveal ) {
			$html .= '<button type="button" class="button button-secondary campaignbridge-reveal-btn" data-action="reveal">';
			$html .= '<span class="dashicons dashicons-visibility"></span> ' . esc_html__( 'Reveal', 'campaignbridge' );
			$html .= '</button>';
		}

		if ( $this->show_edit ) {
			$html .= '<button type="button" class="button button-secondary campaignbridge-edit-btn" data-action="edit">';
			$html .= '<span class="dashicons dashicons-edit"></span> ' . esc_html__( 'Edit', 'campaignbridge' );
			$html .= '</button>';
		}

		$html .= '</div>';

		// Editable input (hidden initially).
		$edit_attrs                = $attrs;
		$edit_attrs['type']        = 'password';
		$edit_attrs['value']       = '';
		$edit_attrs['style']       = 'display: none;';
		$edit_attrs['class']       = ( $edit_attrs['class'] ?? '' ) . ' campaignbridge-encrypted-edit';
		$edit_attrs['placeholder'] = esc_html__( 'Enter new value...', 'campaignbridge' );

		$html .= '<input ' . $this->build_attributes_string( $edit_attrs ) . ' data-original-name="' . esc_attr( $field_name ) . '" />';

		// Edit controls (hidden initially).
		$html .= '<div class="campaignbridge-edit-controls" style="display: none;">';
		$html .= '<button type="button" class="button button-primary campaignbridge-save-btn" data-action="save">';
		$html .= esc_html__( 'Save', 'campaignbridge' );
		$html .= '</button>';
		$html .= '<button type="button" class="button button-secondary campaignbridge-cancel-btn" data-action="cancel">';
		$html .= esc_html__( 'Cancel', 'campaignbridge' );
		$html .= '</button>';
		$html .= '</div>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render field with encrypted value
	 *
	 * @return string HTML output.
	 */
	private function render_encrypted_field(): string {
		$value = $this->get_value();

		try {
			// Try to decrypt for display (will check permissions).
			$decrypted = Encryption::decrypt_for_context( $value, $this->context );
			$masked    = $this->mask_value( $decrypted );

			// Temporarily set masked value for rendering.
			$original_value        = $this->config['value'];
			$this->config['value'] = $masked;

			// Render as masked field.
			$result = $this->render_masked_field();

			// Restore original encrypted value.
			$this->config['value'] = $original_value;

			return $result;

		} catch ( \RuntimeException $e ) {
			// Permission denied - show permission error.
			return $this->render_permission_denied();
		}
	}

	/**
	 * Render permission denied message
	 *
	 * @return string HTML output.
	 */
	private function render_permission_denied(): string {
		$field_id = $this->config['id'] ?? '';

		$html  = '<div class="campaignbridge-encrypted-field campaignbridge-permission-denied" data-field-id="' . esc_attr( $field_id ) . '">';
		$html .= '<input type="text" readonly="readonly" value="' . esc_attr__( '•••••••••••••••• (Access Restricted)', 'campaignbridge' ) . '" class="regular-text" />';
		$html .= '<div class="campaignbridge-permission-notice">';
		$html .= '<span class="dashicons dashicons-lock"></span> ';
		$html .= esc_html__( 'You do not have permission to view this encrypted value.', 'campaignbridge' );
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Check if a value appears to be masked
	 *
	 * @param string $value The value to check.
	 * @return bool True if value appears masked.
	 */
	private function is_masked_value( string $value ): bool {
		return strpos( $value, '•' ) !== false;
	}

	/**
	 * Mask a decrypted value for display
	 *
	 * @param string $value The decrypted value.
	 * @return string The masked value.
	 */
	private function mask_value( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$length = strlen( $value );

		if ( $length <= 4 ) {
			return str_repeat( '•', $length );
		}

		// Show last 4 characters, mask the rest.
		$visible = substr( $value, -4 );
		$masked  = str_repeat( '•', $length - 4 );

		return $masked . $visible;
	}

	/**
	 * Build attributes string from array
	 *
	 * @param array<string, mixed> $attrs Attributes array.
	 * @return string Attributes string.
	 */
	private function build_attributes_string( array $attrs ): string {
		$parts = array();

		foreach ( $attrs as $key => $value ) {
			if ( is_bool( $value ) ) {
				if ( $value ) {
					$parts[] = esc_attr( $key );
				}
			} else {
				$parts[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
			}
		}

		return implode( ' ', $parts );
	}
}
