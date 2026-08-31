<?php
/**
 * Form AJAX Controller.
 *
 * Handles AJAX requests for conditional field logic evaluation.
 *
 * @package CampaignBridge\Admin\REST
 */

declare( strict_types=1 );

namespace CampaignBridge\Admin\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form AJAX Controller.
 *
 * Handles AJAX requests for conditional field logic evaluation.
 */
class Form_Rest_Controller {

	/**
	 * Rate limiting: maximum requests per window.
	 */
	private const RATE_LIMIT_REQUESTS = 20;

	/** Maximum number of nested form entries accepted per evaluation. */
	private const MAX_FORM_ENTRIES = 1000;

	/** Maximum nesting below the top-level form data array. */
	private const MAX_FORM_DEPTH = 5;

	/** Maximum UTF-8 byte length accepted for one scalar value. */
	private const MAX_FIELD_BYTES = 10000;

	/** Maximum aggregate bytes accepted across keys and scalar values. */
	private const MAX_FORM_BYTES = 262144;

	/**
	 * Sanitize form data.
	 *
	 * @param array<string, mixed> $data Form data to sanitize.
	 * @return array<string, mixed> Sanitized form data.
	 */
	public function sanitize_form_data( array $data ): array {
		return $this->sanitize_and_validate_form_data( $data );
	}

	/**
	 * Check if current user can access form functionality.
	 *
	 * @return bool True if user has access.
	 */
	public function can_access_form(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Evaluate conditional logic for form fields.
	 *
	 * @param \WP_REST_Request<array{form_id: string|null, data: mixed}> $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function evaluate_conditions( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		try {
			// Get form data from request.
			$form_id   = $request->get_param( 'form_id' );
			$form_data = $request->get_param( 'data' );

			// Validate form identifier.
			if ( empty( $form_id ) ) {
				return new \WP_Error( 'invalid_form_id', 'Invalid form identifier.', array( 'status' => 400 ) );
			}

			if ( null === $form_data ) {
				$form_data = array();
			}

			if ( ! is_array( $form_data ) ) {
				return new \WP_Error( 'invalid_form_data', 'Form data must be an object.', array( 'status' => 400 ) );
			}

			if ( $this->is_form_data_too_large( $form_data ) ) {
				return new \WP_Error( 'form_data_too_large', 'Form data is too large.', array( 'status' => 400 ) );
			}

			// Get the form configuration.
			$form_config = \CampaignBridge\Admin\Core\Form_Registry::get( $form_id );
			if ( ! $form_config ) {
				return new \WP_Error( 'form_not_found', 'Form configuration not found.', array( 'status' => 404 ) );
			}

			// Sanitize form data.
			$form_data = $this->sanitize_and_validate_form_data( $form_data );

			// Evaluate conditional logic.
			$conditional_manager = new \CampaignBridge\Admin\Core\Forms\Form_Conditional_Manager(
				$form_config->get_fields(),
				$form_data
			);

			$result = array(
				'success' => true,
				'fields'  => $conditional_manager->evaluate_all_fields( $form_id, get_current_user_id() ),
			);

			return new \WP_REST_Response( $result, 200 );

		} catch ( \Throwable $e ) {
			// Log error for debugging but don't expose sensitive information.
			\CampaignBridge\Core\Error_Handler::error(
				'Conditional evaluation error: ' . $e->getMessage(),
				array(
					'user_id' => get_current_user_id(),
					'form_id' => $form_id ?? 'unknown',
				)
			);
			return new \WP_Error( 'evaluation_error', 'An unexpected error occurred. Please try again.', array( 'status' => 500 ) );
		}
	}

	/**
	 * Handle AJAX request for conditional evaluation.
	 *
	 * Leverages WordPress built-in security: authentication, nonces, permissions.
	 *
	 * @return void
	 */
	public function handle_ajax_evaluate_conditions(): void {
		// Security: Add security headers for AJAX responses.
		$this->add_security_headers();

		// Security: Validate request origin for AJAX calls.
		if ( ! $this->validate_request_origin() ) {
			\CampaignBridge\Core\Error_Handler::error(
				'Invalid request origin',
				array(
					'user_id' => get_current_user_id(),
					'referer' => wp_get_referer(),
				)
			);
			wp_send_json_error( 'Invalid request origin.', 403 );
		}

		// WordPress built-in: Verify user authentication.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Authentication required.', 401 );
		}

		// WordPress built-in: Verify nonce for CSRF protection.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) ) {
			wp_send_json_error( 'Security validation failed.', 403 );
		}

		// WordPress built-in: Validate form identifier.
		$form_id = isset( $_POST['form_id'] ) ? sanitize_key( wp_unslash( $_POST['form_id'] ) ) : '';
		if ( empty( $form_id ) ) {
			wp_send_json_error( 'Invalid form identifier.', 400 );
		}

