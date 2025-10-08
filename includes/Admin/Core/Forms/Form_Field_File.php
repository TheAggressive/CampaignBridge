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
	 * Render the input element
	 */
	public function render_input(): void {
		$attributes = $this->render_common_attributes();

		// Add file-specific attributes
		$accept = $this->config['accept'] ?? '';
		if ( $accept ) {
			$attributes .= sprintf( ' accept="%s"', esc_attr( $accept ) );
		}

		$multiple = $this->config['multiple'] ?? false;
		if ( $multiple ) {
			$attributes .= ' multiple';
		}

		printf( '<input type="file" %s />', $attributes );

		// Show current file if editing
		$current_value = $this->get_value();
		if ( $current_value ) {
			$this->render_current_file_display( $current_value );
		}

		// Add file requirements info
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
	 * Render single file display
	 *
	 * @param mixed $file File info.
	 */
	private function render_single_file_display( $file ): void {
		if ( is_numeric( $file ) ) {
			// WordPress attachment ID
			$file_url  = wp_get_attachment_url( $file );
			$file_name = basename( get_attached_file( $file ) );
		} elseif ( is_string( $file ) ) {
			// File URL or path
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
				esc_attr( is_numeric( $file ) ? $file : $file_url )
			);
		}
	}

	/**
	 * Render file requirements information
	 */
	private function render_file_requirements(): void {
		$requirements = [];

		// Max file size
		$max_size       = $this->config['max_size'] ?? wp_max_upload_size();
		$requirements[] = sprintf(
			/* translators: %s: maximum file size */
			__( 'Maximum file size: %s', 'campaignbridge' ),
			size_format( $max_size )
		);

		// Allowed types
		$allowed_types = $this->config['allowed_types'] ?? [];
		if ( ! empty( $allowed_types ) ) {
			$types_list     = implode( ', ', array_map( 'strtoupper', $allowed_types ) );
			$requirements[] = sprintf(
				/* translators: %s: allowed file types */
				__( 'Allowed types: %s', 'campaignbridge' ),
				$types_list
			);
		}

		// Multiple files
		if ( $this->config['multiple'] ?? false ) {
			$requirements[] = __( 'Multiple files allowed', 'campaignbridge' );
		}

		if ( ! empty( $requirements ) ) {
			echo '<div class="file-requirements">';
			foreach ( $requirements as $requirement ) {
				printf( '<p class="description">%s</p>', esc_html( $requirement ) );
			}
			echo '</div>';
		}
	}

	/**
	 * Validate field value
	 *
	 * @param mixed $value Value to validate.
	 * @return bool|\WP_Error True if valid, \WP_Error if invalid.
	 */
	public function validate( $value ) {
		// First run parent validation
		$parent_validation = parent::validate( $value );
		if ( is_wp_error( $parent_validation ) ) {
			return $parent_validation;
		}

		// File-specific validation
		if ( isset( $_FILES[ $this->config['name'] ] ) ) {
			$file_data = $_FILES[ $this->config['name'] ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

			// Handle multiple files
			if ( is_array( $file_data['name'] ) ) {
				foreach ( $file_data['name'] as $index => $file_name ) {
					if ( empty( $file_name ) ) {
						continue;
					}

					$single_file = [
						'name'     => $file_data['name'][ $index ],
						'type'     => $file_data['type'][ $index ],
						'tmp_name' => $file_data['tmp_name'][ $index ],
						'error'    => $file_data['error'][ $index ],
						'size'     => $file_data['size'][ $index ],
					];

					$file_validation = $this->validate_single_file( $single_file );
					if ( is_wp_error( $file_validation ) ) {
						return $file_validation;
					}
				}
			} else {
				// Single file
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
	 * @param array $file File data.
	 * @return bool|WP_Error
	 */
	private function validate_single_file( array $file ) {
		$security = new Form_Security( $this->config['id'] );
		return $security->validate_file_upload( $file, $this->config );
	}
}
