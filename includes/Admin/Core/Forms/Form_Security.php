<?php
/**
 * Form request verification and upload policy.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Verifies form submissions and checks uploads against a field's policy.
 *
 * A submission must be a POST carrying this form's nonce (CSRF) from a user
 * with `campaignbridge_manage` (authorization). Values are then sanitized
 * per field type by Field_Sanitizer and escaped when rendered; there is no
 * separate attack-pattern filter.
 */
class Form_Security {

	/**
	 * Form ID; scopes the nonce action and field name.
	 *
	 * @var string
	 */
	private string $form_id;

	/**
	 * Constructor
	 *
	 * @param string $form_id Unique form identifier.
	 */
	public function __construct( string $form_id ) {
		$this->form_id = $form_id;
	}

	/**
	 * Verify a form submission: POST, valid nonce, and capability.
	 *
	 * @return bool True if the request may be processed.
	 */
	public function verify_request(): bool {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $request_method ) {
			return false;
		}

		$nonce_name = $this->form_id . '_wpnonce';
		if ( ! isset( $_POST[ $nonce_name ] ) || ! \wp_verify_nonce( sanitize_text_field( \wp_unslash( $_POST[ $nonce_name ] ) ), 'campaignbridge_form_' . $this->form_id ) ) {
			return false;
		}

		return \current_user_can( \CampaignBridge\Core\Capabilities::MANAGE );
	}

	/**
	 * Render the nonce field for this form.
	 */
	public function render_security_fields(): void {
		\wp_nonce_field( 'campaignbridge_form_' . $this->form_id, $this->form_id . '_wpnonce' );
	}

	/**
	 * Check an upload against the field's policy before wp_handle_upload().
	 *
	 * WordPress remains authoritative for the upload test, filename
	 * sanitization, and the site-wide type list. This adds the field's own
	 * rules: an explicit MIME allowlist, never SVG (no reviewed sanitizer
	 * exists), the detected type matching the claimed one, and a size limit.
	 *
	 * @param array<string, mixed> $file         One entry from $_FILES.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @return bool|\WP_Error True if valid, \WP_Error if invalid.
	 */
	public function validate_file_upload( array $file, array $field_config ) {
		$error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error ) {
			return new \WP_Error( 'upload_error', $this->get_upload_error_message( (int) $error ) );
		}

		$filename = is_string( $file['name'] ?? null ) ? $file['name'] : '';
		if ( '' === $filename ) {
			return new \WP_Error( 'invalid_filename', \__( 'Filename is required.', 'campaignbridge' ) );
		}

		$size_validation = $this->validate_file_size( $file, $field_config );
		if ( is_wp_error( $size_validation ) ) {
			return $size_validation;
		}

		return $this->validate_mime_type( $file, $field_config, $filename );
	}

	/**
	 * Validate file size.
	 *
	 * @param array<string, mixed> $file         File data.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_file_size( array $file, array $field_config ) {
		$max_size = $field_config['max_size'] ?? \wp_max_upload_size();
		if ( $file['size'] > $max_size ) {
			return new \WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: maximum file size */
					\__( 'File size exceeds maximum allowed size of %s.', 'campaignbridge' ),
					size_format( $max_size )
				)
			);
		}

		if ( 0 === $file['size'] ) {
			return new \WP_Error( 'empty_file', \__( 'Uploaded file is empty.', 'campaignbridge' ) );
		}

		return true;
	}

	/**
	 * Validate the detected MIME type against the field's explicit allowlist.
	 *
	 * @param array<string, mixed> $file         File data.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param string               $filename     Client filename, used for extension detection.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_mime_type( array $file, array $field_config, string $filename ) {
		$allowed_types = $field_config['allowed_types'] ?? null;
		if ( ! is_array( $allowed_types ) || array() === $allowed_types ) {
			return new \WP_Error(
				'missing_allowed_file_types',
				\__( 'An explicit list of allowed file types is required.', 'campaignbridge' )
			);
		}

		$allowed_types = array_values(
			array_filter(
				$allowed_types,
				static fn( $type ): bool => is_string( $type ) && '' !== trim( $type )
			)
		);
		if ( array() === $allowed_types || in_array( 'image/svg+xml', $allowed_types, true ) ) {
			return new \WP_Error( 'invalid_file_type', \__( 'File type not allowed.', 'campaignbridge' ) );
		}

		$tmp_name      = is_string( $file['tmp_name'] ?? null ) ? $file['tmp_name'] : '';
		$filetype      = \wp_check_filetype_and_ext( $tmp_name, $filename );
		$detected_mime = is_string( $filetype['type'] ?? null ) ? $filetype['type'] : '';
		$provided_mime = is_string( $file['type'] ?? null ) ? $file['type'] : '';
		$valid_type    = '' !== $detected_mime
			&& in_array( $detected_mime, $allowed_types, true )
			&& 'image/svg+xml' !== $detected_mime
			&& ( '' === $provided_mime || $provided_mime === $detected_mime );

		return $valid_type ? true : new \WP_Error( 'invalid_file_type', \__( 'File type not allowed.', 'campaignbridge' ) );
	}

	/**
	 * Get upload error message.
	 *
	 * @param int $error_code Upload error code.
	 * @return string Error message.
	 */
	private function get_upload_error_message( int $error_code ): string {
		switch ( $error_code ) {
			case UPLOAD_ERR_INI_SIZE:
				return \__( 'File exceeds the maximum upload size for this site.', 'campaignbridge' );
			case UPLOAD_ERR_FORM_SIZE:
				return \__( 'File exceeds the maximum upload size for this form.', 'campaignbridge' );
			case UPLOAD_ERR_PARTIAL:
				return \__( 'File was only partially uploaded.', 'campaignbridge' );
			case UPLOAD_ERR_NO_FILE:
				return \__( 'No file was uploaded.', 'campaignbridge' );
			case UPLOAD_ERR_NO_TMP_DIR:
				return \__( 'Missing temporary folder.', 'campaignbridge' );
			case UPLOAD_ERR_CANT_WRITE:
				return \__( 'Failed to write file to disk.', 'campaignbridge' );
			case UPLOAD_ERR_EXTENSION:
				return \__( 'File upload stopped by extension.', 'campaignbridge' );
			default:
				return \__( 'Unknown upload error.', 'campaignbridge' );
		}
	}
}