		// WordPress built-in: Verify form-specific nonce.
		$nonce_action = 'campaignbridge_form_' . $form_id;
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_send_json_error( 'Security validation failed.', 403 );
		}

		// WordPress built-in: Verify user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.', 403 );
		}

		// Security: Enhanced rate limiting with sliding window (60-second window).
		if ( ! $this->check_rate_limit( get_current_user_id() ) ) {
			\CampaignBridge\Core\Error_Handler::error(
				'Rate limit exceeded',
				array(
					'user_id' => get_current_user_id(),
					'ip'      => $this->get_client_ip(),
				)
			);
			wp_send_json_error( 'Rate limit exceeded. Please wait before making another request.', 429 );
		}

		// Read the bounded raw shape before sanitizing individual values.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The raw shape is bounded immediately and values are then sanitized.
		$raw_form_data = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array();

		// Security: Reject oversized input before recursive sanitization work.
		if ( $this->is_form_data_too_large( $raw_form_data ) ) {
			wp_send_json_error( 'Form data is too large.', 400 );
		}

		$form_data = $this->sanitize_and_validate_form_data( $raw_form_data );

		// Get the form configuration.
		$form_config = \CampaignBridge\Admin\Core\Form_Registry::get( $form_id );
		if ( ! $form_config ) {
			wp_send_json_error( 'Form configuration not found.', 404 );
		}

		try {
			// Evaluate conditional logic. Keep response termination outside this
			// block so WordPress AJAX test handlers are not mistaken for failures.
			$conditional_manager = new \CampaignBridge\Admin\Core\Forms\Form_Conditional_Manager(
				$form_config->get_fields(),
				$form_data
			);

			$result = array(
				'success' => true,
				'fields'  => $conditional_manager->evaluate_all_fields( $form_id, get_current_user_id() ),
			);

		} catch ( \Throwable $e ) {
			// Log error for debugging but don't expose sensitive information.
			\CampaignBridge\Core\Error_Handler::error(
				'Conditional evaluation error: ' . $e->getMessage(),
				array(
					'user_id' => get_current_user_id(),
					'form_id' => $form_id,
				)
			);
			wp_send_json_error( 'An unexpected error occurred. Please try again.', 500 );
		}

		wp_send_json( $result );
	}

	/**
	 * Validate form data size and depth to prevent DoS attacks.
	 *
	 * @param array<string, mixed> $data Form data to validate.
	 * @return bool True if data is too large, false otherwise.
	 */
	private function is_form_data_too_large( array $data ): bool {
		$remaining_entries = self::MAX_FORM_ENTRIES;
		$remaining_bytes   = self::MAX_FORM_BYTES;

		return ! $this->consume_form_data_budget( $data, 0, $remaining_entries, $remaining_bytes );
	}

	/**
	 * Consume a single aggregate budget while walking nested form data.
	 *
	 * @param array<mixed> $data              Raw form data.
	 * @param int          $depth             Current array depth.
	 * @param int          $remaining_entries Remaining entry budget.
	 * @param int          $remaining_bytes   Remaining byte budget.
	 * @return bool True when the data stays within every bound.
	 */
	private function consume_form_data_budget( array $data, int $depth, int &$remaining_entries, int &$remaining_bytes ): bool {
		if ( $depth > self::MAX_FORM_DEPTH ) {
			return false;
		}

		foreach ( $data as $key => $value ) {
			--$remaining_entries;
			$remaining_bytes -= strlen( (string) $key );

			if ( $remaining_entries < 0 || $remaining_bytes < 0 ) {
				return false;
			}

			if ( is_array( $value ) ) {
				if ( ! $this->consume_form_data_budget( $value, $depth + 1, $remaining_entries, $remaining_bytes ) ) {
					return false;
				}
				continue;
			}

			if ( null !== $value && ! is_scalar( $value ) ) {
				return false;
			}

			$value_bytes = strlen( (string) $value );
			if ( $value_bytes > self::MAX_FIELD_BYTES ) {
				return false;
			}

			$remaining_bytes -= $value_bytes;
			if ( $remaining_bytes < 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitize and validate form data to prevent XSS and injection attacks.
	 *
	 * @param array<mixed> $data Raw top-level form data from POST.
	 * @return array<string, mixed> Sanitized and validated form data.
	 */
	private function sanitize_and_validate_form_data( array $data ): array {
		$sanitized = array();

		foreach ( $data as $field_key => $field_value ) {
			if ( ! is_string( $field_key ) || ! preg_match( '/^[a-zA-Z0-9_-]+$/', $field_key ) ) {
				continue; // Skip invalid field keys.
			}

			$sanitized[ $field_key ] = is_array( $field_value )
				? $this->sanitize_nested_form_data( $field_value )
				: $this->sanitize_field_value( $field_value );
		}

		return $sanitized;
	}

	/**
	 * Sanitize nested objects and lists while preserving safe keys.
	 *
	 * @param array<mixed> $data Raw nested form data.
	 * @return array<int|string, mixed> Sanitized nested form data.
	 */
	private function sanitize_nested_form_data( array $data ): array {
		$sanitized = array();

		foreach ( $data as $field_key => $field_value ) {
			$is_valid_key = is_int( $field_key )
				|| preg_match( '/^[a-zA-Z0-9_-]+$/', $field_key );

			if ( ! $is_valid_key ) {
				continue;
			}

			$sanitized[ $field_key ] = is_array( $field_value )
				? $this->sanitize_nested_form_data( $field_value )
				: $this->sanitize_field_value( $field_value );
		}

		return $sanitized;
	}

	/**
	 * Sanitize individual field values based on expected type.
	 *
	 * @param mixed $value Field value to sanitize.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_field_value( $value ) {
		switch ( gettype( $value ) ) {
			case 'string':
				return sanitize_text_field( $value );

			case 'integer':
				return absint( $value );

			case 'double':
				return (float) $value;

			case 'boolean':
				return (bool) $value;

			default:
				return ''; // Reject unknown types.
		}
	}

	/**
	 * Enhanced rate limiting with sliding window to prevent burst attacks.
	 *
	 * @param int $user_id User ID to check rate limit for.
	 * @return bool True if within limits, false if exceeded.
	 */
	private function check_rate_limit( int $user_id ): bool {
		$current_time   = time();
		$window_seconds = 60; // 1-minute sliding window
		$max_requests   = self::RATE_LIMIT_REQUESTS;

		$rate_limit_key  = 'conditional_rate_limit_' . $user_id;
		$transient_value = \CampaignBridge\Core\Storage::get_transient( $rate_limit_key );

		// Use more efficient array structure for better performance.
		if ( ! is_array( $transient_value ) ) {
			$transient_value = array(
				'requests'     => array(),
				'last_cleanup' => $current_time,
			);
		}

		// Periodic cleanup to prevent array from growing too large.
		if ( $current_time - $transient_value['last_cleanup'] > 30 ) {
			$transient_value['requests']     = array_filter(
				$transient_value['requests'],
				function ( $timestamp ) use ( $current_time, $window_seconds ) {
					return ( $current_time - $timestamp ) < $window_seconds;
				}
			);
			$transient_value['last_cleanup'] = $current_time;
		}

		// Check if under the limit.
		if ( count( $transient_value['requests'] ) >= $max_requests ) {
			return false;
		}

		// Add current request timestamp.
		$transient_value['requests'][] = $current_time;

		// Store updated request log with optimized TTL.
		$ttl = min( $window_seconds, 300 ); // Don't cache longer than 5 minutes.
		\CampaignBridge\Core\Storage::set_transient( $rate_limit_key, $transient_value, $ttl );

		return true;
	}

	/**
	 * Get client IP address for security logging.
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip(): string {
		return \CampaignBridge\Core\Client_Address::get();
	}

	/**
	 * Validate request origin to prevent cross-origin attacks.
	 *
	 * @return bool True if request origin is valid.
	 */
	private function validate_request_origin(): bool {
		// Allow requests from admin area.
		if ( is_admin() ) {
			return true;
		}

		// Allow requests in debug/test environments (WP_DEBUG enabled).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}

		// For AJAX requests, validate referer.
		$referer = wp_get_referer();
		if ( ! $referer ) {
			return false;
		}

		// Get site URL for comparison.
		$site_url         = get_site_url();
		$site_host_raw    = wp_parse_url( $site_url, PHP_URL_HOST );
		$referer_host_raw = wp_parse_url( $referer, PHP_URL_HOST );
		if ( ! is_string( $site_host_raw ) || ! is_string( $referer_host_raw ) ) {
			return false;
		}
		$site_host    = sanitize_text_field( $site_host_raw );
		$referer_host = sanitize_text_field( $referer_host_raw );

		// Allow same domain and subdomains.
		if ( $referer_host === $site_host || strpos( $referer_host, '.' . $site_host ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Add security headers for AJAX responses.
	 */
	private function add_security_headers(): void {
		if ( ! headers_sent() ) {
			// Prevent MIME type sniffing.
			header( 'X-Content-Type-Options: nosniff' );

			// Enable XSS protection.
			header( 'X-XSS-Protection: 1; mode=block' );

			// Prevent clickjacking.
			header( 'X-Frame-Options: SAMEORIGIN' );

			// Referrer policy for AJAX.
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );

			// Content Security Policy for AJAX responses.
			header( "Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'" );

			// Prevent caching of sensitive responses.
			header( 'Cache-Control: no-cache, no-store, must-revalidate' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}
	}
}
