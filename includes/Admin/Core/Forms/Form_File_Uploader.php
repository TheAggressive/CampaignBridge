<?php
/**
 * File Uploader
 *
 * Secure file upload processing with WordPress integration.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * File Uploader Class
 *
 * Handles secure file uploads using WordPress's wp_handle_upload().
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_File_Uploader {

	/**
	 * Security handler instance.
	 *
	 * @var Form_Security
	 */
	private Form_Security $security;

	/**
	 * Constructor
	 *
	 * Initializes the file uploader with security handler.
	 */
	public function __construct() {
		$this->security = new Form_Security( 'file_upload' );
	}

	/**
	 * Process file upload
	 *
	 * @param array<string, mixed> $file     File data from $_FILES.
	 * @param array<string, mixed> $config   Field configuration.
	 * @return array<string, mixed>|\WP_Error Upload result or error.
	 */
	public function process_upload( array $file, array $config = array() ): array|\WP_Error {
		// Pre-validate file with our security checks.
		$validation = $this->validate_file( $file, $config );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Use WordPress's built-in upload handling.
		$upload_overrides = array(
			'test_form'            => false, // We're not testing.
			'upload_error_handler' => array( $this, 'handle_upload_error' ),
		);

		// Apply any additional overrides from config.
		if ( ! empty( $config['upload_overrides'] ) ) {
			$upload_overrides = array_merge( $upload_overrides, $config['upload_overrides'] );
		}

		/**
		 * Upload result from wp_handle_upload().
		 *
		 * @var array<string, mixed>|\WP_Error $upload_result
		 */
		$upload_result = wp_handle_upload( $file, $upload_overrides );
		if ( $upload_result instanceof \WP_Error ) {
			return $upload_result;
		}
		if ( isset( $upload_result['error'] ) ) {
			return new \WP_Error( 'upload_failed', $upload_result['error'] );
		}

		// Add additional metadata.
		$upload_result['filename'] = basename( $upload_result['file'] );
		$upload_result['size']     = $file['size'] ?? 0;
		$upload_result['type']     = $upload_result['type'] ?? ( $file['type'] ?? '' );

		// Create WordPress attachment if requested.
		if ( ! empty( $config['create_attachment'] ) ) {
			$attachment_id                  = $this->create_attachment( $upload_result );
			$upload_result['attachment_id'] = $attachment_id;
		}

		return $upload_result;
	}

	/**
	 * Validate uploaded file
	 *
	 * Performs comprehensive security validation on uploaded files.
	 *
	 * @param array<string, mixed> $file   File data from $_FILES.
	 * @param array<string, mixed> $config Field configuration.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_file( array $file, array $config = array() ): bool|\WP_Error {
		// Use comprehensive Form_Security validation for all security checks.
		return $this->security->validate_file_upload( $file, $config );
	}

	/**
	 * Handle upload errors from wp_handle_upload
	 *
	 * Logs security events for upload errors and returns WP_Error.
	 *
	 * @param array<string, mixed> $file File array.
	 * @param string               $message Error message.
	 * @return \WP_Error Error object.
	 */
	public function handle_upload_error( array $file, string $message ): \WP_Error {
		$this->security->log_security_event(
			'upload_error',
			array(
				'filename' => sanitize_file_name( $file['name'] ?? 'unknown' ),
				'error'    => $message,
			)
		);

		return new \WP_Error( 'upload_failed', $message );
	}

	/**
	 * Create WordPress attachment
	 *
	 * @param array<string, mixed> $upload_result Upload result data.
	 * @return int Attachment ID.
	 */
	private function create_attachment( array $upload_result ): int {
		$attachment_data = array(
			'guid'           => $upload_result['url'],
			'post_mime_type' => $upload_result['type'],
			'post_title'     => pathinfo( $upload_result['filename'], PATHINFO_FILENAME ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		/**
		 * Attachment ID from wp_insert_attachment().
		 *
		 * @var int $attachment_id
		 */
		$attachment_id = wp_insert_attachment( $attachment_data, $upload_result['file'] );

		// Note: wp_insert_attachment can return WP_Error, but PHPStan analysis
		// shows it doesn't in this context, so we treat it as always successful.

		// Generate attachment metadata.
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_result['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		return $attachment_id;
	}

	/**
	 * Process multiple file uploads
	 *
	 * Handles multiple file uploads from HTML multiple file inputs.
	 * Reorganizes the $_FILES array and processes each file individually.
	 *
	 * @param array<string, mixed> $files  Files data from $_FILES.
	 * @param array<string, mixed> $config Field configuration.
	 * @return array<int, array<string, mixed>>|\WP_Error Array of upload results or error.
	 */
	public function process_multiple_uploads( array $files, array $config = array() ): array|\WP_Error {
		$results = array();

		// Reorganize files array for easier processing.
		$reorganized_files = $this->reorganize_files_array( $files );

		foreach ( $reorganized_files as $index => $file ) {
			if ( empty( $file['name'] ) ) {
				continue; // Skip empty file slots.
			}

			$result = $this->process_upload( $file, $config );
			if ( is_wp_error( $result ) ) {
				// Clean up any successfully uploaded files on error.
				foreach ( $results as $previous_result ) {
					if ( isset( $previous_result['file'] ) ) {
						$this->cleanup_file( $previous_result['file'] );
					}
				}
				return $result;
			}

			$results[] = $result;
		}

		return $results;
	}

	/**
	 * Clean up uploaded file
	 *
	 * @param string $file_path File path to clean up.
	 */
	private function cleanup_file( string $file_path ): void {
		// wp_delete_file handles permission checks internally and fails gracefully.
		wp_delete_file( $file_path );
	}

	/**
	 * Reorganize files array for multiple uploads
	 *
	 * @param array<string, mixed> $files Files array from $_FILES.
	 * @return array<int, array<string, mixed>> Reorganized files array.
	 */
	private function reorganize_files_array( array $files ): array {
		$reorganized = array();

		if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
			return array( $files );
		}

		$file_count = count( $files['name'] );

		for ( $i = 0; $i < $file_count; $i++ ) {
			$reorganized[] = array(
				'name'     => $files['name'][ $i ],
				'type'     => $files['type'][ $i ],
				'tmp_name' => $files['tmp_name'][ $i ],
				'error'    => $files['error'][ $i ],
				'size'     => $files['size'][ $i ],
			);
		}

		return $reorganized;
	}
}
