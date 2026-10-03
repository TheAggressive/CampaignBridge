<?php
/**
 * File Uploader
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Stores uploads through wp_handle_upload() after the field's own policy.
 *
 * A failed upload never leaves files or attachments behind: a failed
 * attachment insert removes its file, and a failed multi-file upload removes
 * everything the earlier files created.
 */
class Form_File_Uploader {

	/**
	 * Stores one validated upload; wp_handle_upload() outside tests.
	 *
	 * @var callable(array<string, mixed>, array<string, mixed>): array<string, mixed>
	 */
	private $store;

	/**
	 * Constructor
	 *
	 * @param callable|null $store Storage function with wp_handle_upload()'s signature. Tests pass
	 *                             wp_handle_sideload(), whose only difference is not requiring
	 *                             the file to have arrived over HTTP.
	 */
	public function __construct( ?callable $store = null ) {
		$this->store = $store ?? 'wp_handle_upload';
	}

	/**
	 * Process file upload
	 *
	 * @param array<string, mixed> $file     File data from $_FILES.
	 * @param array<string, mixed> $config   Field configuration.
	 * @return array<string, mixed>|\WP_Error Upload result or error.
	 */
	public function process_upload( array $file, array $config = array() ): array|\WP_Error {
		$validation = $this->validate_file( $file, $config );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// The form's own nonce was verified; wp_handle_upload() runs its other tests.
		$upload_overrides = array( 'test_form' => false );
		if ( ! empty( $config['upload_overrides'] ) ) {
			$upload_overrides = array_merge( $upload_overrides, $config['upload_overrides'] );
		}

		/**
		 * Upload result from wp_handle_upload().
		 *
		 * @var array<string, mixed>|\WP_Error $upload_result
		 */
		$upload_result = ( $this->store )( $file, $upload_overrides );
		if ( $upload_result instanceof \WP_Error ) {
			return $upload_result;
		}
		if ( isset( $upload_result['error'] ) ) {
			return new \WP_Error( 'upload_failed', $upload_result['error'] );
		}

		$upload_result['filename'] = basename( $upload_result['file'] );
		$upload_result['size']     = $file['size'] ?? 0;
		$upload_result['type']     = $upload_result['type'] ?? ( $file['type'] ?? '' );

		if ( ! empty( $config['create_attachment'] ) ) {
			$attachment_id = $this->create_attachment( $upload_result );
			if ( is_wp_error( $attachment_id ) ) {
				\wp_delete_file( $upload_result['file'] );
				return $attachment_id;
			}
			$upload_result['attachment_id'] = $attachment_id;
		}

		return $upload_result;
	}

	/**
	 * Validate an uploaded file against the field's policy.
	 *
	 * @param array<string, mixed> $file   File data from $_FILES.
	 * @param array<string, mixed> $config Field configuration.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_file( array $file, array $config = array() ): bool|\WP_Error {
		return ( new Form_Security( 'file_upload' ) )->validate_file_upload( $file, $config );
	}

	/**
	 * Create a media library attachment for a stored upload.
	 *
	 * @param array<string, mixed> $upload_result Upload result data.
	 * @return int|\WP_Error Attachment ID, or an error when WordPress refused the insert.
	 */
	private function create_attachment( array $upload_result ): int|\WP_Error {
		$attachment_data = array(
			'guid'           => $upload_result['url'],
			'post_mime_type' => $upload_result['type'],
			'post_title'     => pathinfo( $upload_result['filename'], PATHINFO_FILENAME ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment_data, $upload_result['file'], 0, true );
		if ( is_wp_error( $attachment_id ) || 0 === $attachment_id ) {
			return new \WP_Error( 'attachment_failed', \__( 'The file was uploaded but could not be added to the media library.', 'campaignbridge' ) );
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload_result['file'] ) );

		return $attachment_id;
	}

	/**
	 * Process multiple file uploads, all or nothing.
	 *
	 * @param array<string, mixed> $files  Files data from $_FILES.
	 * @param array<string, mixed> $config Field configuration.
	 * @return array<int, array<string, mixed>>|\WP_Error Array of upload results or error.
	 */
	public function process_multiple_uploads( array $files, array $config = array() ): array|\WP_Error {
		$results = array();

		foreach ( $this->reorganize_files_array( $files ) as $file ) {
			if ( empty( $file['name'] ) ) {
				continue;
			}

			$result = $this->process_upload( $file, $config );
			if ( is_wp_error( $result ) ) {
				foreach ( $results as $previous_result ) {
					$this->remove_upload( $previous_result );
				}
				return $result;
			}

			$results[] = $result;
		}

		return $results;
	}

	/**
	 * Remove a stored upload and the attachment created for it, if any.
	 *
	 * @param array<string, mixed> $upload_result Upload result data.
	 */
	private function remove_upload( array $upload_result ): void {
		$attachment_id = $upload_result['attachment_id'] ?? 0;
		if ( is_int( $attachment_id ) && 0 < $attachment_id ) {
			// Force-deleting an attachment also deletes its file.
			\wp_delete_attachment( $attachment_id, true );
			return;
		}
		if ( is_string( $upload_result['file'] ?? null ) ) {
			\wp_delete_file( $upload_result['file'] );
		}
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
