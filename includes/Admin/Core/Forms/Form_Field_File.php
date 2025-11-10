<?php
/**
 * Form Field File
 *
 * Handles file upload input fields.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Field File Class
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Field_File extends Form_Field_Base {

	/**
	 * Processed upload data
	 *
	 * @var mixed
	 */
	private $upload_data;

	/**
	 * Render the input element
	 */
	public function render_input(): void {
		// Ensure we have the correct class for styling.
		if ( empty( $this->config['class'] ) || 'regular-text' === $this->config['class'] ) {
			$this->config['class'] = 'campaignbridge-field__file';
		}

		$attributes = $this->render_common_attributes();

		// Add file-specific attributes.
		$accept = $this->config['accept'] ?? '';
		if ( $accept ) {
			$attributes .= sprintf( ' accept="%s"', esc_attr( $accept ) );
		}

		$multiple = $this->config['multiple_files'] ?? false;
		if ( $multiple ) {
			$attributes .= ' multiple';
			// Ensure the field name ends with [] for multiple files.
			$attributes = preg_replace( '/name="([^"]*)"/', 'name="$1[]"', $attributes );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes is built by render_common_attributes() with proper escaping
		printf( '<input type="file" %s />', $attributes );

		// Show current file if editing.
		$current_value = $this->get_value();
		if ( $current_value ) {
			$this->render_current_file_display( $current_value );
		}

		// Add file requirements info.
		$this->render_file_requirements();
	}

	/**
	 * Render current file display
	 *
	 * @param mixed $current_value Current file value.
	 */
	private function render_current_file_display( $current_value ): void {
		if ( is_array( $current_value ) ) {
			echo '<div class="current-files">';
			foreach ( $current_value as $file ) {
				$this->render_single_file_display( $file );
			}
			echo '</div>';
		} else {
			$this->render_single_file_display( $current_value );
		}
	}

	/**
	 * Set processed upload data
	 *
	 * @param mixed $upload_data Processed upload data.
	 * @return void
	 */
	public function set_upload_data( $upload_data ): void {
		$this->upload_data = $upload_data;

		// Update field value based on upload data.
		if ( is_array( $upload_data ) ) {
			if ( isset( $upload_data['attachment_id'] ) ) {
				$this->set_value( $upload_data['attachment_id'] );
			} elseif ( isset( $upload_data['url'] ) ) {
				$this->set_value( $upload_data['url'] );
			} else {
				$this->set_value( $upload_data );
			}
		}
	}

	/**
	 * Get processed upload data
	 *
	 * @return mixed Upload data.
	 */
	public function get_upload_data() {
		return $this->upload_data ?? null;
	}

	/**
	 * Render single file display
	 *
	 * @param mixed $file File info.
	 */
	private function render_single_file_display( $file ): void {
		if ( is_numeric( $file ) ) {
			// WordPress attachment ID.
			$attachment_id = (int) $file;
			$file_url      = wp_get_attachment_url( $attachment_id );
			$attached_file = get_attached_file( $attachment_id );
			$file_name     = $attached_file ? basename( $attached_file ) : '';
		} elseif ( is_string( $file ) ) {
			// File URL or path.
			$file_url  = $file;
			$file_name = basename( $file );
		} else {
			return;
		}

		if ( $file_url ) {
			printf(
				'<div class="current-file">
					<p><strong>%s:</strong> <a href="%s" target="_blank">%s</a></p>
					<input type="hidden" name="%s" value="%s" />
				</div>',
				esc_html__( 'Current file', 'campaignbridge' ),
				esc_url( $file_url ),
				esc_html( $file_name ),
				esc_attr( $this->config['name'] . '_current' ),
				esc_attr( (string) ( is_numeric( $file ) ? $file : $file_url ) )
			);
		}
	}

	/**
	 * Render file requirements information
	 */
	private function render_file_requirements(): void {
		$requirements = array();

		// Max file size.
		$max_size       = $this->config['max_size'] ?? wp_max_upload_size();
		$requirements[] = sprintf(
			/* translators: %s: maximum file size */
			__( 'Maximum file size: %s', 'campaignbridge' ),
			size_format( $max_size )
		);

		// Allowed types.
		$allowed_types = $this->config['allowed_types'] ?? array();
		if ( ! empty( $allowed_types ) ) {
			$types_list     = implode( ', ', array_map( 'strtoupper', $allowed_types ) );
			$requirements[] = sprintf(
				/* translators: %s: allowed file types */
				__( 'Allowed types: %s', 'campaignbridge' ),
				$types_list
			);
		}

		// Multiple files.
		if ( $this->config['multiple_files'] ?? false ) {
			$requirements[] = __( 'Multiple files allowed', 'campaignbridge' );
		}

		echo '<div class="file-requirements">';
		foreach ( $requirements as $requirement ) {
			printf( '<p class="description">%s</p>', esc_html( $requirement ) );
		}
		echo '</div>';
	}

	/**
	 * Validate field value
	 *
	 * @param mixed $value Value to validate.
	 * @return bool|\WP_Error True if valid, \WP_Error if invalid.
	 */
	public function validate( $value ) {
		// First run parent validation.
		$parent_validation = parent::validate( $value );
		if ( is_wp_error( $parent_validation ) ) {
			return $parent_validation;
		}

		// File-specific validation.
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_FILES[ $this->config['name'] ] ) ) {
			$file_data = $_FILES[ $this->config['name'] ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification

			// Handle multiple files.
			if ( is_array( $file_data['name'] ) ) {
				foreach ( $file_data['name'] as $index => $file_name ) {
					if ( empty( $file_name ) ) {
						continue;
					}

					$single_file = array(
						'name'     => $file_data['name'][ $index ],
						'type'     => $file_data['type'][ $index ],
						'tmp_name' => $file_data['tmp_name'][ $index ],
						'error'    => $file_data['error'][ $index ],
						'size'     => $file_data['size'][ $index ],
					);

					$file_validation = $this->validate_single_file( $single_file );
					if ( is_wp_error( $file_validation ) ) {
						return $file_validation;
					}
				}
			} else {
				// Single file.
				$file_validation = $this->validate_single_file( $file_data );
				if ( is_wp_error( $file_validation ) ) {
					return $file_validation;
				}
			}
		}

		return true;
	}

	/**
	 * Validate a single file
	 *
	 * @param array<string, mixed> $file File data.
	 * @return bool|\WP_Error
	 */
	private function validate_single_file( array $file ) {
		$security = new Form_Security( $this->config['id'] );
		return $security->validate_file_upload( $file, $this->config );
	}
}
