<?php
/**
 * Form Security
 *
 * Handles security features including nonces, CSRF protection, and input sanitization.
 *
 * @package CampaignBridge\Admin\Core\Forms
 */

namespace CampaignBridge\Admin\Core\Forms;

/**
 * Form Security Class
 *
 * @package CampaignBridge\Admin\Core\Forms
 */
class Form_Security {

	/**
	 * Form ID for unique nonce generation
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
	 * Set form ID
	 *
	 * @param string $form_id Form identifier.
	 * @return void
	 */
	public function set_form_id( string $form_id ): void {
		$this->form_id = $form_id;
	}

	/**
	 * Verify security for form submission
	 *
	 * @return bool True if security checks pass, false otherwise.
	 */
	public function verify_request(): bool {
		// Verify nonce.
		$nonce_action = 'campaignbridge_form_' . $this->form_id;
		$nonce_name   = $this->form_id . '_wpnonce';
		if ( ! isset( $_POST[ $nonce_name ] ) || ! \wp_verify_nonce( sanitize_text_field( \wp_unslash( $_POST[ $nonce_name ] ) ), $nonce_action ) ) {
			return false;
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Check if request is coming from admin area.
		if ( ! is_admin() ) {
			return false;
		}

		// Check referer.
		$admin_url = admin_url();
		$referer   = \wp_get_referer();

		if ( $referer && strpos( $referer, $admin_url ) !== 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Render security fields
	 */
	public function render_security_fields(): void {
		$nonce_action = 'campaignbridge_form_' . $this->form_id;
		$nonce_name   = $this->form_id . '_wpnonce';

		\wp_nonce_field( $nonce_action, $nonce_name );

		// Add additional security fields.
		printf(
			'<input type="hidden" name="%s[form_id]" value="%s" />',
			\esc_attr( $this->form_id ),
			\esc_attr( $this->form_id )
		);

		// Add timestamp for additional verification.
		printf(
			'<input type="hidden" name="%s[timestamp]" value="%s" />',
			\esc_attr( $this->form_id ),
			\esc_attr( time() )
		);
	}

	/**
	 * Sanitize input based on field configuration.
	 *
	 * @param mixed $value       Raw input value.
	 * @param array $field_config Field configuration.
	 * @return mixed Sanitized value.
	 */
	public function sanitize_input( $value, array $field_config ) {
		$field_type = $field_config['type'] ?? 'text';

		switch ( $field_type ) {
			case 'email':
				return sanitize_email( $value );

			case 'url':
				return \esc_url_raw( $value );

			case 'number':
				return is_numeric( $value ) ? floatval( $value ) : 0;

			case 'integer':
				return absint( $value );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'wysiwyg':
				return \wp_kses_post( $value );

			case 'checkbox':
				// Handle checkbox arrays (multiple selections).
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', $value );
				}
				return ! empty( $value ) ? 1 : 0;

			case 'multiselect':
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', $value );
				}
				return array();

			case 'file':
				// File inputs are handled separately.
				return $value;

			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Validate file upload security.
	 *
	 * @param array $file     File data from $_FILES.
	 * @param array $field_config Field configuration.
	 * @return bool|\WP_Error True if valid, \WP_Error if invalid.
	 */
	public function validate_file_upload( array $file, array $field_config ) {
		// Check for upload errors.
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return new \WP_Error(
				'upload_error',
				$this->get_upload_error_message( $file['error'] )
			);
		}

		// Verify file is actually uploaded.
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error(
				'upload_error',
				\__( 'File was not uploaded properly.', 'campaignbridge' )
			);
		}

		// Check file size.
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

		// Check MIME type.
		$allowed_types = $field_config['allowed_types'] ?? array();
		if ( ! empty( $allowed_types ) ) {
			$filetype = \wp_check_filetype( $file['name'] );
			if ( ! in_array( $filetype['type'], $allowed_types, true ) ) {
				return new \WP_Error(
					'invalid_file_type',
					\__( 'File type not allowed.', 'campaignbridge' )
				);
			}
		}

		// Basic malware scan (check for PHP content in uploaded files).
		if ( $this->contains_malicious_content( $file['tmp_name'] ) ) {
			return new \WP_Error(
				'malicious_content',
				\__( 'File contains potentially malicious content.', 'campaignbridge' )
			);
		}

		return true;
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

	/**
	 * Check if file contains malicious content.
	 *
	 * @param string $file_path Path to uploaded file.
	 * @return bool True if malicious content detected.
	 */
	private function contains_malicious_content( string $file_path ): bool {
		$content = file_get_contents( $file_path, false, null, 0, 1024 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $content ) {
			return true; // Can't read file, assume malicious.
		}

		// Check for PHP code.
		if ( preg_match( '/<\?php|<\?=|\$\w+\s*=/i', $content ) ) {
			return true;
		}

		// Check for script tags.
		if ( preg_match( '/<script[^>]*>.*?<\/script>/is', $content ) ) {
			return true;
		}

		// Check for suspicious file extensions in content.
		if ( preg_match( '/\.(php|phtml|php3|php4|php5|exe|bat|cmd|com|scr)\b/i', $content ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Rate limiting for form submissions
	 *
	 * @param int $max_attempts Maximum attempts allowed.
	 * @param int $time_window  Time window in seconds.
	 * @return bool True if within limits, false if rate limited.
	 */
	public function check_rate_limit( int $max_attempts = 10, int $time_window = 300 ): bool {
		$user_id       = \get_current_user_id();
		$transient_key = 'form_rate_limit_' . $this->form_id . '_' . $user_id;

		$attempts = \get_transient( $transient_key );

		if ( false === $attempts ) {
			$attempts = 0;
		}

		if ( $attempts >= $max_attempts ) {
			return false; // Rate limited.
		}

		\set_transient( $transient_key, $attempts + 1, $time_window );
		return true;
	}

	/**
	 * Log security event
	 *
	 * @param string $event     Event type.
	 * @param array  $context   Additional context.
	 */
	public function log_security_event( string $event, array $context = array() ): void {
		$log_data = array_merge(
			$context,
			array(
				'event'      => $event,
				'form_id'    => $this->form_id,
				'user_id'    => \get_current_user_id(),
				'user_ip'    => $this->get_client_ip(),
				'user_agent' => sanitize_text_field( \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
				'timestamp'  => current_time( 'mysql' ),
			)
		);

		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf(
				'[SECURITY] %s: %s',
				$event,
				\wp_json_encode( $log_data )
			)
		);
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip(): string {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( isset( $_SERVER[ $header ] ) ) {
				$ip = trim( sanitize_text_field( \wp_unslash( $_SERVER[ $header ] ) ) );

				// Handle comma-separated IPs (like X-Forwarded-For).
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}

				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return '127.0.0.1'; // Fallback.
	}
}
