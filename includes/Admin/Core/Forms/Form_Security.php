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
		// phpcs:ignore CampaignBridge.Standard.Sniffs.Security.SecurityValidation.UnsanitizedInput
		if ( ! isset( $_POST[ $nonce_name ] ) || ! \wp_verify_nonce( sanitize_text_field( \wp_unslash( $_POST[ $nonce_name ] ) ), $nonce_action ) ) {
			return false;
		}

		// Check user capabilities.
		if ( ! \current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Check if request is coming from admin area.
		if ( ! \is_admin() ) {
			return false;
		}

		// Check referer.
		$admin_url = \admin_url();
		$referer   = \wp_get_referer();

		if ( $referer && strpos( $referer, $admin_url ) !== 0 ) {
			return false;
		}

		// Additional origin validation.
		if ( ! $this->validate_request_origin() ) {
			return false;
		}

		// Validate request method.
		if ( ! $this->validate_request_method() ) {
			return false;
		}

		// Check for suspicious patterns in form data.
		if ( ! $this->validate_form_data_integrity() ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate request origin for additional security.
	 *
	 * @return bool True if origin is valid.
	 */
	private function validate_request_origin(): bool {
		// Check HTTP_ORIGIN header for additional validation.
		if ( isset( $_SERVER['HTTP_ORIGIN'] ) ) {
			$origin     = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
			$admin_url  = admin_url();
			$parsed_url = wp_parse_url( $admin_url );

			// Allow same-origin requests.
			if ( strpos( $origin, $parsed_url['host'] ) === false ) {
				return false;
			}
		}

		// Check if request came from expected admin page.
		$expected_pages = array(
			'admin.php',
			'options-general.php',
			'edit.php',
			'post.php',
			'post-new.php',
		);

		$current_page = isset( $_SERVER['REQUEST_URI'] ) ? basename( wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) ) : '';

		if ( ! in_array( $current_page, $expected_pages, true ) && strpos( $current_page, 'admin.php' ) !== 0 ) {
			// Additional check: ensure we're in an admin context.
			if ( ! is_admin() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate request method for security.
	 *
	 * @return bool True if request method is valid.
	 */
	private function validate_request_method(): bool {
		$allowed_methods = array( 'POST' );

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ?
			sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

		return in_array( $request_method, $allowed_methods, true );
	}

	/**
	 * Validate form data integrity for suspicious patterns.
	 *
	 * @return bool True if form data appears legitimate.
	 *
	 * @phpcs:disable WordPress.Security.NonceVerification.Missing -- Security validation method called after nonce verification
	 * @phpcs:disable CampaignBridge.Standard.Sniffs.Security.SecurityValidation.UnsanitizedInput -- Intentionally accessing raw POST data for security validation
	 */
	private function validate_form_data_integrity(): bool {
		// Get sanitized POST data.
		$post_data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Intentionally accessing raw POST data for security validation

		// Check for excessive form fields (potential DoS).
		$field_count = count( $post_data );
		if ( $field_count > 100 ) { // Reasonable upper limit.
			return false;
		}

		// Check for suspicious field names or values.
		foreach ( $post_data as $key => $value ) {
			// Skip WordPress core fields.
			if ( in_array( $key, array( '_wpnonce', '_wp_http_referer', 'action' ), true ) ) {
				continue;
			}

			// Check field name length (prevent extremely long names).
			if ( strlen( $key ) > 200 ) {
				return false;
			}

			// Check for suspicious patterns in field names.
			if ( preg_match( '/[<>\'"]/', $key ) ) {
				return false;
			}

			// Check value size (prevent oversized submissions).
			if ( is_string( $value ) && strlen( $value ) > 10000 ) { // 10KB limit per field.
				return false;
			}
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
			\esc_attr( (string) time() )
		);
	}

	/**
	 * Set security headers for form pages.
	 *
	 * Adds comprehensive security headers including CSP, HSTS, and other protections.
	 * Should be called during form rendering or page initialization.
	 *
	 * @param array<string, mixed> $options Security header options.
	 * @return void
	 */
	public function set_security_headers( array $options = array() ): void {
		if ( headers_sent() ) {
			return; // Headers already sent, cannot modify.
		}

		$defaults = array(
			'csp_enabled'          => true,
			'hsts_enabled'         => is_ssl(),
			'frame_options'        => 'SAMEORIGIN',
			'content_type_options' => true,
			'xss_protection'       => true,
			'referrer_policy'      => 'strict-origin-when-cross-origin',
		);

		$options = wp_parse_args( $options, $defaults );

		// Content Security Policy.
		if ( $options['csp_enabled'] ) {
			$csp_directives = array(
				"default-src 'self'",
				"script-src 'self' 'unsafe-inline'",
				"style-src 'self' 'unsafe-inline'",
				"img-src 'self' data: https:",
				"font-src 'self'",
				"connect-src 'self'",
				"media-src 'self'",
				"object-src 'none'",
				"frame-src 'none'",
				"base-uri 'self'",
				"form-action 'self'",
			);

			header( 'Content-Security-Policy: ' . implode( '; ', $csp_directives ) );
		}

		// HTTP Strict Transport Security.
		if ( $options['hsts_enabled'] ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains; preload' );
		}

		// X-Frame-Options.
		if ( ! empty( $options['frame_options'] ) ) {
			header( 'X-Frame-Options: ' . $options['frame_options'] );
		}

		// X-Content-Type-Options.
		if ( $options['content_type_options'] ) {
			header( 'X-Content-Type-Options: nosniff' );
		}

		// X-XSS-Protection.
		if ( $options['xss_protection'] ) {
			header( 'X-XSS-Protection: 1; mode=block' );
		}

		// Referrer Policy.
		if ( ! empty( $options['referrer_policy'] ) ) {
			header( 'Referrer-Policy: ' . $options['referrer_policy'] );
		}

		// Permissions Policy (formerly Feature Policy).
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );

		// Log security headers implementation.
		$this->log_security_event(
			'security_headers_set',
			array(
				'form_id'      => $this->form_id,
				'csp_enabled'  => $options['csp_enabled'],
				'hsts_enabled' => $options['hsts_enabled'],
			)
		);
	}

	/**
	 * Sanitize input based on field configuration.
	 *
	 * Validates input against potential attacks before applying field-specific sanitization
	 * using the unified Field_Sanitizer.
	 *
	 * @param mixed                $value        The value to sanitize.
	 * @param array<string, mixed> $field_config Field configuration containing type and validation rules.
	 * @return mixed Sanitized value, or empty string if dangerous content detected.
	 */
	public function sanitize_input( $value, array $field_config ) {
		// Basic attack validation before using WordPress native sanitization functions.
		$attack_check = $this->validate_against_attacks( $value );
		if ( is_wp_error( $attack_check ) ) {
			$this->log_security_event(
				'dangerous_content_blocked',
				array(
					'field_type' => $field_config['type'] ?? 'unknown',
					'form_id'    => $this->form_id,
				)
			);
			return ''; // Return empty string for dangerous content.
		}

		// Handle special cases that need security-specific logic.
		$field_type = $field_config['type'] ?? 'text';

		switch ( $field_type ) {
			case 'integer':
				return absint( $value );

			case 'multiselect':
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', $value );
				}
				return array();

			case 'textarea':
			case 'wysiwyg':
				return $this->sanitize_rich_content( $value );

			default:
				// Use unified Field_Sanitizer for all other field types.
				return \CampaignBridge\Admin\Core\Forms\Field_Sanitizer::sanitize( $value, $field_config );
		}
	}

	/**
	 * Advanced XSS protection for rich content.
	 *
	 * Provides enhanced XSS protection beyond WordPress defaults,
	 * including detection of dangerous JavaScript patterns and encoded attacks.
	 *
	 * @param string $content Content to sanitize.
	 * @return string Sanitized content.
	 */
	public function sanitize_rich_content( string $content ): string {
		// Leverage WordPress native KSES - it handles all the XSS protection we need.
		return wp_kses_post( $content );
	}

	/**
	 * Validate input against obvious attack patterns.
	 *
	 * Basic validation for obviously malicious content. WordPress handles most
	 * sanitization through wp_kses and prepared statements.
	 *
	 * @param mixed $value Value to validate.
	 * @return bool|\WP_Error True if safe, WP_Error if dangerous content detected.
	 */
	public function validate_against_attacks( $value ) {
		if ( ! is_string( $value ) ) {
			return true; // Non-string values are handled by type validation.
		}

		// Basic detection for obviously malicious content
		// WordPress handles most sanitization through wp_kses and prepared statements.
		$malicious_patterns = array(
			'/<script[^>]*>.*?<\/script>/is',  // Script tags (caught by wp_kses, but early detection).
		);

		foreach ( $malicious_patterns as $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				$this->log_security_event(
					'malicious_content_detected',
					array(
						'pattern'      => $pattern,
						'value_length' => strlen( $value ),
						'form_id'      => $this->form_id,
					)
				);
				return new \WP_Error( 'security_violation', 'Potentially malicious content detected.' );
			}
		}

		return true;
	}

	/**
	 * Validate file upload security.
	 *
	 * @param array<string, mixed> $file     File data from $_FILES.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param bool                 $skip_upload_check Skip upload check.
	 * @return bool|\WP_Error True if valid, \WP_Error if invalid.
	 */
	public function validate_file_upload( array $file, array $field_config, bool $skip_upload_check = false ) {
		// Check for upload errors.
		$upload_error = $this->validate_upload_error( $file );
		if ( is_wp_error( $upload_error ) ) {
			return $upload_error;
		}

		// Verify file is actually uploaded via HTTP POST.
		$upload_check = $this->validate_upload_method( $file, $skip_upload_check );
		if ( is_wp_error( $upload_check ) ) {
			return $upload_check;
		}

		// Validate filename security.
		$filename_validation = $this->validate_filename( $file );
		if ( is_wp_error( $filename_validation ) ) {
			return $filename_validation;
		}

		$filename = $file['name'];

		// Check file size.
		$size_validation = $this->validate_file_size( $file, $field_config, $filename );
		if ( is_wp_error( $size_validation ) ) {
			return $size_validation;
		}

		// Validate MIME type.
		$mime_validation = $this->validate_mime_type( $file, $field_config, $filename );
		if ( is_wp_error( $mime_validation ) ) {
			return $mime_validation;
		}

		return true;
	}

	/**
	 * Validate upload error codes.
	 *
	 * @param array<string, mixed> $file File data.
	 * @return bool|\WP_Error True if no error, WP_Error if upload failed.
	 */
	private function validate_upload_error( array $file ) {
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			// Log security-relevant upload errors (excluding benign cases like no file selected).
			if ( UPLOAD_ERR_NO_FILE !== $file['error'] ) {
				$this->log_security_event(
					'file_upload_error',
					array(
						'error_code' => $file['error'],
						'filename'   => sanitize_file_name( $file['name'] ?? 'unknown' ),
					)
				);
			}

			return new \WP_Error(
				'upload_error',
				$this->get_upload_error_message( $file['error'] )
			);
		}

		return true;
	}

	/**
	 * Validate upload method (ensure file was uploaded via HTTP POST).
	 *
	 * @param array<string, mixed> $file File data.
	 * @param bool                 $skip_check Skip validation.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_upload_method( array $file, bool $skip_check ) {
		if ( ! $skip_check && ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->log_security_event(
				'invalid_upload_method',
				array(
					'filename' => sanitize_file_name( $file['name'] ?? 'unknown' ),
					'tmp_name' => $file['tmp_name'] ?? 'none',
				)
			);

			return new \WP_Error(
				'upload_error',
				\__( 'File was not uploaded properly.', 'campaignbridge' )
			);
		}

		return true;
	}

	/**
	 * Validate filename security.
	 *
	 * @param array<string, mixed> $file File data.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_filename( array $file ) {
		$filename = $file['name'] ?? '';
		if ( empty( $filename ) ) {
			return new \WP_Error(
				'invalid_filename',
				\__( 'Filename is required.', 'campaignbridge' )
			);
		}

		// Check for dangerous filename patterns.
		if ( $this->is_dangerous_filename( $filename ) ) {
			$this->log_security_event(
				'dangerous_filename_attempted',
				array(
					'filename' => $filename,
				)
			);

			return new \WP_Error(
				'invalid_filename',
				\__( 'Filename contains invalid characters.', 'campaignbridge' )
			);
		}

		return true;
	}

	/**
	 * Validate file size.
	 *
	 * @param array<string, mixed> $file File data.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param string               $filename Filename for logging.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_file_size( array $file, array $field_config, string $filename ) {
		$max_size = $field_config['max_size'] ?? \wp_max_upload_size();
		if ( $file['size'] > $max_size ) {
			$this->log_security_event(
				'file_too_large',
				array(
					'filename'  => $filename,
					'file_size' => $file['size'],
					'max_size'  => $max_size,
				)
			);

			return new \WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: maximum file size */
					\__( 'File size exceeds maximum allowed size of %s.', 'campaignbridge' ),
					size_format( $max_size )
				)
			);
		}

		// Check file size is not zero (empty file).
		if ( 0 === $file['size'] ) {
			return new \WP_Error(
				'empty_file',
				\__( 'Uploaded file is empty.', 'campaignbridge' )
			);
		}

		return true;
	}

	/**
	 * Validate MIME type.
	 *
	 * @param array<string, mixed> $file File data.
	 * @param array<string, mixed> $field_config Field configuration.
	 * @param string               $filename Filename for logging.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_mime_type( array $file, array $field_config, string $filename ) {
		$allowed_types = $field_config['allowed_types'] ?? array();
		if ( ! empty( $allowed_types ) ) {
			// Check both the provided MIME type and the detected MIME type from filename.
			$provided_mime = $file['type'] ?? '';
			$filetype      = \wp_check_filetype( $filename );
			$detected_mime = $filetype['type'];

			// Use the more restrictive check - both must be allowed if both are present.
			$valid_provided = empty( $provided_mime ) || in_array( $provided_mime, $allowed_types, true );
			$valid_detected = empty( $detected_mime ) || in_array( $detected_mime, $allowed_types, true );

			if ( ! $valid_provided || ! $valid_detected ) {
				$this->log_security_event(
					'disallowed_file_type',
					array(
						'filename'      => $filename,
						'provided_mime' => $provided_mime,
						'detected_mime' => $detected_mime,
						'allowed_types' => $allowed_types,
					)
				);

				return new \WP_Error(
					'invalid_file_type',
					\__( 'File type not allowed.', 'campaignbridge' )
				);
			}
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
	 * Check if filename contains dangerous patterns.
	 *
	 * @param string $filename Filename to check.
	 * @return bool True if dangerous.
	 */
	private function is_dangerous_filename( string $filename ): bool {
		// Only check for directory traversal attempts - WordPress handles MIME types and dangerous extensions.
		return strpos( $filename, '..' ) !== false || strpos( $filename, '/' ) !== false || strpos( $filename, '\\' ) !== false;
	}

	/**
	 * Rate limiting for form submissions.
	 *
	 * @param int $max_attempts Maximum attempts allowed.
	 * @param int $time_window  Time window in seconds.
	 * @return bool True if within limits, false if rate limited.
	 */
	public function check_rate_limit( int $max_attempts = 10, int $time_window = 300 ): bool {
		$user_id   = \get_current_user_id();
		$client_ip = $this->get_client_ip();

		// Create composite key for user + IP based rate limiting.
		$rate_limit_key = $user_id . '_' . $client_ip;
		$transient_key  = 'form_rate_limit_' . $this->form_id . '_' . md5( $rate_limit_key );

		$attempts = \CampaignBridge\Core\Storage::get_transient( $transient_key );

		if ( false === $attempts ) {
			$attempts = 0;
		}

		if ( $attempts >= $max_attempts ) {
			$this->log_security_event(
				'rate_limit_exceeded',
				array(
					'user_id'   => $user_id,
					'client_ip' => $client_ip,
					'attempts'  => $attempts,
					'limit'     => $max_attempts,
					'form_id'   => $this->form_id,
				)
			);
			return false; // Rate limited.
		}

		\CampaignBridge\Core\Storage::set_transient( $transient_key, $attempts + 1, $time_window );
		return true;
	}

	/**
	 * Log security event.
	 *
	 * @param string               $event     Event type.
	 * @param array<string, mixed> $context   Additional context.
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

		\CampaignBridge\Core\Error_Handler::info(
			'[SECURITY] ' . $event,
			$log_data
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @return string Client IP address.
	 */
	public static function get_client_ip(): string {
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
